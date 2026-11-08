# Market Data Sources and Quality

## 1. Provider selection is open
No exchange, broker, vendor, or API is approved yet. Selection must follow the first-market decision and a documented coverage test.

## 2. Required data capabilities
- Instrument reference data and symbol mapping
- OHLCV candles at approved timeframes
- Real-time or near-real-time price updates appropriate to the trading style
- Trading calendar, sessions, holidays, and market status
- Corporate actions/adjustments where relevant
- Spread and order-book depth if liquidity analysis requires them
- Historical data sufficient for robust backtesting
- Clear licensing and redistribution rights for commercial SaaS use

## 3. Data provenance
For every observation retain provider, provider instrument ID, normalized instrument ID, event timestamp, receive timestamp, timeframe, adjustment mode, and quality flags.

## 4. Quality controls
Check missing intervals, duplicate/out-of-order events, invalid OHLC relationships, zero/negative prices where invalid, volume anomalies, timestamp drift, session boundaries, symbol changes, and feed latency.

## 5. Provider evaluation
Compare coverage, latency, historical depth, reliability, rate limits, WebSocket support, adjustment quality, corporate-action handling, licensing, redistribution permissions, support, and total cost.

## 6. Failure policy
If required data is stale, incomplete, or inconsistent, suppress new signals dependent on it. Do not fill critical gaps with model guesses. Continue only those monitoring functions whose inputs remain trustworthy.

## 7. Commercial and legal constraint
A provider's API availability does not automatically grant the right to redistribute raw data or derived data. Confirm exchange/vendor licensing before subscriber distribution.
