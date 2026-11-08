# Product Requirements (PRD)

## 1. Scope
The MVP is a centrally operated opportunity discovery, decision, monitoring, and subscriber-distribution service. Exact first market and asset universe are not yet selected.

## 2. Primary user stories
- As a subscriber, I receive opportunities allowed by my active plan.
- As a subscriber, I can inspect the reason, entry, stop/invalidation, targets, expiry, and current state.
- As a subscriber, I receive meaningful updates when a scenario confirms, changes, invalidates, expires, reaches a target, or closes.
- As an operator, I can inspect all generated opportunities, source data health, model decisions, and delivery outcomes.
- As an operator, I can disable a market, data source, analysis version, or distribution channel safely.
- As a platform, I analyze each opportunity centrally and distribute the stored result to eligible subscribers without repeating AI analysis per recipient.

## 3. Functional requirements
### Market data
- Ingest supported instruments and timeframes from approved providers.
- Normalize timestamps, symbols, sessions, price precision, corporate actions where relevant, and provider identifiers.
- Track source timestamp, receive timestamp, completeness, latency, and quality status.
- Reject or quarantine stale, malformed, incomplete, or contradictory data.

### Opportunity discovery
- Detect market regime and multi-timeframe context.
- Analyze price structure, volume, volatility, key levels, and liquidity when available.
- Evaluate a defined set of scenario families.
- Support abstention and explicit rejection reasons.
- Produce structured evidence, not only free-form narrative.

### Trade plan
- Provide entry zone, entry timing, stop/invalidation, targets/exit logic, max entry distance, and expiry.
- Apply deterministic risk and execution gates.
- Do not publish a trade plan with missing critical fields.

### Lifecycle and monitoring
- Track detected, watching, confirmed, active, invalidated, expired, and closed states as defined in the lifecycle specification.
- Monitor active scenarios and emit auditable events.
- Preserve initial and subsequent snapshots.

### Subscriptions and distribution
- Maintain subscribers, plans, entitlements, preferences, and channel permissions.
- Resolve eligibility at distribution time and record the entitlement decision.
- Deduplicate notifications and support retries without duplicate user-visible messages.
- Keep delivery separate from analysis.

### Operations and reporting
- Provide observability for data freshness, analysis runs, failures, AI usage, notification delivery, and costs.
- Report performance by market, scenario, timeframe, and analysis version using validated methodology.
- Separate live results from backtest and paper-trading results.

## 4. Non-functional requirements
- Tenant isolation and least-privilege access.
- Secure secret management and encrypted transport.
- Idempotent ingestion and notification jobs.
- Auditable decision history.
- Resilience to provider outages and rate limits.
- Configurable latency and freshness service-level objectives after market selection.
- Cost controls, quotas, and usage monitoring for model calls.
- Extensible market/provider adapters without leaking provider-specific formats into domain logic.

## 5. Open decisions
- First market and jurisdiction
- Asset universe and exclusions
- Trading styles and timeframes
- Data provider(s), historical depth, real-time availability, licensing
- Signal categories and plan entitlements
- Delivery channels
- Initial technology stack and deployment model
- Regulatory and legal review requirements for target jurisdictions

## 6. MVP acceptance principles
A signal is not considered publishable unless its data is fresh and complete, its scenario is confirmed according to explicit rules, its trade plan passes risk gates, and its audit snapshot is persisted. Exact numeric thresholds require market-specific approval.
