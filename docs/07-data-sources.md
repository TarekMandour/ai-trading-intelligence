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


## 8. Crypto scalping minimum data specification (proposed)
### Required baseline
- Instrument metadata: symbol, base/quote assets, precision, tick size, lot/step size, minimum notional, trading status, product type.
- Real-time trades or aggregated trades with exchange event time and local receive time.
- Best bid and ask (or equivalent top-of-book quote) with timestamps.
- OHLCV for 1m, 5m, 15m, and 1h, with clear candle start/end and closed/forming status.
- Historical candles and trades sufficient for warm-up, research, and realistic replay.
- Venue trading calendar/status and maintenance/degraded-state information where available.

### Strongly preferred, subject to provider support
- Order-book snapshot and incremental depth updates with sequence identifiers.
- Multiple depth levels sufficient to estimate liquidity and slippage.
- Mark price, index price, funding rate, open interest, and liquidation-related data only if perpetual derivatives are selected.
- Historical trades/order-book data for execution simulation; candle-only backtests are not sufficient to validate scalping fills.

### Data freshness and integrity
Store both source event time and receive time. Monitor lag, heartbeat, disconnect/reconnect, missing sequence ranges, out-of-order messages, duplicate events, crossed/locked books, invalid prices/quantities, and stale quotes. Rebuild or resynchronize the book after a sequence gap; do not use an uncertain book for liquidity decisions.

### Derived timeframe policy
Prefer provider-native candles where their semantics are documented and validated. If candles are built from trades, define interval boundaries, timezone (UTC internally), aggregation rules, missing-interval handling, and candle finalization. Do not mix candle sources without reconciliation.

### Commercial constraints
Before launch, confirm exchange/vendor terms permit commercial use and redistribution of alerts or derived analytics. Raw market-data redistribution may require separate licensing. A technically accessible public API is not proof of commercial redistribution rights.

## 9. Provider evaluation for the MVP
Evaluate candidate venues/providers against WebSocket stability, trade/quote latency, depth quality, historical trades and candles, rate limits, instrument coverage, symbol lifecycle, fee assumptions, outage behavior, geographic restrictions, API stability, commercial licensing, and total cost. The chosen venue must match where the target subscriber can realistically execute.
