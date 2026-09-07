<?php

namespace App\Services\MarketData;

use App\Contracts\MarketDataProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TwelveDataProvider implements MarketDataProvider
{
    private function key(): string
    {
        return (string) config('services.twelvedata.key');
    }

    public function quote(string $symbol): array
    {
        if ($this->key() === '') {
            throw new RuntimeException('Market provider is not configured.');
        }

        return Cache::store(config('trading.cache_store'))->remember('market.quote.'.md5($symbol), 30, function () use ($symbol) {
            $r = Http::timeout(10)->get('https://api.twelvedata.com/quote', ['symbol' => $symbol, 'apikey' => $this->key()])->throw()->json();
            if (($r['status'] ?? null) === 'error') {
                throw new RuntimeException($r['message'] ?? 'Provider error.');
            }

return ['symbol' => $symbol, 'open' => (float) $r['open'], 'high' => (float) $r['high'], 'low' => (float) $r['low'], 'close' => (float) $r['close'], 'change_percent' => (float) $r['percent_change'], 'timestamp' => $r['datetime'] ?? now()->toIso8601String(), 'source' => 'Twelve Data'];
        });
    }

    public function candles(string $symbol, string $timeframe, int $limit = 300): array
    {
        if ($this->key() === '') {
            throw new RuntimeException('Market provider is not configured.');
        }

        return Cache::store(config('trading.cache_store'))->remember('market.candles.'.md5("$symbol:$timeframe:$limit"), 60, function () use ($symbol, $timeframe, $limit) {
            $map = ['M1' => '1min', 'M5' => '5min', 'M15' => '15min', 'M30' => '30min', 'H1' => '1h', 'H4' => '4h', 'D1' => '1day'];
            $r = Http::timeout(15)->get('https://api.twelvedata.com/time_series', ['symbol' => $symbol, 'interval' => $map[$timeframe] ?? '15min', 'outputsize' => $limit, 'apikey' => $this->key()])->throw()->json();
            if (! isset($r['values'])) {
                throw new RuntimeException($r['message'] ?? 'Candle data unavailable.');
            }

return array_reverse(array_map(fn ($v) => ['time' => $v['datetime'], 'open' => (float) $v['open'], 'high' => (float) $v['high'], 'low' => (float) $v['low'], 'close' => (float) $v['close'], 'volume' => (float) ($v['volume'] ?? 0)], $r['values']));
        });
    }
}
