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

- ICT-01 now requires an ordered sequence: a previously confirmed SSL is swept and reclaimed, a later bullish displacement closes through a previously confirmed swing high (MSS), and a subsequent three-candle bullish FVG is identified. This sequence is deterministic but still needs broad replay fixtures and historical validation.
- Optional confluence evidence now includes equal-level candidates, bullish OTE range, rejection-block candidates, breaker-block retest candidates, and session-window availability. These are evidence only and do not independently authorize trades.
- Silver Bullet/Judas session windows remain inactive until explicit windows and a timezone are configured. SMT remains unavailable until a synchronized reference asset and approved comparison rule are supplied.
- Equal-level clustering, breaker/rejection, and OTE heuristics require review and calibration; they are not yet validated strategy gates.
- Basic post-creation FVG partial/full fill and OB mitigation/invalidation are replayed across subsequent setup and entry candles; FVG full-fill and invalidation are terminal states. A full chronological event-state machine across all model families and replay fixtures remain outstanding.
- ICT target selection now prefers the nearest two confirmed opposing swing highs above entry as BSL references; if fewer than two are available, R-multiple fallback targets are explicitly labeled in the candidate snapshot.
- Immediate Market entry is blocked when the reference price is outside the configured distance from a valid entry zone. A Limit plan can remain available as a conditional research plan; a candle touch is not treated as a fill.
- Added a GitHub Actions PHP 8.3 workflow for pull-request testing. Its first run has not yet been observed.
- Market/Limit are represented as research entry plans with Ask-side execution reference, zone-distance guard, expiry, and explicit touch-is-not-fill semantics; no order placement is implemented or authorized.
- Historical fill accuracy cannot be inferred from OHLCV alone.
- No historical backtest or profitability validation has been performed.
