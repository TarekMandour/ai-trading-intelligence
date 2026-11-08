# Market Analysis Methodology

## 1. Objective
Determine whether a market instrument presents a sufficiently evidenced, actionable trade scenario now or within a defined watch window. The engine must be able to abstain.

## 2. Analysis layers
### A. Data quality gate
Validate timestamp freshness, candle completeness, symbol mapping, session status, missing values, price continuity, and provider consistency. Critical data failures stop analysis.

### B. Market regime
Estimate probabilities or confidence across states such as trend, range, high/low volatility, momentum expansion, compression, and transition. These are estimates, not certain labels.

### C. Multi-timeframe context
Use higher timeframes for context and lower timeframes for setup/entry timing. Exact timeframe mapping depends on the selected market and trading style and remains open.

### D. Market structure and price action
Potential evidence includes swing highs/lows, HH/HL and LH/LL, break of structure, change of character, displacement, breakout/retest, failed breakout, liquidity sweep, rejection, and support/resistance zones. Definitions must be deterministic and testable.

### E. Volume and participation
Potential evidence includes relative volume, volume expansion/contraction, breakout participation, climax, and price-volume divergence. Interpret cautiously where volume is fragmented or unavailable.

### F. Liquidity and execution
Where reliable data exists, evaluate spread, depth, imbalance, trade flow, slippage, and market impact. Order-book snapshots are not proof of intent and may be spoofed or incomplete.

### G. Scenario evaluation
Initial scenario families for research: trend continuation, pullback, breakout/retest, reversal, mean reversion, momentum expansion, and liquidity sweep. This is a candidate library, not an approved production strategy set.

### H. Trade decision
Possible outcomes:
- ENTER NOW — all required conditions and risk gates pass.
- WAIT — promising setup, but confirmation or price location is not ready.
- MISSED — valid entry window has passed.
- REJECT — evidence, execution, or risk constraints fail.
- NO OPPORTUNITY — no sufficiently strong scenario exists.

## 3. Evidence and scoring
Keep separate measures for opportunity quality, confirmation, risk, and execution. Scores are comparative ranking metrics until calibrated. A strong opportunity score must not override a hard failure such as stale data, inadequate liquidity, unacceptable spread, or invalid stop distance.

## 4. AI role
AI may synthesize cross-layer evidence, compare competing scenario explanations, identify contradictions, and produce a concise structured rationale. AI must cite supplied evidence identifiers and distinguish facts from interpretation. It must not invent prices, infer unavailable order flow, or create missing candles.

## 5. Output contract
Every candidate decision should include instrument, market, direction, scenario, as-of time, source-data references, regime context, evidence, counter-evidence, entry timing, entry zone, stop/invalidation, targets/exit logic, max entry distance, expiry, risk/execution warnings, decision state, and analysis version.

## 6. Research discipline
All definitions and thresholds must be versioned. Evaluate chronologically with train/validation/test separation, walk-forward testing, out-of-sample evaluation, realistic fees/spread/slippage, and robustness checks. Do not optimize on the final test set.
