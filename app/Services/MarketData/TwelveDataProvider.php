<?php

namespace App\Services\MarketData;

use App\Contracts\MarketDataProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TwelveDataProvider implements MarketDataProvider
{
    private function isQuietHours(): bool
    {
        $hour = now(config('trading.quiet_timezone'))->hour;

        return $hour >= config('trading.quiet_start_hour')
            && $hour < config('trading.quiet_end_hour');
    }

    private function key(): string
    {
        return (string) config('services.twelvedata.key');
    }

    private function request(string $endpoint, array $parameters): array
    {
        $vaultUrl = config('services.key_vault.url');
        $vaultSecret = config('services.key_vault.secret');
        $excluded = [];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $id = null;
            if ($vaultUrl && $vaultSecret) {
                $selected = Http::withToken($vaultSecret)->timeout(10)->get(rtrim($vaultUrl, '/').'/api/internal/provider-key', ['exclude' => implode(',', $excluded)])->throw()->json();
                $key = $selected['key'] ?? '';
                $id = $selected['id'] ?? null;
            } else {
                $key = $this->key();
            }
            if ($key === '') throw new RuntimeException('Market provider is not configured.');

            $response = Http::timeout(15)->get("https://api.twelvedata.com/$endpoint", [...$parameters, 'apikey' => $key]);
            $data = $response->json();
            $exhausted = $response->status() === 429 || (int) ($data['code'] ?? 0) === 429;
            if (! $exhausted) return $response->throw()->json();
            if (! $id || ! $vaultUrl) return $response->throw()->json();

            $excluded[] = $id;
            Http::withToken($vaultSecret)->timeout(10)->post(rtrim($vaultUrl, '/').'/api/internal/provider-key', ['id' => $id, 'reason' => 'Daily credits exhausted']);
        }
        throw new RuntimeException('All Twelve Data API keys are exhausted.');
    }

    public function usage(): array
    {
        if ($this->key() === '') {
            throw new RuntimeException('Market provider is not configured.');
        }

        return Cache::store(config('trading.cache_store'))->remember('market.api_usage', 300, function () {
            $usage = Http::withHeaders(['Authorization' => 'apikey '.$this->key()])
                ->timeout(10)
                ->get('https://api.twelvedata.com/api_usage')
                ->throw()
                ->json();

            if (! isset($usage['daily_usage'], $usage['plan_daily_limit'])) {
                throw new RuntimeException($usage['message'] ?? 'API usage data unavailable.');
            }

            $used = (int) $usage['daily_usage'];
            $limit = (int) $usage['plan_daily_limit'];

            return [
                'used' => $used,
                'limit' => $limit,
                'remaining' => max(0, $limit - $used),
                'percent_used' => $limit > 0 ? round(($used / $limit) * 100, 1) : 0,
                'plan' => $usage['plan_category'] ?? null,
                'checked_at' => now()->toIso8601String(),
            ];
        });
    }

    public function quote(string $symbol): array
    {
        if ($this->key() === '' && ! config('services.key_vault.url')) {
            throw new RuntimeException('Market provider is not configured.');
        }

        $cache = Cache::store(config('trading.cache_store'));
        $cacheKey = 'market.quote.'.md5($symbol);

        if ($this->isQuietHours()) {
            $lastQuote = $cache->get($cacheKey.'.last');
            if (is_array($lastQuote)) {
                $lastQuote['sync_enabled'] = false;
                return $lastQuote;
            }

            throw new RuntimeException('Market data is paused between 02:00 and 07:00 WIB and no cached quote is available.');
        }

        return $cache->remember($cacheKey, config('trading.quote_ttl'), function () use ($symbol, $cache, $cacheKey) {
            $r = $this->request('quote', ['symbol' => $symbol]);
            if (($r['status'] ?? null) === 'error') {
                throw new RuntimeException($r['message'] ?? 'Provider error.');
            }

            $quote = ['symbol' => $symbol, 'open' => (float) $r['open'], 'high' => (float) $r['high'], 'low' => (float) $r['low'], 'close' => (float) $r['close'], 'change_percent' => (float) $r['percent_change'], 'timestamp' => $r['datetime'] ?? now()->toIso8601String(), 'synced_at' => now()->toIso8601String(), 'sync_enabled' => true, 'source' => 'Twelve Data'];
            $cache->forever($cacheKey.'.last', $quote);

            return $quote;
        });
    }

    public function candles(string $symbol, string $timeframe, int $limit = 300): array
    {
        if ($this->key() === '' && ! config('services.key_vault.url')) {
            throw new RuntimeException('Market provider is not configured.');
        }

        $cache = Cache::store(config('trading.cache_store'));
        $cacheKey = 'market.candles.'.md5("$symbol:$timeframe:$limit");

        if ($this->isQuietHours()) {
            $lastCandles = $cache->get($cacheKey.'.last');
            if (is_array($lastCandles)) {
                return $lastCandles;
            }

            throw new RuntimeException('Market data is paused between 02:00 and 07:00 WIB and no cached candles are available.');
        }

        return $cache->remember($cacheKey, config('trading.candles_ttl'), function () use ($symbol, $timeframe, $limit, $cache, $cacheKey) {
            $map = ['M1' => '1min', 'M5' => '5min', 'M15' => '15min', 'M30' => '30min', 'H1' => '1h', 'H4' => '4h', 'D1' => '1day'];
            $r = $this->request('time_series', ['symbol' => $symbol, 'interval' => $map[$timeframe] ?? '15min', 'outputsize' => $limit]);
            if (! isset($r['values'])) {
                throw new RuntimeException($r['message'] ?? 'Candle data unavailable.');
            }

            $candles = array_reverse(array_map(fn ($v) => ['time' => $v['datetime'], 'open' => (float) $v['open'], 'high' => (float) $v['high'], 'low' => (float) $v['low'], 'close' => (float) $v['close'], 'volume' => (float) ($v['volume'] ?? 0)], $r['values']));
            $cache->forever($cacheKey.'.last', $candles);

            return $candles;
        });
    }
}
