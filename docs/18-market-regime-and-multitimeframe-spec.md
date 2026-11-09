# Market Regime and Multi-Timeframe Specification

Status: First implementation contract for the local research baseline. Feature thresholds remain configurable hypotheses and must be validated.

## 1. Purpose

Describe market behavior at each timeframe before any strategy is evaluated. The classifier must not emit a trade signal. It supplies evidence and context to independent Scalping, Intraday, and Swing evaluators and to the advisory AI review stage.

## 2. Orthogonal regime dimensions

### 2.1 Direction
- BULLISH: evidence of upward directional structure/momentum.
- BEARISH: evidence of downward directional structure/momentum.
- RANGING: no sufficiently persistent directional movement; price remains within a defined balance/range.
- UNCERTAIN: conflicting or insufficient evidence.

### 2.2 Volatility
- LOW: realized volatility/range is low relative to its rolling historical distribution.
- NORMAL: within the ordinary reference band.
- HIGH: realized volatility/range is elevated relative to its rolling historical distribution.

### 2.3 Behavior
- TRENDING: directional efficiency and structure persistence are high enough.
- CHOPPY: frequent reversals / low path efficiency / weak follow-through.
- TRANSITION: evidence of a recent state change, break from balance, or deterioration of the prior regime.
- UNCERTAIN: insufficient data or conflicting behavior features.

A state may therefore be BULLISH + HIGH + TRENDING, BEARISH + LOW + TRENDING, or RANGING + HIGH + CHOPPY. Do not force volatility into the directional label.

## 3. Baseline feature set

Calculate from completed OHLCV candles unless explicitly marked as forming:
- swing highs/lows and HH/HL/LH/LL sequence;
- normalized regression slope over configurable lookback;
- ADX and +DI/-DI;
- ATR as percentage of close and rolling ATR percentile;
- realized volatility from log returns and rolling percentile;
- Bollinger Band width and percentile;
- path efficiency / Kaufman Efficiency Ratio;
- range position and breakout distance;
- recent structure break/reclaim and candle close location.

Every feature stores timeframe, lookback, value, unit, input candle range, calculation version, and quality status.

## 4. Classification contract

- Use a transparent rules-based baseline with explicit, versioned thresholds.
- Do not select thresholds from the final holdout.
- Use a neutral/uncertain zone around boundaries to reduce unstable label flipping.
- If features materially disagree, classify UNCERTAIN rather than force a majority label.
- Do not use HMM, deep learning, or generative AI as a dependency for the initial classifier. A separate AI review may interpret the classifier's output but must not replace or rewrite it.
- Return evidence/reason codes for every label.
- Return a confidence/evidence score only as a ranking or diagnostic score, not a calibrated probability.
- Missing or stale candles make the timeframe classification unavailable; do not fill gaps with future information.

## 5. Multi-timeframe profiles

Initial profiles:

| Profile | Context timeframe | Structure timeframe | Setup timeframe | Entry timeframe |
|---|---|---|---|---|
| Scalping | 1h | 15m | 5m | 1m |
| Intraday | 4h | 1h | 15m | 5m |
| Swing | 1w | 1d | 4h | 1h |

These are initial configurations for testing. Each timeframe classification is preserved individually. The profile summary reports:
- higher-timeframe direction and volatility;
- setup-timeframe direction and volatility;
- entry-timeframe direction and volatility;
- alignment state: ALIGNED, PARTIAL, CONFLICTED, or INSUFFICIENT;
- explicit conflict description;
- latest fully closed candle timestamp per timeframe.

Do not calculate a single average direction score that hides disagreement. A lower timeframe reversal against a higher timeframe trend should be described as a possible pullback or transition, not automatically treated as a new trend.

## 6. Candle alignment and no-look-ahead rules

- Use exchange candle open/close times and local receive timestamps.
- Mark candles CLOSED or FORMING.
- A higher-timeframe candle is usable as a completed observation only after its close time has passed and the data has been received.
- When aligning higher-timeframe context to lower-timeframe rows, join backward to the last completed higher-timeframe candle; never forward-fill a future candle.
- Preserve gaps and duplicates as data-quality errors; do not silently synthesize missing OHLCV.
- Record all feature windows and the exact last input candle.

## 7. Test requirements

Unit tests must cover:
- bullish/bearish/ranging/uncertain structure examples;
- low/normal/high volatility using controlled fixtures;
- orthogonality (e.g. bullish-high and bearish-low are valid);
- choppy vs trending behavior;
- transition/uncertain boundaries;
- insufficient history and missing candles;
- timeframe alignment around candle close boundaries;
- no future higher-timeframe data leakage;
- deterministic output for identical input and config version.

Integration tests must verify that each of the three profile reports contains its own multi-timeframe context and does not reuse another profile's entry timeframe by mistake.

## 8. Future research

Compare the baseline against HMM/regime-switching models, clustering, change-point detection, and supervised classifiers only after a reliable historical dataset and out-of-sample evaluation harness exist. Compare classification stability, detection delay, false transitions, and incremental net trading value—not classification accuracy alone.
