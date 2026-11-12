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

- ICT-01 now requires an ordered sequence: a previously confirmed SSL is swept and reclaimed, a later bullish displacement exceeds the configured true-range ATR multiple and closes through a previously confirmed swing high (MSS), and a subsequent three-candle bullish FVG is identified. This sequence is deterministic but still needs broad replay fixtures and historical validation.
- Optional confluence evidence now includes equal-level candidates, bullish OTE range, rejection-block candidates, breaker-block retest candidates, and session-window availability. These are evidence only and do not independently authorize trades.
- Silver Bullet/Judas scenarios are implemented but remain inactive until explicit windows and a timezone are configured. Windows can be supplied as JSON through `ICT_SESSION_WINDOWS`; midnight-crossing windows are supported.
- SMT bullish swing-low divergence evidence is implemented for synchronized, same-timeframe reference candles. The queued workflow does not fetch a reference asset yet, so SMT remains `NOT_CONFIGURED` unless the caller supplies that optional series. Positive correlation is assumed and explicitly reported.
- Equal-level clustering, breaker/rejection, and OTE heuristics require review and calibration; they are not yet validated strategy gates.
- Basic post-creation FVG partial/full fill and OB mitigation/invalidation are replayed across subsequent setup and entry candles; FVG full-fill and invalidation are terminal states. A full chronological event-state machine across all model families and replay fixtures remain outstanding.
- ICT target selection now prefers the nearest two confirmed opposing swing highs above entry as BSL references; if fewer than two are available, R-multiple fallback targets are explicitly labeled in the candidate snapshot.
- Immediate Market entry is blocked when the reference price is outside the configured distance from a valid entry zone. A Limit plan can remain available as a conditional research plan; a candle touch is not treated as a fill.
- Added a GitHub Actions PHP 8.3 workflow for pull-request testing. Its first run has not yet been observed.
- Market/Limit are represented as research entry plans with Ask-side execution reference, zone-distance guard, expiry, and explicit touch-is-not-fill semantics; no order placement is implemented or authorized.
- Historical fill accuracy cannot be inferred from OHLCV alone.
- Added `IctHistoricalBacktestService` for chronological OHLCV replay, Market next-bar-open and modeled Limit touch fills, conservative stop-first same-bar handling, split target exits, fee/slippage assumptions, and trade metrics.
- The backtest service accepts caller-supplied multi-timeframe historical candle arrays; historical data download, persisted backtest runs, out-of-sample partitioning, and strategy parameter optimization are not implemented.
- Limit touch fills are assumptions, not queue-confirmed fills. No historical order-book depth is used, and no profitability conclusion is valid until a realistic data set and out-of-sample/paper validation are completed.
