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


## 7. Crypto scalping profile (MVP proposal)
The first research profile is regular crypto scalping, not ultra-scalping. A proposed timeframe hierarchy is:
- 1H: regime, broader momentum, and directional bias.
- 15m: market structure, swing points, and key levels.
- 5m: setup formation, pullback/breakout context, and confirmation.
- 1m: entry trigger, acceptable entry distance, and immediate invalidation monitoring.

The engine should use completed candles for stable context and may use live trade/book events for entry timing. Every decision must record whether its inputs were closed candles, forming candles, or live events. No historical backtest may pretend that an intrabar value was known before it actually occurred.

## 8. Scalping-specific execution filters
Before an opportunity can be actionable, evaluate:
- bid/ask spread relative to the planned stop and target distance
- expected fees for the relevant account/venue tier
- estimated slippage and available depth
- market impact for the intended notional size
- feed latency and age of the last trade/quote/book update
- volatility and abrupt spread expansion
- entry distance from the original planned zone
- venue status, trading restrictions, and symbol liquidity

A setup with positive chart evidence can still be rejected when transaction costs or execution quality erase its expected edge.

## 9. Scalping-specific scenario expiry
Short-lived opportunities need a short, explicit validity window. Store detected_at, discovery_price, valid_until, max_entry_distance, and the event/candle conditions that invalidate the setup. If price leaves the entry zone or the feed becomes stale, change the state to MISSED, EXPIRED, or REJECTED as appropriate; never keep distributing an obsolete ENTER NOW alert.

## 10. AI and latency
Do not call OpenAI for every tick or every subscriber. Deterministic streaming analysis should identify and gate candidates. Invoke AI only at bounded decision points where contextual synthesis is useful, with strict timeout and fallback behavior. A delayed AI response must not turn an expired entry into a current signal.

## 11. Spot-only interpretation
The MVP evaluates long spot opportunities. A short signal is not actionable in the approved product scope. Entry and exit planning must model buying at the executable ask side and selling at the executable bid side, with fees and slippage on both legs. Chart mid-price or last-trade price alone is not sufficient to estimate executable return.

The analysis output must disclose the reference venue and assumed fee tier. Where subscriber execution venue or fee tier differs, present the plan as an analytical reference and avoid implying an identical realized return.
