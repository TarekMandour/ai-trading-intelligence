# Agent Execution Brief — Local MVP

This is the single execution brief for Codex or another coding agent. The approved scope is in docs/17-local-mvp-implementation-plan.md. Regime behavior is specified in docs/18-market-regime-and-multitimeframe-spec.md. Research context is in docs/16-research-evidence-and-strategy-hypotheses.md. Hybrid AI integration is specified in docs/20-ai-integration-spec.md.

## Mission

Inspect the existing repository and implement a working local vertical slice for manual analysis on Windows/XAMPP. Do not assume the repository is empty. The user wants to test the application locally, not deploy it.

## Mandatory first actions

1. Confirm current branch is phase-1-local-mvp (or create a child branch from it). Never modify main or phase-0-foundation.
2. Read README.md, AGENTS.md, docs/PROJECT_STATUS.md, docs/03-system-architecture.md, docs/04-market-analysis-methodology.md, docs/09-testing-validation.md, docs/16-research-evidence-and-strategy-hypotheses.md, docs/17-local-mvp-implementation-plan.md, and docs/18-market-regime-and-multitimeframe-spec.md.
3. Inspect composer.json, package.json, routes, migrations, models, services, tests, and existing UI before making changes.
4. Verify PHP/Laravel versions and the actual database server/version. XAMPP may ship MariaDB rather than Oracle MySQL. Do not silently misrepresent it. If a local runtime is not available to the agent, record this as a user-side verification step.
5. Read docs/20-ai-integration-spec.md and implement the approved hybrid AI stage as part of Phase 1.
6. Present a short implementation plan and risks in the PR description, then proceed within this approved scope; do not ask for approval for each small implementation detail.

## Build order — do not skip ahead

### Slice 1: Application and database baseline
- Preserve existing working code and conventions.
- Ensure Laravel boots with the approved PHP version.
- Configure MySQL-compatible local connection and database queue.
- Add only necessary migrations/models for analysis runs and results.
- Add run status lifecycle: QUEUED, RUNNING, COMPLETED, FAILED.
- Add idempotency/concurrency protection for repeated clicks.

### Slice 2: Binance REST and data quality
- Implement one provider adapter for public exchange info, 24h tickers, klines, and current order-book snapshots only when required for shortlisted candidates.
- Start with configurable top 20 eligible USDT Spot symbols for smoke tests.
- Respect endpoint request weights/rate limits, timeouts, bounded retries, and error reporting.
- Validate symbol status, timestamps, candle continuity, duplicates, OHLC invariants, and closed/forming state.
- Store source timestamps and receive timestamps.
- Do not add WebSocket or API secrets.

### Slice 3: Feature and regime baseline
- Implement transparent, deterministic features and versioned config.
- Produce separate direction, volatility, and behavior classifications per timeframe.
- Preserve uncertain labels and contradictory evidence.
- Implement the test cases in docs/18.
- Do not add HMM, neural networks, or AI as a dependency.

### Slice 4: Multi-timeframe profiles
Implement configurable profiles:
- Scalping: 1h context, 15m structure, 5m setup, 1m entry.
- Intraday: 4h context, 1h structure, 15m setup, 5m entry.
- Swing: 1w context, 1d structure, 4h setup, 1h entry.

Use only information available at the analysis timestamp. Do not leak future higher-timeframe candle values into earlier lower-timeframe observations. Expose conflicts instead of hiding them.

### Slice 5: Small strategy baseline
Implement only a small number of transparent, versioned scenario families:
- trend/pullback continuation;
- breakout/retest;
- range mean reversion;
- momentum/volatility expansion.

Do not implement order-flow imbalance, liquidity sweep, or complex ML unless existing reliable data and a testable definition are already available. No arbitrary strategy generation. The bounded AI review in docs/20 is approved and is not a general strategy generator.

### Slice 6: AI review integration
- Add a minimal provider contract and OpenAI adapter using Laravel HTTP client.
- Configure provider/model/API key through environment variables; never persist credentials.
- Execute AI review inside the queued run after deterministic shortlist creation.
- Send compact structured evidence; require schema-constrained JSON and validate it.
- AI may interpret, compare, rank, critique, explain timeframe conflicts, identify missing confirmation, and abstain.
- AI must not generate/alter prices, levels, costs, data, or final risk decisions; it cannot bypass deterministic gates.
- Provider missing/failure/malformed response must produce explicit AI status and preserve deterministic reports.
- Record sanitized audit metadata, prompt/model/provider versions, latency, and usage/cost metadata where available.
- Test with a fake provider; real OpenAI call is a user-side configuration/smoke-test step if no credential is supplied.

### Slice 7: Risk and reports
- Deterministic entry zone, stop/invalidation, target 1/2, expiry, max entry distance, cost assumptions, and rejection reasons.
- Entry uses ask-side assumptions; exit uses bid-side assumptions.
- Include both-leg fees, spread, and conservative slippage assumptions.
- If cost or data evidence is missing, return WAIT/REJECT/NO_OPPORTUNITY rather than a fabricated tradable plan.
- Produce three distinct report sections/views for Scalping, Intraday, and Swing.
- Save immutable run/config/evidence/decision snapshots.

### Slice 8: Tests and local runbook
- Unit tests for data validation, features, regime labels, multi-timeframe alignment, costs, entry/stop/target geometry, and state transitions.
- Feature tests for run creation, database queue processing, API failures/retries, partial symbol failures, and all three report views.
- Add a concise XAMPP/MySQL setup and exact queue worker instructions.
- Run php artisan test and any project-defined lint/static-analysis commands.
- Report exact command output. Never claim tests passed if not run.

## Strict exclusions

Do not add Redis, Horizon, Kafka, RabbitMQ, Docker, Kubernetes, WebSockets, Reverb, OpenSwoole, ReactPHP, Go, Python services, subscription/billing modules, SaaS distribution, order execution, or paid dependencies. A real OpenAI key is not required for deterministic tests or the baseline smoke run, but the hybrid AI integration itself is in scope and must be implemented. Do not defer the AI architecture to a future milestone.

Read docs/20-ai-integration-spec.md for the complete AI contract. Do not build generic agents, multi-agent orchestration, tool-calling loops, or autonomous trading.

## Delivery expectations

- Commit only to phase-1-local-mvp or a child branch.
- Keep changes focused and reviewable.
- Open a pull request targeting phase-1-local-mvp, not main.
- Update docs/PROJECT_STATUS.md with what is actually implemented and what remains.
- Include setup steps, migrations, queue worker command, test results, known limitations, and one reproducible sample run.
- If the full scope cannot be completed in one work session, deliver the highest-value working vertical slice and explicitly mark incomplete slices. Do not create empty scaffolding for everything.

## Critical truthfulness rule

A dashboard that returns candidates is a technical smoke test, not evidence of a profitable strategy. No live-trading or profitability claim is permitted without the separate out-of-sample and paper-trading validation gate.
