# Crypto Scalping — Data Requirements and Timeframe Proposal

Status: Phase 0 decision updated. Binance Spot is the selected MVP technical reference venue; the product remains Spot-only. Numeric liquidity and latency values remain provisional pending measurement.

## 1. MVP objective
Provide regular crypto scalping opportunities with timely entry timing, explicit invalidation, realistic transaction-cost checks, and lifecycle monitoring. The MVP does not target ultra-low-latency HFT or automatic order execution.

## 2. Proposed timeframe hierarchy
| Timeframe | Role | Typical questions |
|---|---|---|
| 1H | Market context | Is the broader intraday environment trending, ranging, volatile, or transitioning? |
| 15m | Structure | Where are meaningful swings, key levels, breakouts, and pullbacks? |
| 5m | Setup | Is a defined scenario forming and receiving confirmation? |
| 1m | Entry trigger | Is the entry currently executable, and is the invalidation still valid? |

### Operating rules
- Use UTC internally for timestamps; preserve venue event timestamps and receive timestamps.
- Distinguish completed candles from forming candles.
- Higher-timeframe context can update on candle close or material market events; do not force every lower-timeframe event to wait for a 1H close.
- Record the exact candle/event versions used for every decision.
- If a required timeframe is missing or stale, apply a defined degraded-mode or reject policy; do not silently fabricate data.
- The hierarchy is a testable hypothesis. Compare it with alternatives (for example, 4H/1H/15m/5m or 15m/5m/1m) using out-of-sample results and realistic costs before final approval.

## 3. Streaming and historical data
### Streaming fast path
- Trades/aggregated trades
- Best bid and ask
- Order-book snapshots and deltas where reliable
- Exchange event timestamp, receive timestamp, sequence ID where provided
- Heartbeats, connection status, reconnect and resynchronization events

### Historical/research path
- OHLCV candles at all selected resolutions
- Historical trades for event replay and fill simulation
- Historical order-book data where available; absence must be disclosed as a limitation
- Instrument metadata and symbol lifecycle history
- Fee schedule assumptions and changes over time

Candle-only historical data is insufficient to fully validate scalping execution quality. If historical trades/depth are unavailable, backtest claims must be limited and paper trading must be used to evaluate execution assumptions.

## 4. Data-quality checks
- stale feed and event/receive-time lag
- missing, duplicate, and out-of-order events
- WebSocket disconnects and sequence gaps
- malformed OHLC candles and inconsistent candle boundaries
- symbol precision, tick size, quantity step, and minimum notional
- crossed/locked or stale order book
- sudden spread/depth deterioration
- exchange maintenance, halts, delistings, and trading restrictions

## 5. Execution-cost model
Every candidate should be evaluated using the applicable maker/taker fees, bid/ask spread, expected slippage, depth at intended notional, market impact, and (for perpetuals) funding and contract mechanics. A signal is rejected if its expected move is too small relative to total execution costs or if the stop/target cannot be executed plausibly.

## 6. Proposed freshness dimensions
Track separately:
- market event age
- local receive lag
- last successful book update
- candle completion age
- analysis computation time
- signal publication time
- notification delivery time

Numeric service-level thresholds must be selected after choosing venue, instruments, infrastructure region, and trading style parameters.

## 7. Decisions still required
1. Spot or perpetual futures?
2. Which exchange/venue is the initial execution reference?
3. Which symbols and quote assets are in scope?
4. Long-only or long/short?
5. What is the intended maximum holding duration for a scalp?
6. Is reliable order-book depth mandatory for publication, or can some scenario types operate with trades + best bid/ask?
7. Which subscriber notification channels are required at launch?

## 8. Approved product boundary — Spot
The MVP is centralized-exchange spot only, with long/buy opportunities and sell exits. Margin, leverage, borrowing, futures, perpetuals, funding, and liquidation data are not required. Automated order execution and custody are out of scope. A future derivatives expansion requires a separate product and risk decision.

For realistic long spot execution estimates, use ask-side liquidity for entries and bid-side liquidity for exits. Include fees on both legs, spread, slippage, tick/step size, minimum notional, and reference venue fee tier.


## 9. Selected MVP venue and universe
- Reference venue/feed: Binance Spot.
- Eligible universe policy: dynamic `top_liquid` active Spot pairs.
- The target cohort is configurable; 30–50 pairs is a planning range, not a final fixed count.
- Quote assets, exact cohort size, and final thresholds remain subject to explicit approval and empirical validation.
- Selection must consider stable quote-volume, spread, executable depth, slippage, trade activity, instrument filters, and stream health—not 24h volume or market cap alone.
- Binance commercial use and subscriber distribution rights remain a hard production gate.

## 10. Provisional liquidity and latency policy
See `docs/13-top-liquid-universe-and-latency.md` for the complete proposed two-stage selection process, numerical starting hypotheses, event freshness objectives, universe refresh cadence, and validation plan. These values are initial test parameters only; they are not exchange guarantees or proven profitable thresholds.
