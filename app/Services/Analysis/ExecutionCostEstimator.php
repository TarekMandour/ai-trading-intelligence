<?php

namespace App\\Services\\Analysis;

class ExecutionCostEstimator
{
    /** @return array{available:bool,fee:float,spread:float,slippage:float,total:float,buy_vwap?:float,sell_vwap?:float,base_quantity?:float,reason?:string} */
    public function estimate(?float $ask, ?float $bid, float $notional, float $feeRate = .001): array
    {
        if ($ask === null || $bid === null || $ask <= 0 || $bid <= 0 || $bid >= $ask || $notional <= 0 || $feeRate < 0) {
            return $this->unavailable('INVALID_TOP_OF_BOOK_OR_COST_CONFIGURATION');
        }

        $mid = ($ask + $bid) / 2;
        $spread = ($ask - $bid) / $mid * $notional;
        $slippage = 0.0;
        $fee = $notional * $feeRate * 2;

        return ['available' => true, 'fee' => $fee, 'spread' => $spread, 'slippage' => $slippage, 'total' => $fee + $spread + $slippage];
    }

    /**
     * Estimate a market buy for quote notional and a market sell of the acquired
     * base quantity using the visible depth levels. A partial book is never treated
     * as sufficient liquidity.
     *
     * @param array{bids:array<int,array{price:float,quantity:float}>,asks:array<int,array{price:float,quantity:float}>} $book
     * @return array<string,mixed>
     */
    public function estimateFromOrderBook(array $book, float $notional, float $feeRate): array
    {
        if ($notional <= 0 || $feeRate < 0 || empty($book['bids']) || empty($book['asks'])) {
            return $this->unavailable('INVALID_ORDER_BOOK_OR_COST_CONFIGURATION');
        }

        $buy = $this->fillByQuote($book['asks'], $notional);
        if ($buy === null) {
            return $this->unavailable('INSUFFICIENT_ASK_DEPTH');
        }

        $sell = $this->fillByBase($book['bids'], $buy['quantity']);
        if ($sell === null) {
            return $this->unavailable('INSUFFICIENT_BID_DEPTH');
        }

        $bestAsk = (float) $book['asks'][0]['price'];
        $bestBid = (float) $book['bids'][0]['price'];
        if ($bestAsk <= 0 || $bestBid <= 0 || $bestBid >= $bestAsk) {
            return $this->unavailable('CROSSED_OR_INVALID_ORDER_BOOK');
        }

        $mid = ($bestAsk + $bestBid) / 2;
        $spread = ($bestAsk - $bestBid) * $buy['quantity'];
        $slippage = max(0.0, $buy['vwap'] - $bestAsk) * $buy['quantity']
            + max(0.0, $bestBid - $sell['vwap']) * $sell['quantity'];
        $fee = ($notional + $sell['quote']) * $feeRate;

        return [
            'available' => true,
            'fee' => $fee,
            'spread' => $spread,
            'slippage' => $slippage,
            'total' => $fee + $spread + $slippage,
            'buy_vwap' => $buy['vwap'],
            'sell_vwap' => $sell['vwap'],
            'base_quantity' => $buy['quantity'],
            'buy_quote' => $notional,
            'sell_quote' => $sell['quote'],
            'reference_mid' => $mid,
        ];
    }

    /** @param array<int,array{price:float,quantity:float}> $levels */
    private function fillByQuote(array $levels, float $quoteTarget): ?array
    {
        $remaining = $quoteTarget;
        $quantity = 0.0;
        $quote = 0.0;

        foreach ($levels as $level) {
            $price = (float) $level['price'];
            $available = (float) $level['quantity'];
            if ($price <= 0 || $available <= 0) {
                continue;
            }
            $takeQuote = min($remaining, $price * $available);
            $takeQuantity = $takeQuote / $price;
            $quote += $takeQuote;
            $quantity += $takeQuantity;
            $remaining -= $takeQuote;
            if ($remaining <= max(0.00000001, $quoteTarget * 0.000000001)) {
                return ['quantity' => $quantity, 'vwap' => $quote / $quantity];
            }
        }

        return null;
    }

    /** @param array<int,array{price:float,quantity:float}> $levels */
    private function fillByBase(array $levels, float $baseTarget): ?array
    {
        $remaining = $baseTarget;
        $quote = 0.0;
        $quantity = 0.0;

        foreach ($levels as $level) {
            $price = (float) $level['price'];
            $available = (float) $level['quantity'];
            if ($price <= 0 || $available <= 0) {
                continue;
            }
            $take = min($remaining, $available);
            $quote += $take * $price;
            $quantity += $take;
            $remaining -= $take;
            if ($remaining <= max(0.00000001, $baseTarget * 0.000000001)) {
                return ['quantity' => $quantity, 'quote' => $quote, 'vwap' => $quote / $quantity];
            }
        }

        return null;
    }

    /** @return array{available:false,fee:float,spread:float,slippage:float,total:float,reason:string} */
    private function unavailable(string $reason): array
    {
        return ['available' => false, 'fee' => 0.0, 'spread' => 0.0, 'slippage' => 0.0, 'total' => 0.0, 'reason' => $reason];
    }
}
