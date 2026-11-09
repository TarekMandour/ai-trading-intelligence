# Phase 1 Execution Progress

## Objective

Deliver the local, manual Laravel 13 analysis workflow for Binance Spot USDT long-only research, with deterministic risk gates and advisory-only AI.

## Implemented on `phase-1-local-mvp`

- Laravel local application baseline, database queue, manual run creation and report routes.
- Binance Spot REST client for exchange metadata, 24-hour tickers, klines and order-book snapshots.
- Eligible Spot USDT universe ranked by 24-hour quote volume before applying the configured universe limit.
- Current, still-forming klines excluded from downstream analysis.
- Deterministic baseline market regime, multi-timeframe alignment, scenario evaluation and execution-cost components.
- Relational run, report, opportunity, evidence, AI-review and audit persistence.
- Audit-event fingerprint migration for durable uniqueness.
- Symbol-scoped report uniqueness migration: one report per run/profile/symbol.
- Queued pipeline iterates every selected symbol and each Scalping, Intraday and Swing profile; records timeframe states, alignment, regime, scenario results, execution costs, evidence and decisions.
- AI review is restricted to deterministic ENTERABLE candidates with aligned timeframe evidence; AI remains advisory and cannot alter deterministic levels.

## Execution-cost validation update

- The order-book estimator now reports entry and exit fees separately, execution friction per leg, round-trip cost per base unit, and the reference midpoint.
- Buy cost is measured against the midpoint using visible ask-side VWAP; sell cost is measured against the same midpoint using visible bid-side VWAP. This separates the spread component from depth-walk slippage without double counting.
- The analysis job uses the explicit sum of entry- and exit-cost-per-unit to calculate net Target 1 reward and net stop risk. A candidate is not ENTERABLE unless net reward remains positive and net R:R meets the configured minimum.
- The visible REST depth snapshot is an estimate, not guaranteed future liquidity. The current-book sell-side friction is a conservative proxy for target/stop exit friction; this assumption must be disclosed in reports and validated with paper trading.

## Remaining implementation work

- Four deterministic long-only setup evaluators are research hypotheses, not validated profitable strategies.
- Complete retry-safe persistence semantics for mutable RUNNING jobs and verify actual duplicate retry behavior.
- OpenAI advisory review remains optional and bounded per run; deterministic reports persist when AI is unavailable.
- Final comprehensive tests, formatting, migrations, and local smoke test. Tests and migrations must be run in the user's Laragon environment; no local execution is performed by the assistant.

## Verification status

- User confirmed that the complete local test suite passed after correcting the Binance universe mapping issue.
- User verified live Binance connectivity: eligible Spot USDT symbols = 518; selected universe = 20; ticker count = 3,745.
- User verified 99 closed candles for each configured timeframe and valid depth snapshots with 20 bid and 20 ask levels.
- Execution-cost code update is committed but still requires local regression tests and inspection of first real analysis results.
- No profitability claim is made.
