# System Architecture (Proposed)

## 1. Architectural style
A modular, event-driven platform with a centralized analysis pipeline and independent subscriber distribution. Start with a modular monolith or a small number of deployable services unless measured scale or latency justifies service separation. Avoid premature microservices.

## 2. Logical components
1. **Market Data Gateway** — provider adapters, REST/WebSocket ingestion, normalization, validation, and source health.
2. **Market Data Store** — time-series/candle data and optional order-book/trade data, with retention and provenance.
3. **Market Context Engine** — regime, volatility, structure, session, and multi-timeframe context.
4. **Evidence Engines** — price action, volume, liquidity, levels, and scenario feature extraction.
5. **Opportunity Orchestrator** — coordinates analysis and scenario evaluation; records abstentions and rejection reasons.
6. **AI Reasoning Adapter** — centrally calls OpenAI API when contextual synthesis or structured review is useful; validates structured output. No per-subscriber calls.
7. **Decision & Risk Engine** — deterministic entry, invalidation, stop, targets, sizing/risk checks, freshness, and execution constraints.
8. **Signal Registry** — immutable opportunity snapshots, versions, evidence, and lifecycle events.
9. **Trade Monitor** — event-driven updates for active opportunities.
10. **Subscription & Entitlement Engine** — plan rules and subscriber eligibility.
11. **Notification Dispatcher** — channel adapters, templates, retries, deduplication, delivery records.
12. **Admin & Analytics** — operational controls, audit, usage/cost, and performance reporting.

## 3. Data flow
Provider feeds → normalize/validate → persist market data → update market context/features → detect candidate → evaluate scenario → optional AI synthesis → deterministic risk/decision gates → persist immutable signal snapshot → resolve subscriber entitlements → enqueue notifications → deliver and record outcomes → monitor signal lifecycle.

## 4. Centralized AI usage
- One server-side API integration per deployment/environment, protected as a secret.
- AI calls are made for analysis/review tasks, not once per recipient.
- Store model identifier, prompt/schema version, request purpose, token/usage metadata where available, latency, and validated output.
- Enforce timeouts, retries with limits, budgets, circuit breakers, and fallback behavior.
- AI output is untrusted input: schema-validate it and never let it bypass deterministic gates.
- Routine notification generation uses templates and stored signal fields.

## 5. Reliability and event processing
- Use idempotency keys for provider events, analysis jobs, lifecycle events, and notifications.
- Use an outbox/inbox or equivalent reliable event-publishing pattern.
- Separate queues by workload and priority.
- Apply backpressure and rate limits to providers and channels.
- On stale data or unavailable critical components, fail closed: suppress new signals and preserve monitoring warnings.

## 6. Multi-tenancy and security
- Tenant-scoped subscriber data and entitlements.
- Central market analysis data can be shared across tenants where licensing permits.
- Keep user preferences, payment details, and delivery endpoints isolated.
- Store secrets in environment/secret management, never in source control.
- Public repository must contain no credentials, private customer data, or licensed market data.

## 7. Deployment shape
Initial deployment topology and technology stack are intentionally undecided. Select after confirming market latency, data volume, operating budget, and team constraints.


## 8. Crypto scalping data path (MVP proposal)
For regular scalping, the architecture needs a persistent streaming path in addition to REST backfill:
- WebSocket market stream for trades and best bid/ask; order-book depth updates where available and justified.
- REST endpoints for instrument metadata, historical candle backfill, snapshots, and recovery after disconnects.
- Sequence-aware order-book reconstruction when the venue provides sequence numbers and snapshot/delta semantics.
- A stream health monitor for heartbeat, reconnect, sequence gaps, timestamp drift, and stale-feed detection.
- A candle builder or validated candle adapter for 1m, 5m, 15m, and 1h intervals, with explicit open/closed status.
- Event-driven feature updates and candidate evaluation; do not invoke a cloud AI model for every tick or candle.

The fast path should use deterministic code for freshness checks, price/volume features, spread/liquidity gates, entry distance, stop validity, and state transitions. AI is an optional bounded reasoning step and must never become a synchronous bottleneck for every market event.

## 9. Initial timeframe topology
Proposed: 1H context → 15m structure → 5m setup/confirmation → 1m entry timing. The topology is configurable and pending validation. Higher-timeframe context can be cached and updated on candle completion or material regime events; lower-timeframe monitoring is event-driven.

## 10. Venue and product isolation
Provider adapters must expose normalized domain events while retaining venue-specific provenance. Spot and perpetual contracts require separate instrument metadata and risk modules. If derivatives are later approved, funding, mark/index price, liquidation mechanics, contract multiplier, and leverage constraints must be modeled explicitly.

## 11. Spot-only MVP boundary
The initial domain model must represent spot instruments and long-only trade scenarios. Do not introduce futures contract multipliers, leverage, funding, mark/index price, margin, or liquidation components into the MVP domain. Keep provider adapters modular so a future derivatives product can be added without contaminating spot calculations.

The platform does not hold subscriber assets or place orders in the MVP. Execution prices are estimates/reference prices, not guaranteed fills. Entry/exit evaluation must account for the chosen reference venue's fees, spread, liquidity, and slippage assumptions.


## 12. Binance Spot reference integration and dynamic universe
- Implement Binance Spot behind a venue adapter; keep normalized domain events independent of Binance payload shapes.
- REST is used for exchange metadata, historical backfill, initial order-book snapshots, and recovery; WebSocket is the continuous real-time path for trades, book ticker, and depth updates.
- Treat Binance symbol filters and trading status as authoritative instrument constraints; normalize tick size, step size, min quantity/notional, and supported order types into instrument metadata.
- The `top_liquid` universe service consumes normalized market metrics and emits versioned membership changes. It must not be embedded in the strategy/AI analyst.
- Maintain a broader candidate monitor and a narrower intensive-analysis/monitoring set. Subscription to feeds must respect venue connection and stream limits, with sharded connections and controlled resubscription as needed.
- On depth sequence gaps, stale books, reconnects, or failed resynchronization, mark book quality invalid and suppress decisions that require depth until a fresh snapshot plus deltas is synchronized.
- Keep universe selection, feed-health gates, and execution-cost gates deterministic. AI may explain a candidate but cannot override exclusions or freshness gates.


## 13. Approved event-driven monitoring and opportunity pipeline

The MVP uses two market-monitoring cohorts: a broad lightweight Watchlist and a smaller intensive Active Analysis Set. The Watchlist detects emerging activity and liquidity changes; the Active set maintains synchronized local books and computes richer structure/flow features. Cohort transitions are versioned and reason-coded. Capacity ranges are planning hypotheses, not fixed production limits.

The processing path is:
1. Venue adapter ingestion and canonical event envelope.
2. Data normalization, deduplication, timestamp/provenance retention, and health validation.
3. Candle aggregation and rolling feature updates.
4. Lightweight event triggers and bounded candidate queue.
5. Market regime/context and scenario evaluation.
6. Structured evidence packet and optional bounded AI review.
7. Deterministic entry/risk gates.
8. Immutable candidate/signal persistence.
9. Entitlement resolution and notification outbox.
10. Event-driven lifecycle monitoring.

Use bounded per-symbol queues, event coalescing, backpressure, priority, and stale-candidate expiry. No AI call belongs on every tick/trade/depth event. A candidate trigger is not a signal.

Depth-dependent logic is enabled only for a sequence-synchronized local book. On a sequence gap, stale book, or resync failure, mark the book invalid and suppress dependent decisions. Historical depth replay requires prospective capture and explicit retention/licensing approval.

See docs/14 and docs/15 and ADR 0005 for full contracts, recovery behavior, feature families, state boundaries, and acceptance criteria.
