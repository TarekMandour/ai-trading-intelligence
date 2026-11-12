# ICT Comprehensive Implementation

Status: Initial implementation slice on feature/ict-comprehensive. Thresholds are research hypotheses and are not validated trading parameters.

## Approved operating constraints

- Binance Spot USDT pairs.
- Long-only; no short entries, margin, leverage, or automated order execution.
- Scalping, Intraday, Swing profiles retain the existing timeframe map.
- Analyze 24 hours; no general kill-zone filter.
- Market and Limit are supported as planned entry methods; this application records research plans and does not submit exchange orders.
- Deterministic engine is authoritative; AI remains advisory.

## Engine contract

App\Services\Analysis\IctComprehensiveEngine::evaluate(array $candlesByRole, string $profile): array

Required roles: context, structure, setup, entry. Every series requires at least 30 strictly time-ordered, completed OHLCV candles with valid high/low geometry.

The engine returns context, setup evidence, entry evidence, three core scenario results, deterministic decisions, and research-only Market/Limit plans with distance and expiry metadata. No exchange orders are submitted.

## Core models

- ICT-01 Sell-Side Liquidity Sweep Reversal.
- ICT-02 Bullish FVG Continuation.
- ICT-03 Bullish Order Block Reaction.

The first implementation uses deterministic pivot, liquidity, displacement, MSS, FVG, and OB heuristics. These are explicit baselines for review and calibration, not universal definitions of ICT and not claims of profitability.

## Limitations

- Current setup evidence uses the latest completed setup candle for some confirmations; production-quality event sequencing, evidence persistence, and replay-safe lifecycle transitions still require integration work.
- Optional confluence evidence now includes equal-level candidates, bullish OTE range, rejection-block candidates, breaker-block retest candidates, and session-window availability. These are evidence only and do not independently authorize trades.
- Silver Bullet/Judas session windows remain inactive until explicit windows and a timezone are configured. SMT remains unavailable until a synchronized reference asset and approved comparison rule are supplied.
- Equal-level clustering, breaker/rejection, and OTE heuristics require review and calibration; they are not yet validated strategy gates.
- Multi-candle displacement, complete zone touch/mitigation lifecycle, FVG partial/full fill lifecycle, and robust chronological event replay remain outstanding.
- Market/Limit are represented as research entry plans with Ask-side execution reference, zone-distance guard, expiry, and explicit touch-is-not-fill semantics; no order placement is implemented or authorized.
- Historical fill accuracy cannot be inferred from OHLCV alone.
- No historical backtest or profitability validation has been performed.
