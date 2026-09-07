<?php

namespace App\Services\Trading;

final class SignalEngine
{
    public function __construct(private IndicatorService $indicators) {}

    public function analyze(array $candles): array
    {
        if (count($candles) < 55) {
            return ['status' => 'NO TRADE', 'confidence' => 0, 'reason' => 'Insufficient OHLC history.'];
        }

        $closes = array_column($candles, 'close');
        $price = (float) end($closes);
        $ema20 = $this->indicators->ema($closes, 20);
        $ema50 = $this->indicators->ema($closes, 50);
        $rsi = $this->indicators->rsi($closes);
        $atr = $this->atr($candles, 14);
        $buy = $sell = 0;
        $reasons = [];

        if ($price > $ema20 && $ema20 > $ema50) {
            $buy += 35;
            $reasons[] = 'Price is above EMA 20 and EMA 50.';
        } elseif ($price < $ema20 && $ema20 < $ema50) {
            $sell += 35;
            $reasons[] = 'Price is below EMA 20 and EMA 50.';
        }
        if ($rsi >= 52 && $rsi < 70) {
            $buy += 15;
            $reasons[] = 'RSI confirms bullish momentum.';
        } elseif ($rsi <= 48 && $rsi > 30) {
            $sell += 15;
            $reasons[] = 'RSI confirms bearish momentum.';
        }

        $recent = array_slice($candles, -20);
        $support = (float) min(array_column($recent, 'low'));
        $resistance = (float) max(array_column($recent, 'high'));
        $location = ($price - $support) / max($resistance - $support, 0.00001);
        if ($location < .35) {
            $buy += 20;
            $reasons[] = 'Price is near recent support.';
        }
        if ($location > .65) {
            $sell += 20;
            $reasons[] = 'Price is near recent resistance.';
        }

        $score = max($buy, $sell);
        $direction = $buy > $sell ? 'BUY' : 'SELL';
        $status = $score < 60 ? 'NO TRADE' : $direction;
        $setup = $this->buildSetup($direction, $price, $support, $resistance, $ema20, $atr, $score >= 60);

        return [
            'status' => $status,
            'directional_bias' => $direction,
            'confidence' => $score,
            'classification' => $score >= 75 ? 'STRONG SIGNAL' : ($score >= 60 ? 'VALID SIGNAL' : ($score >= 40 ? 'WEAK SIGNAL' : 'NO TRADE')),
            'current_price' => $this->round($price),
            'indicators' => ['ema20' => $ema20, 'ema50' => $ema50, 'rsi14' => $rsi, 'atr14' => $this->round($atr)],
            'levels' => ['support' => $this->round($support), 'resistance' => $this->round($resistance)],
            'buy_confidence' => $buy,
            'sell_confidence' => $sell,
            'primary_setup' => $setup,
            'reasons' => $reasons,
            'disclaimer' => 'Calculated scenario for information only; no setup is guaranteed.',
        ];
    }

    private function buildSetup(string $direction, float $price, float $support, float $resistance, float $ema20, float $atr, bool $valid): array
    {
        if ($direction === 'BUY') {
            $entryMid = min($price, max($support + .35 * $atr, $ema20));
            $entryLow = $entryMid - .15 * $atr;
            $entryHigh = $entryMid + .15 * $atr;
            $sl = min($support - .35 * $atr, $entryLow - .8 * $atr);
            $risk = $entryMid - $sl;
            $type = $entryHigh < $price ? 'BUY LIMIT' : 'BUY';
            $invalidation = 'Cancel if an M15 candle closes below '.number_format($sl, 2, '.', '').'.';
            $targets = [$entryMid + $risk, $entryMid + 2 * $risk, max($resistance, $entryMid + 3 * $risk)];
        } else {
            $entryMid = max($price, min($resistance - .35 * $atr, $ema20));
            $entryLow = $entryMid - .15 * $atr;
            $entryHigh = $entryMid + .15 * $atr;
            $sl = max($resistance + .35 * $atr, $entryHigh + .8 * $atr);
            $risk = $sl - $entryMid;
            $type = $entryLow > $price ? 'SELL LIMIT' : 'SELL';
            $invalidation = 'Cancel if an M15 candle closes above '.number_format($sl, 2, '.', '').'.';
            $targets = [$entryMid - $risk, $entryMid - 2 * $risk, min($support, $entryMid - 3 * $risk)];
        }

        return [
            'action' => $valid ? $type : 'WAIT',
            'conditional_order' => $type,
            'entry' => ['low' => $this->round($entryLow), 'high' => $this->round($entryHigh)],
            'stop_loss' => $this->round($sl),
            'take_profits' => array_map(fn ($target, $index) => ['label' => 'TP'.($index + 1), 'price' => $this->round($target), 'rr' => $index + 1], $targets, array_keys($targets)),
            'risk_reward' => '1:3',
            'invalidation' => $invalidation,
            'executable' => $valid,
            'method' => 'Recent 20-candle swing + EMA20 retracement + ATR14 volatility buffer.',
        ];
    }

    private function atr(array $candles, int $period): float
    {
        $ranges = [];
        for ($i = 1; $i < count($candles); $i++) {
            $high = (float) $candles[$i]['high'];
            $low = (float) $candles[$i]['low'];
            $previous = (float) $candles[$i - 1]['close'];
            $ranges[] = max($high - $low, abs($high - $previous), abs($low - $previous));
        }

        return max(array_sum(array_slice($ranges, -$period)) / $period, .01);
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }
}
