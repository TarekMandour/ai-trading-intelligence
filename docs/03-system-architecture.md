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
