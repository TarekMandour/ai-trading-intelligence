# Project Status

Last updated: 2026-11-12

## Current phase
**Phase 1 — Local Manual-Analysis MVP.** The user has authorized implementation of a local research prototype on the dedicated branch. No server deployment or commercial SaaS release is authorized.

## Repository and branch policy
- Repository: TarekMandour/ai-trading-intelligence
- Default branch: main
- Phase 0 decision branch: phase-0-foundation
- Phase 1 baseline branch: phase-1-local-mvp
- ICT implementation branch: feature/ict-comprehensive (child branch; not merged)
- Never implement directly on main or phase-0-foundation.

## Approved product scope
- Crypto; Binance Spot public market data; USDT quote asset.
- Long Spot entry / sell-to-exit only.
- Manual, on-demand analysis.
- Three separate reports per run:
  - Scalping: 1–30 minutes.
  - Intraday: 30 minutes–8 hours.
  - Swing: 1–7 days.
- Windows/XAMPP local development; Laravel 13 / PHP 8.3.
- MySQL target database; verify actual installed server. XAMPP may bundle MariaDB.
- Laravel database queue with manually started worker.
- REST data collection only for the first milestone.
- Hybrid AI architecture is approved for Phase 1: deterministic Laravel analysis plus provider-neutral AI review, with OpenAI as the first provider.
- AI review is implemented in this milestone for shortlisted candidates when credentials are configured. Deterministic reports remain available when AI is unavailable or not configured.

## Approved exclusions
No VPS/cloud deployment, continuous monitoring, Binance WebSocket, Redis, Horizon, Kafka, RabbitMQ, Docker, Kubernetes, OpenSwoole, ReactPHP, Go/Python services, Reverb, subscriptions, billing, commercial signal distribution, automated order execution, custody, margin, borrowing, leverage, futures, perpetuals, options, or short signals.

## Initial research profiles (configurable hypotheses)
| Profile | Context | Structure | Setup | Entry |
|---|---|---|---|---|
| Scalping | 1h | 15m | 5m | 1m |
| Intraday | 4h | 1h | 15m | 5m |
| Swing | 1w | 1d | 4h | 1h |

These profiles must be tested. Candle alignment must use only completed higher-timeframe candles available at the decision timestamp.

## Market-regime contract
The classifier has three independent dimensions:
- Direction: BULLISH, BEARISH, RANGING, UNCERTAIN.
- Volatility: LOW, NORMAL, HIGH.
- Behavior: TRENDING, CHOPPY, TRANSITION, UNCERTAIN.

Volatility is not a direction. Preserve per-timeframe classifications and conflicts. Begin with transparent deterministic features; HMM/ML is deferred research.

## Research status
Research sources and limitations are documented in docs/16-research-evidence-and-strategy-hypotheses.md. Scenario families remain hypotheses, not production-approved strategies. Current research does not prove profitability for Binance Spot or the selected holding windows.

## Implementation documents
- docs/17-local-mvp-implementation-plan.md
- docs/20-ai-integration-spec.md
- docs/18-market-regime-and-multitimeframe-spec.md
- docs/decisions/0006-local-laravel-mysql-manual-analysis-mvp.md

## Deferred documents
- docs/14-market-monitoring-and-data-pipeline.md describes future continuous/event-driven monitoring, not the first local MVP.
- docs/15-opportunity-detection-and-decision-pipeline.md remains a logical decision framework; streaming triggers and subscriber distribution are deferred.

## Definition of first local test
- Fresh XAMPP setup and migrations succeed against verified MySQL.
- Manual dashboard action creates an analysis run.
- Database queue worker processes it.
- Binance REST data is fetched, validated, and rate-limited.
- Per-timeframe regime outputs are generated for each profile.
- Three separate reports are rendered, including NO OPPORTUNITY where appropriate.
- Candidate plans include entry, stop/invalidation, targets, costs, expiry, evidence, and reason codes.
- Tests cover data quality, calculations, multi-timeframe alignment, decision gates, queue workflow, report rendering, AI schema validation, provider failures, and deterministic fallback.
- Exact commands and test outcomes are documented.
- No profitability claim is made from the first smoke test.

## Future gates
- Backtesting/replay harness and untouched out-of-sample validation.
- Evidence that any strategy family has positive net expectancy after costs.
- Prospective order-book capture only if research justifies it.
- Validate AI review quality, cost, latency, and consistency against human-reviewed cases; AI remains advisory and cannot override deterministic gates.
- Commercial data rights and legal/jurisdiction review before paid signal distribution.
- Hosting, subscriptions, and SaaS only after local proof of value and separate approval.

## Current status
Initial vertical slice implemented: a Laravel dashboard creates an idempotency-keyed queued analysis run, the database worker persists independent Scalping, Intraday, and Swing reports, and Binance public REST failure produces auditable `NO_OPPORTUNITY` reports rather than invented plans. The first slice includes a bounded Binance exchange/ticker client and a NOT_CONFIGURED AI fallback state. Regime calculations, candle normalization, scenario/risk geometry, OpenAI adapter/schema validation, and detailed opportunity persistence remain incomplete. Do not mark the local MVP complete until the Definition of First Local Test is met and the user can run it on their machine.


## ICT Comprehensive implementation branch update (2026-11-12)

- Created feature branch: feature/ict-comprehensive, based on phase-1-local-mvp commit 176504ec2f7bc501ef89688622b93f3ac320c890.
- Added an initial deterministic ICT engine and dedicated ICT configuration.
- Integrated the ICT engine into the queued analysis workflow and stored the ICT result in the opportunity evidence snapshot.
- Added an ICT section to the report view.
- Three core scenario evaluators are present: SSL Sweep Reversal, Bullish FVG Continuation, and Bullish Order Block Reaction.
- This is not yet a complete ICT Comprehensive delivery: advanced models, detailed event lifecycle, Market/Limit plan lifecycle, complete evidence normalization, and robust historical replay remain outstanding.
- Tests have been added but have not been executed in this environment. Do not mark implementation or validation complete until code review and local test execution.


## ICT Comprehensive status (feature/ict-comprehensive)

- Added dedicated ICT configuration for long-only, 24-hour analysis, existing Scalping/Intraday/Swing profiles, and Market/Limit research plans.
- Added deterministic engine and integrated it into the queued analysis workflow in place of the legacy scenario selection path.
- Implemented the three core ICT scenario evaluators and snapshot/report persistence.
- Added optional confluence evidence for equal liquidity levels, OTE, rejection-block candidates, breaker-block retests, and configured session windows.
- SMT intentionally remains unavailable without a synchronized reference asset and approved pairing rule.
- Market and Limit outputs are plans only. Exchange order submission remains disabled.
- The implementation is not yet complete or validated: chronological event lifecycle, robust FVG/OB mitigation, advanced model gates, and historical replay/backtesting remain outstanding.
- Tests were added but not executed here. No test pass or profitability claim is recorded.
