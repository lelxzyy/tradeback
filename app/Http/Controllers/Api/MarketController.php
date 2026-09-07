<?php

namespace App\Http\Controllers\Api;

use App\Contracts\MarketDataProvider;
use App\Http\Controllers\Controller;
use App\Services\Trading\AiAnalysisService;
use App\Services\Trading\SignalEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class MarketController extends Controller
{
    public function __construct(private MarketDataProvider $provider) {}

    private function unavailable(Throwable $e): JsonResponse
    {
        $message = strtolower($e->getMessage());
        $code = match (true) {
            str_contains($message, '401'), str_contains($message, 'unauthorized') => 'KEY_VAULT_UNAUTHORIZED',
            str_contains($message, 'no active api key'), str_contains($message, '404') => 'NO_ACTIVE_API_KEY',
            str_contains($message, '429'), str_contains($message, 'credits'), str_contains($message, 'exhausted') => 'API_CREDITS_EXHAUSTED',
            str_contains($message, 'timed out'), str_contains($message, 'timeout') => 'PROVIDER_TIMEOUT',
            str_contains($message, 'not configured') => 'PROVIDER_NOT_CONFIGURED',
            default => 'MARKET_PROVIDER_ERROR',
        };

        report($e);

        return response()->json(['status' => 'unavailable', 'message' => 'Market Data Unavailable', 'error_code' => $code, 'detail' => app()->isLocal() ? $e->getMessage() : null], 503);
    }

    public function quote(Request $request): JsonResponse
    {
        try {
            if ($request->boolean('refresh')) {
                Cache::store(config('trading.cache_store'))->forget('market.quote.'.md5(config('trading.symbol')));
            }
            return response()->json(['status' => 'ok', 'data' => $this->provider->quote(config('trading.symbol'))]);
        } catch (Throwable $e) {
            return $this->unavailable($e);
        }
    }

    public function usage(Request $request): JsonResponse
    {
        try {
            if ($request->boolean('refresh')) {
                Cache::store(config('trading.cache_store'))->forget('market.api_usage');
            }
            return response()->json(['status' => 'ok', 'data' => $this->provider->usage()]);
        } catch (Throwable $e) {
            return $this->unavailable($e);
        }
    }

    public function candles(Request $r): JsonResponse
    {
        $v = $r->validate(['timeframe' => 'nullable|in:M1,M5,M15,M30,H1,H4,D1', 'limit' => 'nullable|integer|min:55|max:5000']);
        try {
            return response()->json(['status' => 'ok', 'data' => $this->provider->candles(config('trading.symbol'), $v['timeframe'] ?? 'M15', $v['limit'] ?? 300)]);
        } catch (Throwable $e) {
            return $this->unavailable($e);
        }
    }

    public function analysis(Request $r, SignalEngine $e): JsonResponse
    {
        $v = $r->validate(['timeframe' => 'nullable|in:M1,M5,M15,M30,H1,H4,D1']);
        try {
            return response()->json(['status' => 'ok', 'data' => $e->analyze($this->provider->candles(config('trading.symbol'), $v['timeframe'] ?? 'M15', 300))]);
        } catch (Throwable $x) {
            return $this->unavailable($x);
        }
    }

    public function aiAnalysis(Request $request, SignalEngine $engine, AiAnalysisService $ai): JsonResponse
    {
        $validated = $request->validate(['timeframe' => 'nullable|in:M1,M5,M15,M30,H1,H4,D1']);
        $timeframe = $validated['timeframe'] ?? 'M15';

        try {
            $analysis = $engine->analyze($this->provider->candles(config('trading.symbol'), $timeframe, 300));

            return response()->json([
                'status' => 'ok',
                'data' => array_merge($analysis, ['ai' => $ai->explain($analysis, $timeframe)]),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'AI Analysis Unavailable',
                'detail' => app()->isLocal() ? $e->getMessage() : null,
            ], 503);
        }
    }
}
