# Research Evidence and Strategy Hypotheses

Status: Research baseline for the local MVP. No strategy in this document is approved as profitable or production-ready.

## 1. Research objective

Build a testable evidence base for three independent Spot-long opportunity reports:

- Scalping: intended holding window 1–30 minutes.
- Intraday: intended holding window 30 minutes–8 hours.
- Swing: intended holding window 1–7 days.

The research question is not "which indicator gives BUY?" It is whether identifiable market conditions and scenario families provide repeatable, executable net edge on Binance Spot USDT pairs after fees, spread, slippage, latency assumptions, and missed fills.

Research from equities, other venues, other sampling frequencies, or cross-sectional horizons is informative but not directly transferable. Every finding is a hypothesis until reproduced on the project's own data.

## 2. Evidence hierarchy

Classify sources and claims:

1. Peer-reviewed empirical research directly studying cryptocurrency markets.
2. Peer-reviewed market-microstructure research in other asset classes; use only to motivate features, not to claim crypto profitability.
3. Working papers/preprints and independent backtests; exploratory only.
4. Practitioner claims and vendor material; do not use as evidence of an edge without independent replication.

For each strategy experiment record source, sample, venue, instrument, horizon, data frequency, transaction-cost assumptions, out-of-sample design, limitations, and whether results were independently replicated.

## 3. Findings that shape the research plan

### 3.1 Technical trading rules: mixed evidence and data-snooping risk

Hudson and Urquhart examine nearly 15,000 technical trading rules across Bitcoin markets and other major cryptocurrencies. They report predictability/profitability across rule classes and apply multiple-hypothesis procedures. This motivates systematic rule-family testing, but the study period, venues, costs, and market structure differ from the intended live Binance Spot implementation.

Anghel's 2021 reality-check study finds that statistically significant positive excess returns become rare after controlling for data snooping and market frictions. This is a critical counterweight: large strategy searches can produce attractive in-sample results that do not survive realistic evaluation.

Implementation implication: retain failed variants, limit parameter search, correct for multiple testing where appropriate, and require untouched chronological out-of-sample evidence.

### 3.2 Intraday momentum and reversal are conditional

Wen, Bouri, Xu, and Zhao (2022) report both intraday momentum and reversal in cryptocurrency data. Their results vary with price jumps, liquidity, macro announcements, and market conditions.

Implementation implication: momentum and reversal must be conditional scenario families, not always-on rules. Market regime and liquidity context must be evaluated before a setup is considered.

### 3.3 Market regimes and hidden states

Figà-Talamanca, Focardi, and Patacca (2021) apply Hidden Markov Models to cryptocurrency price dynamics and report regime changes and commonality across selected cryptoassets, with regimes associated with different volatility states. Abu Hashish (2020) also studies HMM regime changes in cryptoasset markets.

Implementation implication: regime classification is a legitimate research problem. However, HMM is not automatically the best first implementation. A transparent deterministic baseline should be implemented first and used as a benchmark before any HMM/ML model is considered.

### 3.4 Order flow and market microstructure

Kolm, Turiel, and Westray (2023) find that order-flow-derived inputs can be useful for high-frequency forecasting in Nasdaq equities. This motivates investigating order flow, but the asset class is equities and the result must not be presented as proof of an edge in crypto.

Anastasopoulos et al. (2026) study world order flow and cryptocurrency returns and report predictive information in cross-sectional cryptocurrency returns. The scope is not equivalent to intraminute Binance Spot execution.

Implementation implication: trade-flow and order-book features belong in later experiments only when reliable, time-aligned data can be captured. A single order-book snapshot or imbalance must never be treated as a trade signal.

### 3.5 Transaction costs and execution-aware decisions

Recent working-paper evidence on hourly BTC/USDT forecasting emphasizes that gross forecasts may fail after transaction costs and that cost-aware trade filters can materially change results. As a working paper, it is exploratory, but its methodological warning is sound and consistent with the wider literature.

Implementation implication: every candidate plan must be assessed on executable ask-side entry and bid-side exit assumptions, both-leg fees, spread, estimated slippage, and whether the remaining conservative target move exceeds costs.

## 4. Strategy families to research

These are candidate families, not approved production rules:

| Family | Core hypothesis | Most relevant profiles | Main failure modes |
|---|---|---|---|
| Trend continuation | Directional structure and persistent momentum continue | Intraday, Swing; Scalping when aligned | Late entry, regime transition |
| Pullback continuation | Retracement into a structural/volatility zone resumes the dominant move | All | Pullback becomes reversal |
| Breakout and retest | Price accepts beyond a range/structure level, then retests | All | False breakout, thin liquidity |
| Momentum expansion | Volatility and participation expand together | Scalping, Intraday | Exhaustion, spread expansion |
| Range mean reversion | Price reverts inside a stable range | Scalping, Intraday | Range breaks and regime changes |
| Liquidity sweep and reclaim | Price takes a prior extreme then reclaims it with follow-through | Scalping, Intraday | Apparent sweep without confirmation |
| Structural reversal | Market structure changes after exhaustion or failed continuation | Intraday, Swing | Countertrend entry too early |
| Volatility compression breakout | Low realized volatility/range compression precedes expansion | Intraday, Swing | Persistent compression / false break |

Each family must have a formal, versioned specification: required inputs, observation windows, setup definition, confirmation, entry, stop/invalidation, targets/exit, expiry, hard gates, and reason codes.

## 5. Market regime research dimensions

Do not collapse market state into one label. Estimate independent dimensions:

### Directional state
- BULLISH
- BEARISH
- RANGING
- UNCERTAIN

### Volatility state
- LOW / COMPRESSION
- NORMAL
- HIGH / EXPANSION

### Behavior/phase state
- TRENDING
- CHOPPY
- TRANSITION
- UNCERTAIN

Possible transparent baseline features:
- swing sequence and HH/HL vs LH/LL;
- normalized regression slope;
- ADX / directional movement;
- ATR and realized-volatility percentiles;
- Bollinger Band width percentile;
- range efficiency / Kaufman Efficiency Ratio;
- price displacement and close location;
- persistence and change-point flags.

No single feature is authoritative. Volatility is orthogonal to direction: bullish/high-volatility and bearish/low-volatility are both valid combinations.

## 6. Multi-timeframe research hypotheses

Initial timeframe sets to test, not hard-coded final rules:

| Profile | Context | Structure | Setup | Entry |
|---|---|---|---|---|
| Scalping | 1H | 15m | 5m | 1m |
| Intraday | 4H | 1H | 15m | 5m |
| Swing | 1W / 1D | 1D / 4H | 4H | 1H |

For each profile, record per-timeframe directional state, volatility state, confidence, candle completion status, and data timestamp. Preserve conflicts explicitly. Do not average conflicting timeframes into one opaque score. The lower timeframe may provide an entry trigger, but it cannot silently rewrite higher-timeframe context.

Higher-timeframe candles must be aligned using only information available at the decision timestamp. Never forward-fill a future completed higher-timeframe candle into earlier lower-timeframe rows.

## 7. Experiment design

Run each family independently before combining them.

- Use chronological train/validation/test splits.
- Use walk-forward evaluation; reserve an untouched final holdout.
- Purge/embargo overlapping labels and holding windows where necessary.
- Include fees on both legs, spread, slippage, market impact, latency, and missed fills.
- Use bid/ask execution simulation; candle-close fills are not sufficient.
- Report results by profile, regime, symbol, scenario, and volatility bucket.
- Run threshold sensitivity and ablation tests.
- Account for multiple testing / backtest overfitting.
- Retain negative and inconclusive experiments.
- Never infer historical order-book state from a current REST snapshot.

## 8. Required metrics

- Number of trades and opportunity frequency.
- Net expectancy in quote currency and R-multiples.
- Profit factor.
- Win rate, with sample size and confidence intervals.
- Maximum drawdown and return distribution.
- MAE / MFE and time-to-target / time-to-stop.
- Entry delay, missed-fill rate, and adverse selection.
- Net results after both-leg fees, spread, and slippage.
- Results by market regime and profile.
- Calibration and drift metrics for any probability output.

A score is not a success probability unless explicitly calibrated and validated out-of-sample.

## 9. Sources

- Hudson, R. & Urquhart, A. (2021), "Technical trading and cryptocurrencies", Annals of Operations Research. https://doi.org/10.1007/s10479-019-03357-1
- Anghel, D.-G. (2021), "A reality check on trading rule performance in the cryptocurrency market: Machine learning vs. technical analysis", Finance Research Letters. https://doi.org/10.1016/j.frl.2020.101655
- Wen, Z., Bouri, E., Xu, Y. & Zhao, Y. (2022), "Intraday return predictability in the cryptocurrency markets: Momentum, reversal, or both", The North American Journal of Economics and Finance. https://doi.org/10.1016/j.najef.2022.101733
- Figà-Talamanca, G., Focardi, S. & Patacca, M. (2021), "Regime switches and commonalities of the cryptocurrencies asset class", The North American Journal of Economics and Finance. https://doi.org/10.1016/j.najef.2021.101425
- Abu Hashish, I. (2020), "A hidden Markov model to detect regime changes in cryptoasset markets", Quality and Reliability Engineering International. https://doi.org/10.1002/qre.2673
- Kolm, P. N., Turiel, J. & Westray, N. (2023), "Deep order flow imbalance: Extracting alpha at multiple horizons from the limit order book", Mathematical Finance. https://doi.org/10.1111/mafi.12413 (equities study; feature motivation only)
- Anastasopoulos, A. et al. (2026), "Order flow and cryptocurrency returns", Journal of Financial Markets. https://doi.org/10.1016/j.finmar.2026.101047

## 10. Research limitations

These papers use different sample periods, venues, assets, frequencies, and transaction-cost models. None establishes a profitable Binance Spot long-only system for the project's exact holding windows. Published results motivate hypotheses; only reproducible project-specific testing can determine whether a family is retained.
