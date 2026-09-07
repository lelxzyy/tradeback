<?php
namespace App\Services\Trading;
use InvalidArgumentException;
final class TradingCalculator {
 public function profit(float $entry,float $exit,float $lot,string $direction,float $contractSize): array {$delta=strtoupper($direction)==='BUY'?$exit-$entry:$entry-$exit;return ['price_difference'=>abs($exit-$entry),'estimated_profit'=>round($delta*$lot*$contractSize,2)];}
 public function risk(float $balance,float $riskPercent,float $entry,float $sl,float $contractSize): array {if($entry==$sl||$contractSize<=0)throw new InvalidArgumentException('Invalid SL or contract size.');$max=$balance*$riskPercent/100;return ['maximum_risk'=>round($max,2),'suggested_lot'=>round($max/(abs($entry-$sl)*$contractSize),4),'warning'=>$riskPercent>5?'Risk above 5% is considered high.':null];}
 public function breakeven(array $p): array {$lots=array_sum(array_column($p,'lot'));if($lots<=0)throw new InvalidArgumentException('Total lot must be positive.');$w=array_sum(array_map(fn($x)=>$x['entry']*$x['lot'],$p));return ['total_lot'=>round($lots,4),'average_entry'=>round($w/$lots,5)];}
}
