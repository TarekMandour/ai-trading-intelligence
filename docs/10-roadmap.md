# Roadmap

## Phase 0 — Foundation and research
Completed as a design milestone for the local prototype:
- product scope and risk boundaries;
- Binance Spot / USDT technical data source;
- local Laravel/MySQL/manual-analysis decision;
- research evidence and scenario hypotheses;
- regime and multi-timeframe specification;
- local MVP implementation and test acceptance criteria.

Commercial data rights and legal/jurisdictional review remain blockers for any commercial redistribution or SaaS launch, not for treating the local prototype as a personal research tool subject to applicable terms.

## Phase 1 — Local Manual-Analysis MVP
Goal: produce a repeatable local analysis run and three independent reports.

1. Inspect repository and actual local runtime; verify PHP, Laravel, MySQL server/version, frontend conventions, and dependencies.
2. Build Binance public REST adapter and data-quality validation.
3. Build manual analysis-run workflow and database queue worker.
4. Build per-timeframe feature calculations and deterministic market-regime baseline.
5. Build multi-timeframe context/conflict reporting.
6. Implement a small number of transparent scenario baselines.
7. Build deterministic entry/risk/cost gates and trade-plan snapshots.
8. Build separate Scalping, Intraday, and Swing reports.
9. Add tests, setup instructions, sample run, and reproducibility notes.

Acceptance: docs/17-local-mvp-implementation-plan.md and docs/18-market-regime-and-multitimeframe-spec.md.

## Phase 1.1 — Historical validation
- Historical candle collection and integrity checks.
- Backtesting/replay harness with chronological walk-forward and untouched holdout.
- Realistic bid/ask execution and cost model.
- Scenario-by-scenario and regime-by-regime reporting.
- Threshold sensitivity, ablations, and multiple-testing controls.

## Phase 1.2 — Research extensions
- Compare HMM/regime-switching, clustering, and change-point detection with the transparent baseline.
- Add further scenario families only when data and tests justify them.
- Consider prospective order-book capture only if it adds measurable value.
- Evaluate optional bounded AI evidence review after deterministic baselines.

## Phase 2 — Operational monitoring
Only if local research proves value and the user approves:
- continuous WebSocket market monitoring;
- robust order-book synchronization and replay capture;
- operational recovery and feed health;
- deployment and monitoring architecture.

## Phase 3 — SaaS product
Only after technical efficacy, operational readiness, and commercial/legal approval:
- accounts, plans, entitlements, payments, subscriber distribution;
- notifications and lifecycle monitoring;
- hosting, security, support, and compliance.

No phase implies profitability or authorizes live order execution.
