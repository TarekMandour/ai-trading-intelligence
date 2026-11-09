# Market Analysis Methodology

Status: Research methodology for the local manual-analysis MVP. Strategy parameters and thresholds remain hypotheses until validated.

## 1. Objective

For each eligible Binance Spot USDT instrument, determine whether a sufficiently evidenced long Spot opportunity exists for one of three separate holding profiles: Scalping (1–30m), Intraday (30m–8h), or Swing (1–7d). The engine must abstain when evidence is weak, stale, conflicting, or uneconomic after costs.

## 2. Analysis order

1. Instrument and data eligibility.
2. Per-timeframe feature calculation.
3. Per-timeframe market-regime classification.
4. Multi-timeframe context and conflict assessment.
5. Scenario-family evaluation.
6. Entry, stop, target, cost, freshness, and risk checks.
7. AI evidence review for shortlisted candidates (advisory, structured, provider-neutral).
8. Deterministic final decision/risk gates.
9. Report persistence and audit.

A regime label is context, not a trade direction or signal. AI may interpret regime evidence and timeframe relationships but cannot replace the underlying classifier.

## 3. Market regime dimensions

### Direction
- BULLISH
- BEARISH
- RANGING
- UNCERTAIN

### Volatility
- LOW
- NORMAL
- HIGH

### Behavior
- TRENDING
- CHOPPY
- TRANSITION
- UNCERTAIN

These dimensions are orthogonal. A bullish market can have high volatility; a range can be low-volatility or high-volatility. Do not label every high-volatility market as trending or every sideways market as low-volatility.

## 4. Baseline evidence features

Research and implement transparent features first:
- swing structure: HH/HL, LH/LL, range boundaries, breaks and reclaims;
- normalized regression slope and directional movement;
- ADX and +DI/-DI;
- ATR percentage and rolling volatility percentile;
- realized volatility and Bollinger Band width percentile;
- path/range efficiency;
- candle body/wick, close location, and follow-through;
- relative volume where valid.

Each output records timeframe, lookback, unit, exact source candle range, calculation version, and data-quality state. No single indicator is a complete strategy.

## 5. Multi-timeframe profiles

Initial hypotheses, subject to testing:

| Profile | Context | Structure | Setup | Entry |
|---|---|---|---|---|
| Scalping | 1h | 15m | 5m | 1m |
| Intraday | 4h | 1h | 15m | 5m |
| Swing | 1w | 1d | 4h | 1h |

Preserve every timeframe's state and timestamp. Summarize alignment as ALIGNED, PARTIAL, CONFLICTED, or INSUFFICIENT and explain the conflict. Do not average away disagreement.

Use completed candles for stable context. Forming candles must be explicitly labeled and cannot be represented as completed confirmation. Higher-timeframe alignment must be backward-looking as of the decision timestamp to prevent look-ahead leakage.

## 6. Scenario families for baseline testing

- Trend continuation / pullback continuation.
- Breakout and retest.
- Range mean reversion.
- Momentum / volatility expansion.

Further research candidates: liquidity sweep/reclaim, structural reversal, order-flow imbalance, HMM/regime switching, and change-point detection. Do not implement all candidates in the first vertical slice.

Each scenario needs a formal versioned definition of inputs, setup, confirmation, invalidation, entry, stop, targets/exit, expiry, cost assumptions, and rejection reasons.

## 7. Decision outputs

- ENTERABLE: every required scenario, data, execution, freshness, and risk gate passes.
- WAIT: scenario is promising but an explicit confirmation/entry condition is missing.
- MISSED: entry has moved outside its allowed window.
- REJECTED: a hard gate or contradictory evidence fails.
- NO_OPPORTUNITY: no candidate passes the minimum threshold.

A candidate plan must include entry zone, stop/invalidation, target 1/2, conservative reward/risk after costs, estimated fees/spread/slippage, expiry, reference price/time, evidence, contradictions, and data-quality warnings.

## 8. Costs and execution assumptions

- Model entry on executable ask-side prices.
- Model exit on executable bid-side prices.
- Include fees on both legs, spread, and estimated slippage.
- Use current order-book snapshots only as current execution context, never as historical order flow.
- Reject a plan when data or cost assumptions are insufficient to establish positive conservative reward/risk.
- Do not imply guaranteed fills.

## 9. AI role

No AI API is required for the first deterministic baseline. Later, AI may summarize supplied evidence, compare bounded scenarios, and identify contradictions. It may not invent prices, calculate authoritative stops/targets, or override deterministic gates.

## 10. Research discipline

See docs/16-research-evidence-and-strategy-hypotheses.md. Test each scenario family independently with chronological splits, walk-forward evaluation, realistic costs, untouched out-of-sample data, threshold sensitivity, and ablations. Keep failed experiments. A score is not a success probability without calibration.

## 11. Sources

Research references and limitations are maintained in docs/16-research-evidence-and-strategy-hypotheses.md.
