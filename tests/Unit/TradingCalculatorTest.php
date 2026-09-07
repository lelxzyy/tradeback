<?php
namespace Tests\Unit;
use App\Services\Trading\TradingCalculator;use PHPUnit\Framework\TestCase;
class TradingCalculatorTest extends TestCase {public function test_gold_profit_uses_broker_contract_size():void{$r=(new TradingCalculator)->profit(3500,3510,.01,'BUY',100);$this->assertSame(10.0,$r['estimated_profit']);}public function test_weighted_average_entry():void{$r=(new TradingCalculator)->breakeven([['entry'=>3550,'lot'=>.01],['entry'=>3545,'lot'=>.02],['entry'=>3540,'lot'=>.03]]);$this->assertEqualsWithDelta(3543.33333,$r['average_entry'],.00001);}}
