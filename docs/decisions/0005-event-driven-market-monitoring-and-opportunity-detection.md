# ADR 0005: Event-Driven Market Monitoring and Evidence-Gated Opportunity Detection

- Status: Accepted for Phase 0 design
- Date: 2026-11-09

## Context

The MVP has approved Binance Spot as its technical reference venue, USDT-only Spot instruments, a two-cohort top-liquid universe, regular scalping, and long-only opportunities. The platform needs a way to move from broad market observation to a limited set of evidence-backed candidates without analyzing every event with an AI model or turning isolated indicator events into buy signals.

Official Binance Spot streams provide aggregate trades, best bid/ask, klines, and diff-depth updates. A valid local order book requires snapshot/delta synchronization and must be invalidated and rebuilt after sequence gaps. Exchange stream limits and finite connection lifetimes also require bounded subscriptions, sharding, and planned recovery.

Research on order flow and order-book dynamics supports investigating trade-flow and depth-derived features, but findings from other markets, venues, or derivatives do not establish a profitable Binance Spot strategy. The project must validate features on its own captured data.

## Decision

1. Adopt a **two-tier event-driven monitoring topology**:
   - Universe Watchlist: broad, lightweight observation (initial planning range 75–150 symbols).
   - Active Analysis Set: deeper order-book, flow, structure, and scenario analysis (initial planning range 30–50 symbols).
   Both ranges are capacity hypotheses, not final limits.
2. Use Binance Spot public REST for metadata, historical backfill where available, depth snapshots, and recovery; use WebSocket streams for continuous trades, best bid/ask, and depth deltas.
3. Maintain a normalized, timestamped, provenance-preserving event envelope. Keep venue event time, local receive time, and internal processing times distinct.
4. Maintain a local depth book only when snapshot/delta sequence synchronization is valid. On a sequence gap, stale book, or failed recovery, disable depth-dependent decisions until resynchronized.
5. Build deterministic rolling features for price, volatility, trade flow, spread, depth, book changes, and market structure. Keep feature definitions, units, windows, and versions explicit.
6. Use cheap, adaptive event triggers to create candidate evaluations. A trigger is never a trade signal.
7. Use an orchestrated opportunity pipeline: data gates → market regime/context → candidate trigger → scenario hypothesis → evidence/confirmation → optional bounded AI review → deterministic entry/risk decision → immutable persistence → entitlement-aware distribution → event-driven monitoring.
8. AI is optional and bounded. It may summarize/compare structured evidence and identify contradictions; it cannot invent data, change prices or risk limits, bypass hard gates, directly transition states, or execute orders.
9. Separate candidate lifecycle from published trade lifecycle. Candidate states include DETECTED, WATCHING, CONFIRMED, ENTERABLE, INVALIDATED, EXPIRED, REJECTED, and MISSED.
10. Require every tradable plan to include entry condition/zone, maximum entry distance, stop/invalidation, targets/exit logic, cost assumptions, validity window, data-health context, and immutable version identifiers.
11. Validate scenario families and features with event replay, chronological walk-forward/out-of-sample testing, realistic bid/ask fills, fees, spread, slippage, market impact, latency, and paper/shadow trading.
12. Do not assume historical order-book data can be fetched retroactively. Capture depth snapshots/deltas prospectively if book-based replay is required.
13. Do not launch subscriber display or derived-signal distribution until commercial data rights and jurisdiction eligibility are confirmed in writing.

## Consequences

- The broad Watchlist can detect emerging candidates without paying the full analysis cost for every symbol.
- The Active Analysis Set concentrates order-book capture and compute on symbols with better liquidity and stronger evidence.
- Monitoring and candidate detection remain deterministic and replayable; AI is not on the market-event hot path.
- Data quality and book synchronization become explicit prerequisites for depth-dependent features.
- More storage and engineering effort are required to capture and replay market events, especially depth updates.
- Historical performance claims are limited by the data actually captured; missing historical depth cannot be reconstructed from present-day snapshots.
- Cohort capacities, feature windows, trigger thresholds, scenario weights, and AI call frequency remain subject to empirical validation.
- Phase 0 remains documentation-only until its full exit checklist and implementation stack are approved.

## Alternatives considered

- **Analyze every Watchlist symbol deeply:** rejected for unnecessary stream, storage, compute, and AI cost.
- **Trigger BUY from a single indicator or order-book imbalance:** rejected because isolated signals are noisy, can be transient, and omit execution and structural context.
- **AI call on every tick/candle:** rejected due to latency, cost, nondeterminism, and poor operational boundaries.
- **Candle-only scalping validation:** rejected as insufficient for execution-sensitive decisions; it can be a coarse research baseline only.
- **Use current REST snapshots as historical order-book data:** rejected because they do not reproduce past book states.

## References

- Binance Spot WebSocket Streams: https://github.com/binance/binance-spot-api-docs/blob/master/web-socket-streams.md
- Binance market-data-only endpoints: https://developers.binance.me/en/docs/products/spot/faqs/market_data_only
- Kolm, Turiel & Westray (2023): https://doi.org/10.1111/mafi.12413
- Albers et al. (2022): https://doi.org/10.1080/1350486X.2022.2080083
- Anastasopoulos et al. (2026): https://doi.org/10.1016/j.finmar.2026.101047
- Alexander et al. (2026): https://doi.org/10.1016/j.jbankfin.2026.107849
