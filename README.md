# AI Trading Intelligence

A trading-research application that discovers, evaluates, and records explainable crypto Spot-long opportunities. The long-term product may become a centralized SaaS platform; the current milestone is deliberately a small local prototype.

## Current phase

**Phase 1 — Local Manual-Analysis MVP.** The user runs the application on Windows/XAMPP and starts analysis manually. No server deployment or always-on market monitoring is planned for this milestone.

## Approved local stack

- Laravel 13 / PHP 8.3
- MySQL target database
- Windows + XAMPP Apache/PHP
- Laravel database queue with a manually started worker
- Binance Spot public REST API
- Provider-neutral AI review integration (OpenAI first provider)
- Local dashboard with three independent reports

Verify the actual database server supplied by the installed XAMPP package. Some XAMPP releases bundle MariaDB rather than Oracle MySQL. Do not mislabel one as the other.

## Three trading reports

- **Scalping:** intended holding window 1–30 minutes.
- **Intraday:** intended holding window 30 minutes–8 hours.
- **Swing:** intended holding window 1–7 days.

Initial timeframe profiles are configurable research hypotheses, not approved profitable strategies.

## Product principles

- Analyze once per manual run and save immutable evidence and decision snapshots.
- Separate market direction, volatility, and behavior regime dimensions.
- Preserve multi-timeframe conflicts and data-quality warnings.
- Use deterministic calculations and risk gates as the final authority.
- AI reviews structured evidence and shortlisted scenarios; it never invents trade levels or overrides hard gates.
- AI provider credentials are configured locally in .env; deterministic analysis remains available if AI is unconfigured or unavailable.
- Allow the engine to return **NO OPPORTUNITY**.
- Scores are not success probabilities unless calibrated and validated out-of-sample.
- Include fees, spread, slippage, and realistic entry/exit assumptions.
- No strategy is considered successful until it passes realistic out-of-sample and paper-trading validation.

## Documentation map

- [Project status](docs/PROJECT_STATUS.md)
- [Local MVP implementation plan](docs/17-local-mvp-implementation-plan.md)
- [Research evidence and strategy hypotheses](docs/16-research-evidence-and-strategy-hypotheses.md)
- [Market regime and multi-timeframe specification](docs/18-market-regime-and-multitimeframe-spec.md)
- [System architecture](docs/03-system-architecture.md)
- [Market analysis methodology](docs/04-market-analysis-methodology.md)
- [Validation standards](docs/09-testing-validation.md)
- [Agent instructions](AGENTS.md)
- [Codex execution brief](docs/19-agent-execution-brief.md)
- [AI integration specification](docs/20-ai-integration-spec.md)
- [ADR 0006 — Local Laravel/MySQL manual-analysis MVP](docs/decisions/0006-local-laravel-mysql-manual-analysis-mvp.md)

## Deferred architecture

The event-driven WebSocket monitoring design in docs/14 is future scope. It is not part of the first local milestone. SaaS subscriptions, commercial redistribution, deployment, and automated execution remain out of scope.

## Important disclaimer

This application provides analytical research outputs, not guaranteed returns or personalized financial advice. Trading involves risk, including loss of capital. A local technical prototype or backtest does not establish a live trading edge.
