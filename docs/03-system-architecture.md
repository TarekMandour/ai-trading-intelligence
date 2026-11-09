# System Architecture — Local Manual-Analysis MVP

Status: The local MVP architecture is approved by ADR 0006. This document describes the current milestone; continuous SaaS architecture remains future scope.

## 1. Architecture style

Use a Laravel modular monolith in one repository. Keep domain boundaries clear without introducing separate deployable services. The application runs locally on Windows using XAMPP Apache/PHP and a verified local MySQL database.

## 2. Runtime components

1. Laravel Web Application — local dashboard, run creation, report display, configuration.
2. Analysis Run Orchestrator — creates and coordinates one manual analysis run.
3. Database Queue Worker — processes the run when manually started in a terminal.
4. Binance REST Adapter — exchange metadata, 24-hour tickers, klines, and selected current depth snapshots.
5. Market Data Validation — symbol eligibility, freshness, candle completeness, duplicates, gaps, and source timestamps.
6. Market Regime Engine — per-timeframe direction, volatility, and behavior classification.
7. Multi-Timeframe Context Engine — profile-specific context/structure/setup/entry views and conflict reporting.
8. Scenario Evaluators — small versioned deterministic baseline families.
9. Decision & Risk Engine — entry, stop/invalidation, targets, costs, expiry, and hard gates.
10. AI Review Layer — provider-neutral structured review of shortlisted candidates and market context; OpenAI is the initial adapter.
11. Report & Audit Store — immutable run, evidence, deterministic decision, AI review, and reason snapshots.

## 3. Manual data flow

Dashboard action → create AnalysisRun → database queue → Binance REST collection → data-quality validation → per-timeframe features/regime → three profile evaluators → deterministic risk/decision gates → persist snapshots → render three independent reports.

No always-on market feed or automatic post-run monitoring is required. A new manual refresh creates a new run.

## 4. Technology

- Laravel 13 / PHP 8.3.
- MySQL target database.
- XAMPP Apache/PHP on Windows.
- Laravel database queue.
- File/database cache.
- Binance Spot public REST API.
- Reuse existing frontend conventions; do not add a frontend framework unless the repository already requires it.

Important environment check: XAMPP packages may bundle MariaDB instead of Oracle MySQL. Inspect the actual database server and version. If strict MySQL is required, install MySQL locally and connect XAMPP/Laravel to that service.

## 5. Data acquisition

The first milestone uses REST only:
- exchange information and symbol filters;
- 24-hour ticker statistics;
- klines for 1m, 5m, 15m, 1h, 4h, 1d, and 1w as needed by profiles;
- current order-book snapshots only for shortlisted candidates where execution-quality checks require them.

Start with a configurable top-20 eligible USDT Spot universe for local smoke testing. Fetch deeper data only for shortlisted candidates. Respect current Binance request weights and limits. Store source event/open/close times and local receive times. Never fabricate historical depth from a current snapshot.

## 6. Analysis modules

Keep modules/services separated by responsibility:
- MarketData
- InstrumentUniverse
- CandleStore
- FeatureCalculation
- MarketRegime
- MultiTimeframe
- ScenarioEvaluation
- DecisionRisk
- AnalysisRuns
- Reports
- Audit

Do not create an elaborate generic plugin framework. Use small interfaces only where a second implementation is genuinely expected.

## 7. Database and persistence

Use decimal-safe columns for prices, quantities, and monetary values. Persist analysis runs, selected symbols, data provenance, per-timeframe regime snapshots, feature/config versions, scenario evaluations, trade plans, and immutable reason/state events. Avoid storing secrets. Retain only the data needed for reproducible local research and within applicable data terms.

## 8. Queue and local operations

Use Laravel's database queue. The user starts the worker manually from a terminal. Do not create a web request that shells out to a background process, and do not add Redis/Horizon. Configure timeout and retry behavior based on measured analysis duration and prevent duplicate concurrent runs.

## 9. AI integration boundary

Hybrid AI review is part of the Phase 1 implementation. Laravel computes market features, regime states, multi-timeframe relationships, candidate scenarios, entry/stop/target levels, cost assumptions, and deterministic eligibility. A provider-neutral AI adapter receives a compact, validated evidence packet and returns schema-constrained interpretation: scenario assessment, supporting/contradicting evidence, timeframe alignment, missing confirmation, explanation, and advisory ranking/stance.

OpenAI is the first provider. Keep the provider contract small and configuration-driven; do not build a generic agent framework or multi-agent orchestration. API calls happen inside the queued analysis workflow after deterministic shortlist creation, not in the browser request and not per candle. Cache/deduplicate by evidence/config/model/prompt fingerprint where safe.

AI is not authoritative: it cannot create or alter price levels, change costs, bypass data-quality checks, override risk gates, or force ENTER. Laravel validates the response schema and evidence references, then applies deterministic final gates. AI may abstain. Persist provider/model/prompt versions, status, latency, usage/cost metadata when available, and sanitized request/response audit records. Never persist credentials.

If no AI credential is configured or the provider fails, the run still produces deterministic reports and marks AI review as NOT_CONFIGURED or FAILED; it must never silently label the AI review complete. The local baseline therefore remains testable without paid API access, while the AI integration itself is implemented in this milestone.

## 10. Explicitly deferred architecture

Not part of this milestone:
- Binance WebSocket ingestion and persistent Market Runtime;
- watchlist/active-set continuous monitoring;
- Redis, Kafka, RabbitMQ, Horizon, Reverb;
- cloud deployment, containers, microservices;
- subscribers, entitlements, billing, and commercial distribution;
- automated order execution.

These may be reconsidered only after the local prototype demonstrates a repeatable research benefit and the user approves the next milestone.

## 11. References

- Binance Spot REST API: https://github.com/binance/binance-spot-api-docs/blob/master/rest-api.md
- Binance Spot API documentation: https://developers.binance.com/en/docs/products/spot/overview
- Research evidence: docs/16-research-evidence-and-strategy-hypotheses.md
- Local implementation plan: docs/17-local-mvp-implementation-plan.md
- Regime contract: docs/18-market-regime-and-multitimeframe-spec.md
