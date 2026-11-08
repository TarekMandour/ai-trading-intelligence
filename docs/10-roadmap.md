# Roadmap

## Phase 0 — Project Foundation (current)
- Establish repository rules and source-of-truth documentation.
- Approve product scope and MVP market.
- Approve trading styles and timeframe approach.
- Select data-provider evaluation criteria and candidate providers.
- Approve signal lifecycle, risk policy, and subscription entitlement model.
- Decide initial technology/deployment architecture.
- Define validation and release gates.

**Exit:** user reviews and approves the Phase 0 decision set. No production code before exit.

## Phase 1 — Data and platform foundation
Subject to approval: implement instrument registry, provider adapters, normalized data contracts, storage, data-quality monitoring, and operational observability.

## Phase 2 — Deterministic analysis foundation
Implement market context, features, scenario definitions, trade-plan calculations, risk gates, and reproducible test harnesses.

## Phase 3 — Opportunity lifecycle and AI reasoning
Add bounded AI synthesis/review, structured output validation, immutable signal registry, lifecycle monitoring, and abstention handling.

## Phase 4 — Subscriber SaaS and distribution
Implement identity/tenancy, plans, entitlements, notification channels, billing integration if approved, delivery audit, and subscriber experience.

## Phase 5 — Validation and controlled launch
Backtest, walk-forward, paper/shadow trade, controlled release, cost monitoring, and operational readiness.

## Roadmap policy
Phases may be reordered only through an explicit decision record. Do not treat dates or estimates as committed until scope and dependencies are known.


## MVP scope update — Crypto Scalping
The first release is scoped to crypto regular scalping. The proposed timeframe stack is 1H context, 15m structure, 5m setup/confirmation, and 1m entry timing. Venue, spot/perpetual product, instrument universe, and final timeframe rules remain approval gates.

### Updated Phase 0 exit checklist
- [x] Select crypto as the first market.
- [x] Select regular scalping as the first trading style.
- [ ] Decide spot vs perpetual derivatives.
- [ ] Select initial exchange/venue and confirm commercial data rights.
- [ ] Approve symbol universe and direction policy.
- [ ] Validate timeframe roles and latency/freshness targets.
- [ ] Define transaction-cost and liquidity gates.
- [ ] Approve lifecycle, subscription entitlements, stack, and deployment plan.


## Phase 0 design completion — monitoring and opportunity detection

The following design artifacts are now recorded:
- docs/14-market-monitoring-and-data-pipeline.md
- docs/15-opportunity-detection-and-decision-pipeline.md
- ADR 0005: event-driven market monitoring and evidence-gated opportunity detection.

This completes the current design pass for the monitoring-to-candidate-to-opportunity workflow. It does not complete all of Phase 0 and does not authorize production code. Remaining Phase 0 work includes final technology/deployment decisions, exact operational/retention budgets, legal/commercial data rights, jurisdiction review, final timeframe/holding-time definitions, and the remaining subscription/entitlement and release criteria.

Phase 1 can begin only after the user explicitly approves the complete Phase 0 exit gate. Phase 1 should first implement data contracts, provider adapter, instrument registry, stream health, event capture/replay, and deterministic data-quality gates before any AI opportunity analyst.
