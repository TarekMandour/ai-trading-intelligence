> **Scope note (2026-11-09):** This document describes a future continuous/event-driven monitoring architecture. It is explicitly deferred from the current local manual-analysis MVP. The first implementation uses Binance REST requests on demand and a Laravel database queue; it does not build persistent WebSocket streams, watchlist/active-set daemons, or continuous order-book reconstruction. See docs/17-local-mvp-implementation-plan.md and ADR 0006.


# Market Monitoring and Data Pipeline

Status: Phase 0 design approved for the MVP workflow. Numeric trigger thresholds, capture retention, and operational capacity remain pilot parameters requiring measurement. This document does not authorize production implementation or paid redistribution of Binance data.

## 1. Purpose

Continuously observe the approved Binance Spot USDT universe, detect meaningful changes in market conditions, and route only promising candidates into deeper analysis. Monitoring is not itself a trading strategy and a market event is not a buy signal.

The system must distinguish:
- market-data health from market opportunity;
- broad symbol monitoring from intensive analysis;
- a candidate trigger from a confirmed setup;
- a confirmed setup from an executable trade plan;
- an estimated entry price from a guaranteed fill.

The platform is long-only Spot for the MVP. It publishes analysis and alerts; subscribers execute manually.

## 2. Two-tier monitoring topology

### 2.1 Universe Watchlist — lightweight monitoring

Initial capacity hypothesis: 75–150 symbols, subject to stream and compute testing.

Purpose:
- detect emerging activity, volatility, price structure changes, and liquidity deterioration;
- maintain a recent rolling context for promotion decisions;
- identify new candidates that may enter the Active Analysis Set without running the full analysis pipeline on every symbol.

Recommended inputs:
- aggregate trades (aggTrade) for signed taker-flow estimates and activity;
- best bid/ask (bookTicker) for spread and quote freshness;
- 1-minute candles or a deterministic 1-minute candle builder;
- instrument metadata and exchange status;
- periodic lightweight liquidity metrics.

Do not maintain full local depth books for every Watchlist symbol by default. Depth subscriptions and expensive feature calculations are reserved for the Active Analysis Set or a candidate promotion probe, subject to measured capacity.

### 2.2 Active Analysis Set — intensive monitoring

Initial capacity hypothesis: 30–50 symbols, promoted from the Watchlist.

Purpose:
- maintain synchronized local order books;
- compute richer trade-flow, liquidity, volatility, and structure features;
- evaluate scenario families and multi-timeframe context;
- monitor candidate confirmation, entry freshness, and published opportunity lifecycle.

Recommended inputs:
- aggTrade;
- bookTicker;
- diff-depth stream, initially test 100ms and 1000ms modes under actual load;
- 1-minute candles, with 5m, 15m, and 1h candles derived deterministically or obtained through validated candle streams;
- instrument metadata and venue status.

A symbol may be promoted temporarily for a bounded candidate investigation, but promotion must obey stream limits, queue capacity, and a promotion cooldown. A candidate must not cause unbounded subscriptions.

### 2.3 Cohort transitions

- Watchlist → Active: all hard eligibility gates pass, data health is acceptable, and ranking/trigger evidence justifies intensive analysis.
- Active → Watchlist: no longer ranks within capacity or has no active scenario requiring intensive monitoring; retain lightweight observation.
- Any cohort → Quarantined: hard safety/data failure, symbol status change, stale feed, invalid local book, or severe execution-cost deterioration.
- Quarantined → Watchlist: only after explicit health recovery, fresh metadata, valid data, and a configurable stable observation period.

A hard exclusion overrides dwell periods. Persist cohort version, transition time, reason codes, and metric snapshot.

## 3. Market-data ingestion contract

### 3.1 Source streams and REST responsibilities

Use official Binance Spot public market-data interfaces only:
- REST exchange information for symbol status, filters, tick size, quantity step, and minimum notional.
- REST klines/trades/aggregate trades for initial history and bounded backfill where supported.
- REST depth snapshot to initialize or rebuild a local order book.
- WebSocket aggTrade for aggregate taker executions.
- WebSocket bookTicker for best bid/ask.
- WebSocket diff-depth for incremental local order-book reconstruction.
- WebSocket kline only if selected instead of a deterministic candle builder; never combine duplicate candle sources without reconciliation.

REST is not a substitute for the live stream. Public market-data-only endpoints may be available without an API key, but endpoint access does not establish commercial redistribution rights.

### 3.2 Canonical event envelope

Every normalized event should carry:
- venue = Binance Spot;
- symbol and normalized instrument identifier;
- event type and source stream;
- venue event timestamp and trade timestamp where supplied;
- local receive timestamp;
- normalization/processing timestamp;
- source event/trade/update identifier;
- connection/session identifier;
- payload schema/version;
- raw payload reference or controlled raw capture pointer;
- data-quality status;
- deduplication key.

Use UTC internally. Preserve source timestamps exactly and do not overwrite them with receive time. Use monotonic local clocks for elapsed processing measurements, while retaining wall-clock timestamps for audit. Monitor clock offset against a trusted time source and Binance server time.

### 3.3 Normalization and ordering

- Parse exchange decimal strings using decimal-safe representations; do not use binary floating-point for price/quantity equality, tick rounding, or order-book keys.
- Normalize symbols, quantities, quote notional, timestamps, and exchange filters.
- Deduplicate using venue identifiers and deterministic event keys.
- Preserve out-of-order arrival information; do not silently reorder based only on local receive time.
- Track event time, receive time, and processing time separately.
- Reject malformed events and quarantine affected symbol state when the missing data is material.
- Keep provider-specific payloads inside the adapter boundary.

## 4. Local order-book reconstruction

A depth-dependent feature is valid only when the local book is synchronized.

Required bootstrap/recovery procedure:
1. Open the diff-depth stream and buffer incoming events.
2. Request a REST depth snapshot.
3. If the snapshot update ID is older than the first buffered event's first update ID, request a fresh snapshot.
4. Discard buffered events already covered by the snapshot.
5. Apply the first bridging event and subsequent events only when update IDs are continuous according to Binance's documented procedure.
6. Update price levels using absolute quantities; remove a level when its quantity is zero.
7. Track the latest applied update ID and the age of the most recent update.
8. If a sequence gap is detected, invalidate the local book immediately, stop depth-dependent decisions, discard the book, and restart snapshot/delta synchronization.

The implementation must not keep using a stale or partially reconstructed book after a gap. A successful WebSocket reconnect does not by itself mean the book is synchronized.

Important limitations:
- REST snapshots are bounded in depth; levels outside the snapshot may be unknown until updated.
- Partial-depth streams are not a replacement for a sequence-correct diff-depth local book when deeper executable liquidity is required.
- Displayed liquidity can be cancelled and does not guarantee execution. Measure replenishment, depletion, and cancellation dynamics rather than treating visible orders as firm intent.
- Historical depth cannot be assumed to be retrievable retroactively. Begin capturing the required snapshots/deltas prospectively if historical book replay is needed.

## 5. Candle and rolling-window construction

The canonical timeframe stack remains a testable hypothesis:
- 1H: regime and directional context;
- 15m: market structure and key levels;
- 5m: setup development and confirmation;
- 1m: entry timing and immediate monitoring.

Build 5m, 15m, and 1h candles from validated 1m bars or trades using exchange-aligned boundaries. The source and aggregation method must be versioned. Each candle must include:
- open time and close time;
- OHLCV and quote volume;
- trade count where available;
- taker buy/sell volume where reliably derived;
- source provenance;
- complete/forming flag;
- gap/completeness status.

Never treat a forming candle as a completed candle. A candle correction or late event must create a traceable correction/version, not silently rewrite a decision snapshot.

Suggested rolling windows for research, not final thresholds:
- microstructure: 1s, 5s, 15s, 30s, 1m;
- activity/volatility: 1m, 5m, 15m;
- structure/context: 15m, 1h, optionally 4h as a research comparison.

Window lengths and update cadence must be selected by out-of-sample validation, not by optimizing a single historical period.

## 6. Feature families

### 6.1 Price and return behavior
- mid-price and microprice changes;
- log returns over multiple windows;
- realized volatility and volatility acceleration;
- range expansion/contraction;
- gap and jump flags;
- distance to recent swing levels and session/range extremes.

### 6.2 Trade flow
- buyer-initiated and seller-initiated quote volume;
- signed trade-flow imbalance;
- trade count and notional rate;
- trade-size distribution and burstiness;
- inter-trade duration;
- price response per unit of signed flow.

For Binance aggTrade, interpret the maker-side flag correctly: when the buyer is the maker, the seller was the aggressor; otherwise the buyer was the aggressor. Validate field semantics against official stream documentation and test fixtures.

### 6.3 Order-book and execution
- best bid/ask spread in basis points;
- executable ask depth for a buy and bid depth for a sell at defined price bands;
- depth imbalance at multiple bands, not only top-of-book;
- order-flow imbalance from changes to bid/ask price levels;
- microprice deviation from mid-price;
- depth replenishment/depletion rates;
- cancellation/replacement intensity where inferable;
- estimated market impact and slippage at the reference notional and configurable scenarios.

Do not interpret one-sided depth or a single imbalance snapshot as directional certainty. Combine persistence, trades, price response, spread, and volatility context.

### 6.4 Structure and participation
- swing highs/lows and market structure state;
- breakout distance and retest behavior;
- relative volume and quote-volume acceleration;
- price-volume divergence;
- volatility compression/expansion;
- distance to invalidation and nearby resistance/supply zones.

Feature definitions must be deterministic, versioned, and computable from data available at the decision timestamp.

## 7. Market event detection and candidate triggers

The Watchlist layer uses cheap event triggers to identify symbols worth promoting. Examples for research:
- unusual but persistent quote-volume/trade-rate acceleration;
- volatility expansion after a compression regime;
- price approaching or crossing a previously identified structure level;
- sustained positive signed trade flow accompanied by favorable price response;
- meaningful change in spread/depth or order-book replenishment;
- breakout attempt followed by a retest or failed breakout;
- rapid liquidity deterioration that should suppress, not promote, new entries.

A trigger only creates a Candidate Evaluation event. It must not publish a BUY signal. Trigger thresholds should be adaptive to each symbol's rolling distribution and volatility regime, with minimum persistence and cooldowns to reduce noise. Hard risk/data exclusions are not cooldown-controlled.

Use event deduplication keys such as symbol + scenario family + structural level + observation window. A repeated trigger updates the existing candidate rather than spawning unlimited duplicate candidates.

## 8. Candidate evaluation and workload control

- Evaluate only on material events: candle close, structure break, sustained flow shift, liquidity transition, or a scheduled context refresh.
- Do not call an AI model on every tick, trade, depth update, or candle.
- Cache higher-timeframe context and update it on candle completion or material regime transition.
- Use bounded per-symbol queues, coalescing, priority classes, and backpressure.
- If the candidate queue is delayed beyond its validity window, mark the candidate stale/expired rather than processing it as current.
- Prioritize Active Analysis symbols with fresh data and valid books; suppress depth-dependent evaluation for degraded symbols.
- Track candidate queue age, dropped/coalesced event count, and processing lag.

## 9. Reliability, limits, and recovery

- Respect current Binance limits for incoming WebSocket messages, stream subscriptions, connection attempts, REST request weights, and endpoint-specific quotas.
- A Spot market-data WebSocket connection has a finite lifetime; rotate connections proactively with overlap and controlled subscription handoff.
- Implement heartbeat/ping-pong handling, reconnect with exponential backoff and jitter, subscription reconciliation, and snapshot resynchronization.
- Shard subscriptions by measured message rate and symbol priority, not just by symbol count.
- Avoid subscription storms after an outage; stagger re-subscription and prioritize critical streams.
- Use circuit breakers for REST backfill and depth snapshot requests.
- If a feed is degraded, fail closed for new opportunities while continuing to record health and lifecycle warnings.
- Test server shutdown events, network partitions, duplicate events, delayed events, sequence gaps, rate-limit responses, and process restarts.

## 10. Monitoring and operational metrics

Per connection:
- connected duration, reconnect count, ping/pong health, subscription count, inbound message rate, parse failures, queue depth.

Per symbol:
- event receive lag p50/p95/p99;
- book age and sequence continuity;
- best bid/ask age and spread;
- candle completeness and gap count;
- trade-rate and volume-rate distributions;
- data-health state and reason;
- candidate count and candidate age.

Pipeline:
- event-to-feature latency;
- candidate-to-evaluation latency;
- evaluation-to-decision latency;
- decision-to-publish latency;
- stale candidate rejection rate;
- dropped/coalesced events;
- CPU, memory, storage, and network utilization.

Do not report a single average latency as proof of suitability. Separate venue event-to-receive lag from internal processing latency.

## 11. Retention, replay, and licensing

- Store normalized candles and required market events with provenance and schema versions.
- Retain raw depth events only under an explicit storage/retention budget and verified legal rights.
- Capture order-book snapshots/deltas prospectively for the replay horizons required by validation.
- Provide deterministic replay with the original event ordering, timestamps, data-quality flags, and configuration.
- Do not use live data as a substitute for historical data in backtests.
- Do not redistribute raw Binance data or derived analytics to subscribers until written commercial permissions, retention rights, caching rights, attribution, downstream processor rights, and jurisdiction eligibility are confirmed.

## 12. Acceptance criteria for this design

The later implementation is acceptable only when:
- both cohorts can be managed with bounded subscriptions and queues;
- normalized events preserve provenance and timestamps;
- duplicate and out-of-order events are handled deterministically;
- a local book becomes valid only after documented snapshot/delta synchronization;
- sequence gaps and stale feeds immediately disable dependent decisions;
- candles distinguish forming from closed states and pass gap checks;
- event triggers create candidates, never direct trade signals;
- replay reproduces feature and candidate decisions from recorded inputs;
- latency and feed-health metrics are available per symbol and connection;
- no AI call is required on every market event;
- commercial redistribution remains disabled until licensing approval.

## 13. References

- Binance Spot WebSocket Streams: https://github.com/binance/binance-spot-api-docs/blob/master/web-socket-streams.md
- Binance Spot REST API: https://github.com/binance/binance-spot-api-docs/blob/master/rest-api.md
- Binance market-data-only endpoints: https://developers.binance.me/en/docs/products/spot/faqs/market_data_only
- Kolm, Turiel & Westray (2023), Deep order flow imbalance: https://doi.org/10.1111/mafi.12413
- Albers et al. (2022), Fragmentation, Price Formation and Cross-Impact in Bitcoin Markets: https://doi.org/10.1080/1350486X.2022.2080083
- Anastasopoulos et al. (2026), Order flow and cryptocurrency returns: https://doi.org/10.1016/j.finmar.2026.101047
- Alexander et al. (2026), High-frequency price discovery in fragmented crypto markets: https://doi.org/10.1016/j.jbankfin.2026.107849

Research findings are context, not proof that a feature will be profitable on Binance Spot or under this product's fee, latency, and execution conditions. Validate all features on the project's own captured data.
