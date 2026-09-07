<?php

namespace App\Services\Trading;

final class IndicatorService
{
    public function ema(array $v, int $p): ?float
    {
        if (count($v) < $p) {
            return null;
        } $k = 2 / ($p + 1);
        $e = array_sum(array_slice($v, 0, $p)) / $p;
        foreach (array_slice($v, $p) as $x) {
            $e = $x * $k + $e * (1 - $k);
        }

return round($e, 5);
    }

    public function rsi(array $v, int $p = 14): ?float
    {
        if (count($v) <= $p) {
            return null;
        }$g = $l = [];
        for ($i = 1; $i < count($v); $i++) {
            $d = $v[$i] - $v[$i - 1];
            $g[] = max(0, $d);
            $l[] = max(0, -$d);
        } $a = array_sum(array_slice($g, -$p)) / $p;
        $b = array_sum(array_slice($l, -$p)) / $p;

        return $b == 0 ? 100 : round(100 - (100 / (1 + $a / $b)), 2);
    }
}
