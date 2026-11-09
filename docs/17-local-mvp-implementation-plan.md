# Local MVP Implementation Plan

Status: Approved execution scope for the first local prototype. This plan supersedes cloud/SaaS deployment assumptions for the current milestone only.

## 1. Goal

Build a locally runnable Laravel application that the user can start manually on Windows/XAMPP, trigger on demand, and use to inspect three independent reports:

1. Scalping: 1–30 minute intended holding window.
2. Intraday: 30 minute–8 hour intended holding window.
3. Swing: 1–7 day intended holding window.

The first milestone proves that the application can collect current/historical public market data, classify market regimes across timeframes, evaluate explainable research-baseline scenarios, produce auditable candidate plans, and display separate reports. It does not prove profitability.

## 2. Approved local stack

- Laravel 13.
- PHP 8.3.
- MySQL as the target database.
- Windows local development using XAMPP Apache/PHP, with MySQL service configured locally.
- Laravel Blade/Vue 3 only where useful; reuse the existing frontend conventions if present.
- Laravel database queue for manually triggered analysis jobs.
- File/database cache only.
- One local application; one local database.
- Binance Spot public REST API for market data.
- No production deployment in this milestone.

### Explicitly out of scope for the local MVP

- Go, Python runtime services, OpenSwoole, ReactPHP, or custom persistent WebSocket runtime.
- Binance WebSocket streaming or always-on market monitoring.
- Redis, Horizon, Kafka, RabbitMQ, Docker, Kubernetes, microservices, Reverb.
- Futures, perpetuals, margin, borrowing, leverage, options, short signals, or order execution.
- SaaS subscriptions, billing, tenant distribution, and commercial redistribution.
- A generic AI-agent framework or multi-agent orchestration.
- AI-generated trade prices, stop-losses, targets, market data, or final risk decisions.

Hybrid AI review is included in this milestone through a small provider-neutral interface. OpenAI is the first provider. The AI API credential is optional for local deterministic operation, but the adapter, queued review stage, schema validation, audit status, and fallback behavior must be implemented now.

If the installed XAMPP package supplies MariaDB rather than Oracle MySQL, verify the actual server/version first. Do not silently label MariaDB as MySQL. Prefer a local MySQL 8.x service if strict MySQL is required; XAMPP Apache/PHP can connect to a separately installed local MySQL instance.

## 3. Manual analysis workflow

1. User opens the local dashboard.
2. User selects or accepts the configured analysis universe and clicks Start Analysis.
3. Laravel creates an AnalysisRun with status QUEUED and a unique idempotency key.
4. The database queue worker processes the run. The developer starts the worker manually in a terminal using documented Artisan commands.
5. Data provider fetches exchange metadata, eligible USDT Spot tickers, klines for required timeframes, and current order-book snapshots only for shortlisted candidates when needed.
6. Data quality checks run before feature generation.
7. Market regime is classified independently per timeframe and then summarized per profile.
8. Scenario evaluators produce candidates; deterministic decision/risk gates produce ENTERABLE, WAIT, MISSED, REJECTED, or NO_OPPORTUNITY.
9. Shortlisted candidates are sent to the configured AI provider for structured evidence review within the queued job. The AI stage may assess scenario quality, timeframe alignment, supporting/contradicting evidence, missing confirmation, and abstain.
10. Laravel validates AI output and applies deterministic final decision/risk gates; AI cannot change computed prices or bypass gates.
11. Results are persisted and rendered as three separate report views/tabs, with AI review status clearly shown.
10. User can reopen previous runs and inspect their data timestamps, configuration version, evidence, and reasons.

The first prototype does not continuously monitor or automatically update opportunities after the run completes. A later refresh is a new analysis run.

## 4. Initial universe for local testing

- Binance Spot instruments only.
- USDT quote asset only.
- Exclude inactive, restricted, leveraged-token, and non-Spot instruments.
- Start with the top 20 eligible symbols by 24-hour quote volume for local smoke testing. Make the count configurable; do not hard-code the planning target of 30–50 into the MVP.
- Apply minimum data completeness and basic spread/depth checks.
- Fetch deeper REST order-book data only for shortlisted candidates, not every symbol.
- Treat thresholds in docs/13 as research hypotheses until measured. Do not block the first technical smoke test on unvalidated thresholds.
- Record excluded symbols and reason codes.

## 5. Initial timeframe matrix

The first configurable research matrix is:

| Profile | Timeframes |
|---|---|
| Scalping | 1m, 5m, 15m, 1h |
| Intraday | 5m, 15m, 1h, 4h |
| Swing | 1h, 4h, 1d, 1w |

The roles are context → structure → setup → entry, but exact assignment is part of validation. Use completed candles for stable context. If the current candle is included, label it FORMING and prevent it from being treated as a completed-candle confirmation. Store source open time, close time, receive time, and candle status.

## 6. Regime detection

Implement a transparent, deterministic baseline before ML:

- Direction: BULLISH, BEARISH, RANGING, UNCERTAIN.
- Volatility: LOW, NORMAL, HIGH.
- Behavior: TRENDING, CHOPPY, TRANSITION, UNCERTAIN.

Use multiple features, with thresholds in versioned configuration:
- swing structure (HH/HL and LH/LL);
- normalized slope;
- ADX/directional movement;
- ATR or realized-volatility percentile;
- Bollinger Band width percentile;
- range efficiency / path efficiency.

Keep direction and volatility orthogonal. A market may be BULLISH + HIGH volatility or RANGING + LOW volatility. Report the classification and evidence for each timeframe. Preserve higher/lower timeframe conflicts rather than flattening them into a single weighted label.

HMM, clustering, and more advanced classifiers are research follow-ups, not first-night dependencies.

## 7. Initial scenario baselines

Implement a small, transparent, versioned set only after data and regime tests pass:

- Trend continuation / pullback continuation.
- Breakout and retest.
- Range mean reversion.
- Momentum/volatility expansion.

Liquidity sweep/reclaim and order-flow imbalance remain research candidates until their required historical/event data and definitions are available. A current order-book snapshot may support an execution-quality check but cannot be used as historical order flow.

Each scenario must define inputs, conditions, confirmation, invalidation, entry, stop, targets, expiry, and reason codes. Use deterministic calculations; no AI-generated prices or levels. AI may review the rationale and evidence only.

## 8. Data provider and API discipline

Use Binance public REST endpoints only in the initial milestone:
- exchange information / symbol filters;
- 24-hour ticker statistics;
- klines for required intervals;
- order-book snapshot for shortlisted candidates when required.

Requirements:
- centralize provider calls in one adapter/service;
- respect current endpoint weights and rate limits;
- batch where supported;
- bounded retries with exponential backoff and jitter;
- timeouts and clear provider error states;
- no secrets or API keys required for public market data;
- persist source timestamps and local receive timestamps;
- validate symbol status, interval, continuity, duplicates, and malformed values;
- do not invent historical depth from a current snapshot.

## 9. AI review integration

Implement the bounded hybrid AI workflow described in docs/20-ai-integration-spec.md:
- A small provider contract and an OpenAI adapter using Laravel's HTTP client.
- Provider/model/prompt version selected through environment configuration; API key only in local .env and never stored in DB/logs.
- AI review runs in the existing database queue after deterministic shortlist generation, not in the browser request and not per candle.
- Send compact structured evidence, not raw unbounded candle histories. Include data timestamps, per-timeframe regimes, alignment/conflicts, evaluated scenarios, deterministic trade plan levels, cost assumptions, and reason codes.
- Require schema-constrained JSON output; validate types, enums, lengths, and references to supplied evidence IDs. Reject malformed or unsupported output.
- AI may assess, compare, rank, critique, explain, request missing confirmation, or abstain. It cannot invent/modify market values, entry/stop/targets, cost assumptions, or final risk status.
- Final ENTER/WAIT/REJECT/NO_OPPORTUNITY status is determined by Laravel hard gates. AI advisory output cannot override any gate.
- Record NOT_CONFIGURED, PENDING, COMPLETED, FAILED, or REJECTED response state. Provider failure must not destroy deterministic analysis results.
- Add bounded timeout/retry/backoff, token/output limits, sanitized audit, and cache/deduplication keyed by evidence + prompt + model version where appropriate.
- Track latency and usage/cost metadata where returned. No hard-coded model name or API key.
- No generic agent framework, tool-calling loop, or multi-agent design.

## 10. Storage and audit

Persist:
- analysis run and status;
- selected universe and exclusions;
- candle data or reproducible references according to retention configuration;
- per-timeframe regime snapshots;
- features and calculation/config versions;
- candidate and scenario evaluations;
- trade plan snapshots;
- immutable state/reason events;
- provider request metadata and data timestamps.

Use decimal-safe database types for prices, quantities, and costs. Avoid floating-point arithmetic for authoritative prices and quantities. Do not store secrets in database records or logs.

## 11. Reports

Create three independent reports for each run:
- Scalping Report.
- Intraday Report.
- Swing Report.

Each candidate shows symbol, scenario, regime context by timeframe, status, discovery time/reference price, entry zone, stop/invalidation, target 1/2, conservative reward/risk after costs, estimated fees/spread/slippage, expiry, opportunity/confirmation/execution/risk/data scores, reasons, contradictions, and data-quality warnings.

Scores are rankings, not probabilities. Display NO OPPORTUNITY when no candidate passes gates. Never force a minimum number of recommendations.

## 12. Local operations

Document:
- XAMPP/PHP/MySQL prerequisites and verified versions.
- Composer install and environment setup.
- database creation and migration.
- queue table setup.
- one terminal for Apache/MySQL via XAMPP.
- one terminal for Laravel database queue worker; set timeout based on measured run duration and document exact command.
- how to trigger a run, inspect logs, stop/retry a failed run, and clear local cache.
- how to reset test data safely.

Do not assume Windows process spawning or background shell execution from a web request. Use the Laravel database queue and a manually started worker.

## 13. Definition of Done for first local test

- Fresh local install instructions work on the target Windows/XAMPP environment.
- Migrations run cleanly on the target MySQL version.
- Dashboard can create a run and show QUEUED/RUNNING/COMPLETED/FAILED.
- Queue worker processes the run without requiring Redis.
- Binance REST adapter fetches and validates market data within rate limits.
- Three separate profile reports are produced, even if some are NO_OPPORTUNITY.
- Regime output is available per timeframe and retains conflicts.
- Scenario outputs include complete evidence and reason codes.
- AI integration is implemented and tested with a fake provider; a real provider call is verified when the user supplies a valid local API key.
- Deterministic reports remain available when AI is not configured or the provider fails, with explicit AI status.
- Unit tests cover indicators/features, regime labels, timeframe alignment, candle completeness, costs, entry/stop/target geometry, and state transitions.
- Feature/integration tests cover run creation, queue processing, provider failures, retries, and report rendering.
- php artisan test passes; exact test command and result are recorded.
- A sample run can be repeated deterministically from stored input data/configuration.
- No claim of profitability is made from a smoke test.

## 14. Explicit next milestone after local smoke test

Only after the first working prototype:
- add historical backtesting and event/candle replay;
- validate each strategy family independently;
- test thresholds and timeframe hierarchy;
- capture prospective order-book data if justified;
- evaluate AI review quality, latency, and cost;
- consider WebSocket streaming only if measured manual REST analysis cannot answer the product question.

## 15. Commercial/legal boundary

This milestone is a local personal research prototype. It does not authorize commercial redistribution, paid subscriber signals, raw data resale, or SaaS launch. Verify current Binance terms and any applicable jurisdictional requirements before commercial use.
