<?php

namespace App\Services\Analysis;

class ExecutionCostEstimator
{
    /** @return array{available:bool,fee:float,spread:float,slippage:float,total:float} */
    public function estimate(?float $ask, ?float $bid, float $notional, float $feeRate = .001): array
    {
        if ($ask === null || $bid === null || $ask <= 0 || $bid <= 0 || $bid > $ask) {
            return ['available' => false, 'fee' => 0, 'spread' => 0, 'slippage' => 0, 'total' => 0];
        }
        $spread = ($ask - $bid) / $ask * $notional;
        $slippage = $notional * .0005;
        $fee = $notional * $feeRate * 2;

        return compact('fee', 'spread', 'slippage') + ['available' => true, 'total' => $fee + $spread + $slippage];
    }

    /** @param array{bids:array<int,array{price:float,quantity:float}>,asks:array<int,array{price:float,quantity:float}>} $book */
    public function estimateFromOrderBook(array $book, float $notional, float $feeRate): array
    {
        $ask = $book['asks'][0]['price'] ?? null;
        $bid = $book['bids'][0]['price'] ?? null;
        $cost = $this->estimate($ask, $bid, $notional, $feeRate);
        if (! $cost['available'] || $book['asks'][0]['quantity'] * $ask < $notional || $book['bids'][0]['quantity'] * $bid < $notional) {
            return ['available' => false, 'fee' => 0, 'spread' => 0, 'slippage' => 0, 'total' => 0];
        }

        return $cost;
    }
}
