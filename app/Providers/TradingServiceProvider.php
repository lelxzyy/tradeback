<?php
namespace App\Providers;
use App\Contracts\MarketDataProvider;
use App\Services\MarketData\TwelveDataProvider;
use Illuminate\Support\ServiceProvider;
final class TradingServiceProvider extends ServiceProvider { public function register(): void { $this->app->bind(MarketDataProvider::class,TwelveDataProvider::class); } }
