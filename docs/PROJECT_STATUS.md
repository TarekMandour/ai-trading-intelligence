# Project Status

Last updated: 2026-11-13

## Active implementation branch
- Repository: `TarekMandour/ai-trading-intelligence`
- Base branch for this replacement: `feature/ict-engine-rebuild-v2`
- Working branch: `feature/technical-strategy-replacement`
- Changes are isolated on the working branch; `main` is not modified.

## Product scope
- Laravel 13 / PHP 8.3 research application.
- Crypto market data: Binance Spot USDT pairs, long-only.
- Egyptian equities: Thndr marketwatch and persisted Thndr OHLCV candles, long-only.
- Three independent analysis profiles:
  | Profile | Context | Structure | Setup | Entry |
  |---|---|---|---|---|
  | Scalping | 1h | 15m | 5m | 1m |
  | Intraday | 4h | 1h | 15m | 5m |
  | Swing | 1w | 1d | 4h | 1h |
- No automated order submission. Signals and backtests are research outputs only.

## Active technical analysis engine
`App\\Services\\Analysis\\TechnicalStrategyEngine` is the shared provider-neutral deterministic engine. It receives normalized OHLCV candle series and does not call Binance or Thndr directly.

The engine implements the supplied StockAnalysisService strategy:
- Confirmed fractal swing highs/lows.
- HH/HL/LH/LL structure, BOS and directional CHOCH.
- Liquidity sweep detection and directional contribution.
- Trend classification and trend score.
- Support/resistance extraction, touch clustering, strength and proximity ranking.
- Relative volume, volume trend and accumulation/distribution evidence.
- ATR and volatility context.
- Entry zone, stop loss, Target 1/2 and reward/risk.
- Opportunity score, speed score, fast-trade class and support-bounce confirmation.
- Multi-timeframe context/structure/setup alignment plus entry-timeframe confirmation.

The engine is independent of market provider: Binance and Thndr adapters supply the same normalized candle contract. Only completed candles are eligible for analysis.

## Removed
- ICT engine and its dedicated analyzers/pipeline.
- ICT-specific configuration.
- ICT-specific EGX backtest adapter.
- ICT-only unit tests and CI workflow.
- ICT report and scanner UI wording/output assumptions.

## Preserved
- Binance Spot market-data client and crypto analysis queue.
- Thndr API client, token capture, EGX universe, watchlists and candle synchronization.
- Gemini as an independent advisory analyst.
- AI Review provider abstraction and validation.
- Crypto order-book execution-cost estimation.
- Reports, opportunity/evidence persistence, audit events and EGX scan history.

## EGX historical replay
The EGX backtest now evaluates the shared strategy chronologically. A signal is evaluated using candles available at that decision timestamp; simulated entry occurs at the next entry candle open. Position size is derived from a per-trade equity risk budget and includes estimated stop slippage plus round-trip fees. Fees and slippage are explicit per-side assumptions. Same-bar stop/target ambiguity resolves stop-first. Results include quantity, PnL, trade count, win rate, final equity, net return and maximum drawdown.

Limitations: this is an OHLCV simulation, not exchange-confirmed fills. Historical order-book depth, queue priority, corporate actions and complete EGX session-calendar reconstruction are not modeled.

## Research and safety constraints
- Thresholds and scores are strategy hypotheses, not calibrated probabilities.
- Backtest completion does not imply profitability.
- AI is advisory and cannot override deterministic levels or decisions.
- No exchange order submission is enabled.
- Do not deploy publicly until API authentication and watchlist ownership controls are added.

## Verification status
The GitHub changes are being authored on the isolated working branch. A local PHP runtime and Composer dependencies are not available in this execution environment, and GitHub's read/write connector cannot execute the Laravel test suite. Therefore PHP lint, migrations, full tests, live Binance/Thndr smoke tests and chart-by-chart validation are **not yet verified**. No passing test claim or profitability claim is made.
