<?php

namespace App\Services\Analysis;

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
        $fee = $notional * $feeRate * 2;

        return ['available' => true, 'fee' => $fee, 'spread' => $spread, 'slippage' => 0.0, 'total' => $fee + $spread];
    }

    /**
     * Estimate a market buy for quote notional and a market sell of the acquired
     * base quantity using visible depth. Both execution legs are costed against
     * the same midpoint so spread and market impact are not conflated or omitted.
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
        $quantity = $buy['quantity'];
        $spread = ($bestAsk - $bestBid) * $quantity;
        $slippage = max(0.0, $buy['quote'] - ($bestAsk * $quantity))
            + max(0.0, ($bestBid * $quantity) - $sell['quote']);
        $entryFee = $notional * $feeRate;
        $exitFee = $sell['quote'] * $feeRate;
        $entryExecutionCost = max(0.0, $buy['quote'] - ($mid * $quantity));
        $exitExecutionCost = max(0.0, ($mid * $quantity) - $sell['quote']);
        $entryCost = $entryExecutionCost + $entryFee;
        $exitCost = $exitExecutionCost + $exitFee;
        $fee = $entryFee + $exitFee;

        return [
            'available' => true,
            'fee' => $fee,
            'entry_fee' => $entryFee,
            'exit_fee' => $exitFee,
            'spread' => $spread,
            'slippage' => $slippage,
            'entry_cost' => $entryCost,
            'exit_cost' => $exitCost,
            'entry_cost_per_unit' => $entryCost / $quantity,
            'exit_cost_per_unit' => $exitCost / $quantity,
            'total' => $entryCost + $exitCost,
            'buy_vwap' => $buy['vwap'],
            'sell_vwap' => $sell['vwap'],
            'base_quantity' => $quantity,
            'buy_quote' => $buy['quote'],
            'sell_quote' => $sell['quote'],
            'reference_mid' => $mid,
        ];
    }

    /**
     * Apply the current order-book snapshot to a candidate without double-counting
     * entry spread/impact: buy_vwap is already the estimated entry execution price.
     * The remaining modeled costs are the entry fee plus the estimated exit cost.
     *
     * @param array{entry:float,stop:float,target1:float} $candidate
     * @param array<string,mixed> $executionCosts
     * @return array<string,float>
     */
    public function netRiskReward(array $candidate, array $executionCosts): array
    {
        $quantity = (float) ($executionCosts['base_quantity'] ?? 0.0);
        if (! ($executionCosts['available'] ?? false)
            || $quantity <= 0
            || ! isset($executionCosts['buy_vwap'], $executionCosts['entry_fee'], $executionCosts['exit_cost_per_unit'])) {
            throw new \InvalidArgumentException('A complete order-book execution estimate is required.');
        }

        $estimatedEntry = max((float) $candidate['entry'], (float) $executionCosts['buy_vwap']);
        $risk = $estimatedEntry - (float) $candidate['stop'];
        $reward = (float) $candidate['target1'] - $estimatedEntry;
        $entryFeePerUnit = (float) $executionCosts['entry_fee'] / $quantity;
        $exitCostPerUnit = (float) $executionCosts['exit_cost_per_unit'];
        $roundTripCostPerUnit = $entryFeePerUnit + $exitCostPerUnit;
        $netReward = $reward - $roundTripCostPerUnit;
        $netRisk = $risk + $roundTripCostPerUnit;

        return [
            'estimated_entry' => $estimatedEntry,
            'entry_fee_per_unit' => $entryFeePerUnit,
            'round_trip_cost_per_unit' => $roundTripCostPerUnit,
            'net_target1_reward_per_unit' => $netReward,
            'net_stop_risk_per_unit' => $netRisk,
            'net_risk_reward' => $netRisk > 0 ? $netReward / $netRisk : 0.0,
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
                return ['quantity' => $quantity, 'quote' => $quote, 'vwap' => $quote / $quantity];
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
