# Testing and Validation Standard

## 1. Validation layers
1. Data contract and normalization tests
2. Unit tests for features, structure, risk gates, and state transitions
3. Integration tests for provider adapters, queues, persistence, and notifications
4. Historical simulation/backtesting
5. Walk-forward and out-of-sample evaluation
6. Paper trading / shadow mode
7. Controlled production rollout with monitoring

## 2. Backtest integrity
- Use chronological splits; never randomly shuffle time-series observations.
- Avoid look-ahead and survivorship bias.
- Account for fees, spread, slippage, liquidity, latency, and realistic fills.
- Handle corporate actions and symbol delistings where applicable.
- Avoid using revised or future data unavailable at decision time.
- Preserve all strategy/model versions and parameter sets.
- Correct for multiple testing and selection bias when many hypotheses are explored.

## 3. Metrics
Evaluate expectancy after costs, win/loss distribution, profit factor, maximum drawdown, tail loss, exposure/time in market, trade frequency, missed opportunities, false positives, calibration, and stability across regimes. Metrics and thresholds must be chosen before final evaluation.

## 4. Operational tests
Measure feed freshness, end-to-end signal latency, analysis failure rate, AI latency/cost, notification delay, duplicate delivery, retry behavior, and recovery after provider/channel outage.

## 5. Promotion gates
No research strategy or model version is promoted based only on in-sample results. Require out-of-sample evidence, paper/shadow results, risk review, and explicit approval.

## 6. Reproducibility
A historical decision must be reproducible from stored data references, code/rule version, configuration, and model/prompt version where applicable.
