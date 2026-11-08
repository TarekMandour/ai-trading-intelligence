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

## 10. Approved MVP market-data scope — Spot
The first provider integration must cover centralized-exchange spot markets only. Required data: spot instrument metadata, trades, best bid/ask, order-book depth, 1m/5m/15m/1h OHLCV, and sufficient historical trades/candles for replay. No derivatives-specific feeds are required.

A provider/exchange must pass a commercial rights review for the intended SaaS use before production. The review must explicitly cover display of quotes/charts, derived analytics and signals, caching/retention, subscriber distribution, geographic restrictions, attribution, and any downstream data processor. API access or a public endpoint alone is not evidence of redistribution rights.


## 11. Binance Spot integration decision
- Use Binance Spot market data only for the MVP; no Binance derivatives endpoints or contract data are in scope.
- Use REST for exchange information, historical candle/trade backfill, initial depth snapshots, and recovery; use WebSocket streams for continuous trades, best bid/ask, and depth updates.
- Normalize symbol status and exchange filters (price tick, quantity step, minimum quantity/notional, precision, and applicable order constraints).
- Keep exchange event time and local receive time separately for every event. Do not substitute local arrival time for venue event time.
- Reconstruct order books using the documented snapshot/delta synchronization procedure and sequence checks. A gap invalidates the local book until resynchronized.
- The selected dynamic instrument universe is `top_liquid`; see `docs/13-top-liquid-universe-and-latency.md` for proposed ranking, gates, and service objectives.
- The selected quote-asset whitelist is still an explicit open decision; do not silently assume every quote currency is eligible.


## 12. Approved MVP quote-asset filter
Only Binance Spot symbols quoted in **USDT** are eligible for the MVP `top_liquid` universe. Normalize quote volume and order-book depth directly in USDT. Do not include other quote assets or synthesize converted liquidity values in the MVP. A future expansion requires an explicit decision and documented conversion, fee, and liquidity-comparability policy.


## 6. Approved Binance monitoring data contract

The MVP market monitoring path uses official Binance Spot public market-data interfaces:
- REST exchange information for instrument status and trading filters;
- REST klines/trades/aggregate trades for supported backfill;
- REST depth snapshots to initialize/rebuild a local book;
- WebSocket aggTrade, bookTicker, and diff-depth streams for live monitoring;
- validated 1m candles with deterministic aggregation to 5m, 15m, and 1h where appropriate.

Every normalized event preserves venue event time, trade time where supplied, local receive time, processing time, source identifier, stream, connection/session, symbol, schema version, provenance, and quality status. Use decimal-safe price/quantity handling and deterministic deduplication.

A local order book is valid only after snapshot/delta synchronization and continuous update IDs. Any sequence gap invalidates it until rebuilt. Historical depth is not assumed to be available retroactively; capture snapshots and deltas prospectively if depth-based replay is required.

Review current official Binance stream limits and connection lifecycle before implementation. Keep subscription management, sharding, planned connection rotation, backoff, and rate-limit handling configurable. Public endpoint accessibility does not grant SaaS redistribution rights.

Full stream contracts, recovery sequence, feature inputs, and acceptance criteria are in docs/14.
