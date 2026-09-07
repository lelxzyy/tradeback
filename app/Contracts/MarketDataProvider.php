<?php
namespace App\Contracts;
interface MarketDataProvider { public function quote(string $symbol): array; public function candles(string $symbol,string $timeframe,int $limit=300): array; public function usage(): array; }
