# Testing and Validation

Status: Required standards for the local MVP and later strategy approval.

## 1. Two separate validation gates

### Gate A — Technical local prototype
Proves the application fetches and validates data, classifies regimes, evaluates baseline scenarios, stores results, and renders three separate reports reliably.

### Gate B — Trading-edge validation
Proves a strategy has repeatable positive net expectancy on chronological out-of-sample data under realistic execution costs and then survives paper trading. Passing Gate A does not imply Gate B.

## 2. Data tests

- Binance symbol eligibility and USDT Spot filtering.
- API timeout, rate-limit, server-error, and malformed-response handling.
- Candle completeness, duplicates, gaps, timestamp ordering, and interval mapping.
- Closed vs forming candle status.
- Receive-time and exchange-time provenance.
- Decimal precision and symbol tick/lot filters.
- No future candle leakage when aligning timeframes.

## 3. Market-regime tests

- BULLISH, BEARISH, RANGING, and UNCERTAIN direction fixtures.
- LOW, NORMAL, and HIGH volatility fixtures.
- TRENDING, CHOPPY, TRANSITION, and UNCERTAIN behavior fixtures.
- Orthogonal combinations such as BULLISH + HIGH volatility.
- Conflicting feature and timeframe evidence.
- Insufficient history and missing data.
- Stable deterministic output for identical inputs/configuration.

## 4. Strategy and risk tests

- Scenario setup/confirmation/invalidation.
- Entry-zone geometry.
- Stop and target ordering.
- Conservative reward/risk after both-leg costs.
- Ask-side entry and bid-side exit assumptions.
- Maximum entry distance and expiry.
- Duplicate candidates and state transitions.
- Hard gates cannot be overridden by a score or AI output.
- NO_OPPORTUNITY is a valid successful result.

## 5. Application tests

- Create analysis run from dashboard.
- Database queue worker state transitions.
- Duplicate click/idempotency protection.
- Provider failures and bounded retry behavior.
- Partial symbol failures do not corrupt other symbols or the whole run.
- Three independent report views and historical run navigation.
- Audit trail immutability.
- Secrets never appear in logs or persisted payloads.

## 6. Trading-edge validation protocol

For each scenario family independently:
- define the hypothesis and parameters before test;
- split data chronologically into train, validation, and untouched test;
- use walk-forward evaluation;
- purge/embargo overlapping labels or holding windows where needed;
- include both-leg fees, spread, slippage, market impact, latency, and missed fills;
- simulate bid/ask execution rather than candle-close fills;
- report net expectancy, profit factor, drawdown, win rate with sample size, MAE/MFE, time in trade, false positives, and missed opportunities;
- break down by symbol, timeframe, profile, and regime;
- run threshold sensitivity and feature ablation;
- account for multiple testing and backtest overfitting;
- retain failed experiments.

Historical order-book replay is unavailable unless the relevant depth was captured historically. A current REST depth snapshot must not be used as a proxy for past depth.

## 7. Paper trading

Only after a strategy passes out-of-sample validation:
- run signals without placing orders;
- record signal timestamp and executable reference prices;
- measure entry delay, missed fills, slippage, stop/target outcomes, and operational failures;
- require a predeclared minimum observation period and trade count before any promotion decision.

No arbitrary profitability threshold should be selected after seeing the results.

## 8. Required reporting

Every validation report must state:
- dataset, venue, symbols, dates, and sampling frequency;
- strategy/config/model version;
- data-quality exclusions;
- execution and cost assumptions;
- number of trials and parameter variants;
- in-sample, validation, and untouched out-of-sample results;
- uncertainty/sample-size caveats;
- limitations and reproducible commands.
