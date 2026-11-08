# ADR 0006: Local Laravel + MySQL Manual-Analysis MVP

- Status: Accepted
- Date: 2026-11-09
- Scope: First local research prototype only

## Context

The project is not ready for server deployment or commercial SaaS. The immediate goal is to let the user test whether the analysis engine can classify crypto market regimes and identify explainable Spot-long opportunities. The user will run the application locally on Windows using XAMPP and trigger analysis manually.

The earlier continuous market-monitoring design is a possible future architecture, not a requirement for the first local MVP.

## Decision

1. Use Laravel 13 and PHP 8.3.
2. Use MySQL as the target relational database.
3. Use local XAMPP Apache/PHP and a local MySQL service.
4. Use a Laravel modular monolith in one repository.
5. Use Binance Spot public REST API only for the first local milestone.
6. Trigger analysis manually from the dashboard.
7. Process runs using Laravel's database queue and a manually started queue worker.
8. Do not use persistent Binance WebSocket connections in the first milestone.
9. Produce separate Scalping, Intraday, and Swing reports in one analysis run.
10. Implement transparent deterministic market-regime and scenario baselines before optional AI review.
11. Keep the first local milestone independent of OpenAI API credentials.
12. Keep all results, configuration versions, evidence, and state transitions auditable.

## Initial trading profiles

- Scalping: 1–30 minutes.
- Intraday: 30 minutes–8 hours.
- Swing: 1–7 days.

Initial timeframe sets are configurable research hypotheses:
- Scalping: 1m, 5m, 15m, 1h.
- Intraday: 5m, 15m, 1h, 4h.
- Swing: 1h, 4h, 1d, 1w.

## Consequences

### Positive
- No hosting, Redis, Kafka, Docker, WebSocket runtime, or extra infrastructure.
- Easier local setup and debugging.
- Manual runs constrain API usage and operational complexity.
- The same modular domain boundaries can support future automation if justified.

### Trade-offs
- REST snapshots do not provide continuous event-level market data.
- Historical order-book depth is not available from a current snapshot.
- Manual analysis cannot monitor entry validity continuously after the run.
- Long runs require a queue worker terminal.
- Results are a research prototype, not evidence of live profitability.

## Explicit exclusions

No Go, Python service, OpenSwoole, ReactPHP, Redis, Horizon, Kafka, RabbitMQ, Docker, Kubernetes, Reverb, continuous WebSocket ingestion, subscriptions, billing, order execution, or commercial signal distribution in this milestone.

## XAMPP database note

XAMPP distributions commonly bundle MariaDB rather than Oracle MySQL. The implementation must inspect and document the actual server version. If strict MySQL is required, use a separately installed local MySQL service and connect Laravel to it; do not mislabel MariaDB as MySQL.

## Revisit conditions

Revisit the decision only when local measurements show that REST/manual runs cannot answer the research question, or after the local prototype has demonstrated a repeatable useful edge and the user approves a next milestone.
