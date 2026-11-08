# Agent Operating Instructions

## Mission
Build AI Trading Intelligence as a reliable, auditable local trading-research prototype first. The repository documentation is the source of truth. The immediate milestone is the Local Manual-Analysis MVP; commercial SaaS is a later milestone.

## Mandatory workflow
1. Read README.md and relevant docs before changing code.
2. Inspect the existing repository, current branch, runtime versions, and existing code. Never assume the codebase is empty.
3. Follow docs/17-local-mvp-implementation-plan.md and docs/18-market-regime-and-multitimeframe-spec.md.
4. Implement only on phase-1-local-mvp or a child feature branch. Never commit directly to main or phase-0-foundation.
5. Add or update tests for every behavior change.
6. Run tests/static analysis/formatting and report exact commands and outcomes. Never claim a check passed unless it was run.
7. Update docs/PROJECT_STATUS.md and relevant documents as progress changes.
8. Prefer a working vertical slice over broad incomplete scaffolding.

## Approved first milestone
- Laravel 13 / PHP 8.3.
- MySQL target database; verify actual local XAMPP database server/version. Do not call MariaDB MySQL.
- Windows/XAMPP local development.
- Binance Spot public REST only.
- Manual analysis initiated from the dashboard.
- Laravel database queue with a manually started worker.
- Three independent reports: Scalping (1–30m), Intraday (30m–8h), Swing (1–7d).
- Transparent deterministic regime and scenario baselines first.
- No OpenAI API key required for the first working baseline.

## Exclusions for this milestone
No production deployment, always-on monitoring, Binance WebSocket, Go/Python runtime, OpenSwoole, ReactPHP, Redis, Horizon, Kafka, RabbitMQ, Docker, Kubernetes, microservices, Reverb, subscriptions, billing, commercial redistribution, order execution, custody, derivatives, margin, borrowing, leverage, or short signals.
Do not add dependencies or paid services without explaining why, alternatives, and cost implications. Do not implement every research hypothesis at once.

## Trading and data safety
- Never expose credentials, tokens, or personal data in source, logs, commits, examples, or docs.
- Deterministic calculations, hard gates, and lifecycle transitions are authoritative.
- AI must be allowed to abstain. Never force a BUY signal.
- Scores are not success probabilities unless statistically calibrated and validated out-of-sample.
- Preserve immutable analysis snapshots and auditable state/reason history.
- Prevent look-ahead bias, repainting, survivorship bias, and data leakage.
- Use completed candles for context; label forming candles.
- Preserve timeframe conflicts; do not average them into an opaque score.
- Entry assumptions use ask-side prices; exits use bid-side prices, including both-leg fees and slippage.
- A current REST order-book snapshot is not historical depth.
- No AI-generated prices, stops, targets, executable code, or risk-gate bypasses.

## Required implementation-task response
- Context read
- Understanding and assumptions
- Proposed plan
- Files/components affected
- Risks and edge cases
- Tests to add/run
- Results and remaining limitations

## Definition of done
Follow docs/17-local-mvp-implementation-plan.md. Record exact commands and results. A successful smoke test is not proof of profitability.
