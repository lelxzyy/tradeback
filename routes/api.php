<?php

use App\Http\Controllers\Api\CalculatorController;
use App\Http\Controllers\Api\MarketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('/health', fn () => ['status' => 'ok', 'timestamp' => now()->toIso8601String()]);
    Route::get('/market/xauusd', [MarketController::class, 'quote']);
    Route::get('/chart/xauusd', [MarketController::class, 'candles']);
    Route::get('/analysis/xauusd', [MarketController::class, 'analysis']);
    Route::post('/analysis/xauusd/ai', [MarketController::class, 'aiAnalysis']);
    Route::post('/calculator/profit', [CalculatorController::class, 'profit']);
    Route::post('/calculator/risk', [CalculatorController::class, 'risk']);
    Route::post('/calculator/breakeven', [CalculatorController::class, 'breakeven']);
    Route::post('/calculator/points', [CalculatorController::class, 'points']);
});
