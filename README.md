# AI Trading Intelligence

A centralized, multi-market trading intelligence platform that discovers, evaluates, monitors, and distributes actionable trade opportunities to subscribers according to their subscription entitlements.

## Current phase

**Phase 0 — Project Foundation.** This phase establishes product scope, architecture, market-analysis methodology, data requirements, risk controls, validation standards, and AI-agent operating rules before implementation begins.

## Core product principles

- Analyze each market opportunity centrally; do not repeat analysis per subscriber.
- Persist immutable signal snapshots and an auditable event history.
- Distribute eligible opportunities according to subscription plans.
- Use OpenAI API centrally only where model reasoning adds measurable value; ordinary notification delivery must not require an AI call.
- Treat deterministic calculations, risk gates, and lifecycle transitions as authoritative.
- Allow the system to return **NO OPPORTUNITY** rather than force a trade.
- Never represent scores as probabilities unless calibrated and validated on out-of-sample data.
- No strategy or model is production-approved without realistic validation, costs, and monitoring.

## Documentation map

See [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) for current decisions, open questions, and phase progress. Start with [`docs/00-project-overview.md`](docs/00-project-overview.md), then read the product vision and requirements.

## Development policy

Read [`AGENTS.md`](AGENTS.md) before making changes. No production code is authorized until Phase 0 decisions are reviewed and approved.

## Important disclaimer

This platform provides analytical information, not guaranteed returns or personalized financial advice. Trading involves risk, including loss of capital. Every opportunity must communicate its assumptions, invalidation conditions, freshness, and risk context.
