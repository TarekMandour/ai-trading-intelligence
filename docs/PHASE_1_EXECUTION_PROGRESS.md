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

## Hierarchical multi-timeframe alignment update

- Replaced strict four-timeframe directional equality with role-based long-only alignment.
- Context must be BULLISH; BEARISH or non-bullish context blocks long entries.
- Structure may be BULLISH or RANGING; BEARISH structure invalidates the setup conservatively.
- Setup may be BEARISH as a pullback, but Entry must be BULLISH before the alignment gate allows order-book evaluation.
- A bullish Entry with a bearish Setup is labeled PULLBACK; consolidation at Structure or Setup is labeled PARTIAL; fully bullish states are ALIGNED.
- This is a strategy hypothesis, not a profitability claim. It requires local tests, report inspection, historical validation, and paper trading.
- Higher-timeframe candles must remain completed/closed at decision time; no forming-candle values may enter the alignment or AI evidence.


## AI advisory review implementation update

- Expanded the OpenAI structured response to include assessment, timeframe alignment, ordinal evidence-strength label, supporting/contradicting evidence IDs, missing confirmations, risk observations and concise rationale.
- Laravel validator now requires the exact response key set, validates enums, rejects unknown/duplicate/overlapping evidence IDs, and bounds text lists and rationale length.
- AI remains restricted to deterministic ENTERABLE candidates and the configured per-run cap.
- Provider failures and invalid responses are stored as AI review statuses while deterministic reports continue unchanged.
- Added an audit migration for schema version and SHA-256 evidence hash; the existing prompt_version column is reused.
- Reports display the AI review as advisory, including cautions and missing confirmations, separately from deterministic decision and levels.
- Added tests for strict contract validation, malformed/failed provider responses, AI caution isolation and provider-failure fallback.
- Automated tests use fakes. The complete test suite, migration and real OpenAI smoke test remain to be run in the user's Laragon environment.



## Technical strategy replacement (2026-11-13)

- The active ICT engine was removed from crypto and EGX analysis paths and its dedicated classes/configuration/tests were removed.
- Both markets now call the provider-neutral `TechnicalStrategyEngine` using normalized completed OHLCV candles.
- The replacement ports the supplied StockAnalysisService calculations: confirmed fractal swings, HH/HL/LH/LL structure, BOS/CHoCH, liquidity sweep, trend score, support/resistance strength, RVOL/volume trend/accumulation, ATR, entry zone, stop/targets, reward/risk, opportunity/speed scores and support-bounce confirmation.
- Scalping, Intraday and Swing keep their existing multi-timeframe mappings. Binance and Thndr remain separate data providers; Gemini remains an independent advisory layer.
- EGX historical replay now evaluates the same strategy chronologically, enters at the next entry candle open, applies explicit fees/slippage and resolves same-bar stop/target ambiguity stop-first.
- Known research limitations: the supplied score thresholds are hypotheses, not calibrated probabilities; the historical replay uses OHLCV approximations and does not model queue priority or historical order-book depth; no profitability claim is made.
- Verification remains pending in the target PHP 8.3/Laravel 13 environment. Do not treat this branch as validated until PHP lint, the full Laravel test suite, migrations and live-data smoke tests pass.
