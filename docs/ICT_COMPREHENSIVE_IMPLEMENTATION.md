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

- ICT-01 now requires an ordered sequence: a previously confirmed SSL is swept and reclaimed, a later bullish displacement occurs within `ICT_SEQUENCE_MAX_BARS_AFTER_SWEEP`, closes through a previously confirmed swing high (MSS), and is the middle candle of the subsequent three-candle bullish FVG. The displacement metadata is taken from that same impulse candle.
- Bullish OB candidates are anchored to the last bearish candle immediately preceding the confirmed displacement, not any unrelated bearish candle near the latest bar. The OB becomes active only after the displacement candle closes.
- FVG and OB lifecycle replay records the most recent retest timestamp. Entry triggers must occur after the retest and within `ICT_MAX_ENTRY_BARS_AFTER_RETEST` entry candles; an old touch cannot authorize a late chase entry.
- ICT-02 and ICT-05 require the linked confirmed sweep/displacement/MSS/FVG sequence rather than combining an unrelated recent displacement with an arbitrary FVG.
- Optional confluence evidence now includes equal-level candidates, bullish OTE range, rejection-block candidates, breaker-block retest candidates, and session-window availability. These are evidence only and do not independently authorize trades.
- Silver Bullet/Judas scenarios are implemented but remain inactive until explicit windows and a timezone are configured. Windows can be supplied as JSON through `ICT_SESSION_WINDOWS`; midnight-crossing windows are supported.
- SMT bullish swing-low divergence evidence is implemented for synchronized, same-timeframe reference candles. The queued workflow can fetch an optional setup-timeframe reference symbol when `ICT_SMT_REFERENCE_SYMBOL` is configured; failures only add warnings and do not block core ICT analysis. Positive correlation is assumed and explicitly reported.
- Equal-level clustering, breaker/rejection, and OTE heuristics require review and calibration; they are not yet validated strategy gates.
- Basic post-creation FVG partial/full fill and OB mitigation/invalidation are replayed across subsequent setup and entry candles; FVG full-fill and invalidation are terminal states. A full chronological event-state machine across all model families and replay fixtures remain outstanding.
- ICT target selection now prefers the nearest two confirmed opposing swing highs above entry as BSL references; if fewer than two are available, R-multiple fallback targets are explicitly labeled in the candidate snapshot.
- Immediate Market entry is blocked when the reference price is outside the configured distance from a valid entry zone. A Limit plan can remain available as a conditional research plan; a candle touch is not treated as a fill.
- Added a GitHub Actions PHP 8.3 workflow for pull-request testing. Its first run has not yet been observed.
- Market/Limit are represented as research entry plans with Ask-side execution reference, zone-distance guard, expiry, and explicit touch-is-not-fill semantics; no order placement is implemented or authorized.
- Historical fill accuracy cannot be inferred from OHLCV alone. Stop-market gaps are modeled at the worse opening price when the market opens through the stop; same-bar stop/target ambiguity remains stop-first.
- Added `IctHistoricalBacktestService` for chronological OHLCV replay, Market next-bar-open and modeled Limit touch fills, conservative stop-first same-bar handling, split target exits, fee/slippage assumptions, and trade metrics.
- The backtest service accepts caller-supplied multi-timeframe historical candle arrays and supports a caller-selected chronological holdout via `test_start_index`, with a Buy-and-Hold benchmark over the same entry-timeframe window. This is a single holdout mechanism, not automated train/validation/test partitioning or walk-forward optimization. Historical data download and persisted backtest runs remain incomplete.
- Limit touch fills are assumptions, not queue-confirmed fills. No historical order-book depth is used, and no profitability conclusion is valid until a realistic data set and out-of-sample/paper validation are completed.


## Causal engine rebuild — v2 (2026-11-13)

A separate research branch introduces `ict-v2-causal`. The new pipeline is now called by the comprehensive engine and supplies the top-level structural trade plan; the seven earlier scenario evaluators remain in the payload as comparison/diagnostic outputs during migration.

### New deterministic components

- `IctSwingPointDetector`: strict left/right fractal pivots with origin index and later confirmation index/time. A pivot is not available until all configured right-side candles have closed.
- `IctMarketStructureAnalyzer`: chronological close-based BOS/CHoCH events. A level can only break after its pivot confirmation and is consumed once.
- `IctLiquidityAnalyzer`: equal-high/equal-low pools within a configurable basis-point tolerance, plus wick-through-and-close-back-inside sweeps. A sweep is only evaluated after the pool's pivots are confirmed.
- `IctDisplacementAnalyzer`: candle range versus prior ATR, body/range ratio, close location, and volume versus prior average volume. The impulse candle is excluded from its own baselines.
- `IctZoneQualityAnalyzer`: FVGs are formed around an identified displacement candle; OBs are anchored to the last opposing candle before that impulse. Quality scores combine impulse size, body ratio, relative volume, linked structure break, and a temporally linked SSL sweep. Zones are replayed through later candles and invalidated/fully-filled zones are excluded.
- `IctDealingRangeAnalyzer`: premium/discount is calculated from a confirmed directional swing leg, not a rolling arbitrary high/low.
- `IctStructuralTradePlanner`: entry requires an entry-timeframe candle that retests the zone, closes bullish, and closes above the prior entry candle high. Stop is below structural invalidation with an ATR buffer. Targets prefer unbroken opposing swing/liquidity levels and disclose R-multiple fallback. No order submission is enabled.

### Output and migration

The engine returns `structural_analysis`, `structural_trade_plan`, and `canonical_scenario` in addition to the legacy scenario diagnostics. The queued Binance workflow now considers the canonical structural candidate first. The historical replay also includes canonical structural candidates and still reports legacy scenario families independently.

### Known limitations — not strategy approval

- This first rebuild is long-only and deterministic; thresholds remain hypotheses.
- The pipeline detects strict fractal pivots; alternate pivot/market-structure definitions require separate sensitivity testing.
- The current OB/FVG quality score is a transparent heuristic, not a calibrated probability.
- The first structural plan confirms a bullish retest/reclaim on the entry timeframe; more nuanced trigger types (engulfing, micro BOS, reclaim of CE) remain future variants to compare.
- Higher-timeframe alignment and live order-book cost checks remain hard gates in the existing queued workflow.
- The OHLCV backtest still uses explicit fill assumptions and cannot reproduce queue priority, intrabar tick order, or historical order-book depth.
- EGX/Thndr now feeds the same causal ICT engine through a separate stored-candle adapter and chronological backtest endpoint. Real EGX approval still requires exchange-session validation, corporate-action adjustment, tick/lot/price-band handling, and verified broker fees/slippage.
- No profitability claim is made. Approval requires scenario-isolated train/validation/untouched test, walk-forward and sensitivity analysis, and prospective paper-trading evidence.
