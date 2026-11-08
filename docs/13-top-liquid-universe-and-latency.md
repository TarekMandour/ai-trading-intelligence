# Top-Liquid Universe, Liquidity Gates, and Latency Objectives

Status: Phase 0 proposal for measurement and approval. Binance Spot is the selected MVP technical reference venue. The numeric values below are starting hypotheses, not validated trading edges, exchange guarantees, or final production SLOs.

## 1. Purpose and policy

The MVP universe is `top_liquid`: a dynamic set of active Binance Spot pairs selected for executable liquidity and reliable data, not simply market capitalization or reported 24-hour volume.

The universe must be:
- **Eligible:** active Spot instrument with supported quote asset and valid exchange filters.
- **Liquid:** enough consistent traded value and executable depth for the configured trade size.
- **Cost-efficient:** spread, expected slippage, and round-trip fees do not consume an excessive share of the expected move.
- **Observable:** real-time trades, best bid/ask, and synchronized depth are healthy enough for the intended analysis.
- **Stable:** membership changes do not churn excessively in response to brief volume spikes.

A pair can rank highly by volume and still be rejected for wide spread, shallow depth, erratic trading, poor data quality, or unacceptable execution cost.

## 2. Universe construction — two stages

### Stage A: eligibility and hard exclusions

Exclude a symbol when any of the following applies:
- It is not a currently trading Binance Spot symbol.
- Its status is halted, suspended, pending delisting, or otherwise not eligible for normal Spot trading.
- It is a leveraged token or a product outside the approved Spot scope.
- Required price/quantity filters or minimum notional cannot be normalized.
- It has insufficient history for the configured stability window (initially 7 complete days; new listings may enter a separately monitored probation pool).
- Its quote asset is not on the explicitly approved quote-asset whitelist.
- Critical market data is stale, malformed, contradictory, or cannot be synchronized.
- Its expected execution costs exceed the configured scenario's cost budget.

**Approved MVP quote-asset whitelist: USDT only.** Other quote assets are excluded from the MVP universe unless a future documented decision expands the scope.

### Stage B: liquidity ranking and cohort selection

Rank the eligible symbols using a rolling score built from:
1. 7-day quote-volume consistency and 24-hour quote volume.
2. Median and tail bid/ask spread.
3. Executable depth on the ask side for buys and bid side for exits.
4. Estimated market impact/slippage for the configured notional.
5. Trade count/activity and continuity (avoid isolated prints).
6. Feed health, event lag, book synchronization, and data completeness.

Use normalized percentiles or robust ranks within the eligible universe rather than adding raw USD volume, basis points, and latency values directly. Hard safety gates always take precedence over rank.

Initial cohort capacity planning: **30–50 pairs**, configurable. This is not a fixed commitment; the final number depends on measured stream capacity, processing budget, depth quality, and signal quality.

## 3. Provisional starting thresholds

All values are initial experiment parameters. They must be measured by quote asset, time of day, volatility regime, and intended order notional before final approval.

| Dimension | Initial test threshold | Purpose |
|---|---:|---|
| 24h quote volume | At least 10 million USDT-equivalent | Remove very thin markets |
| 7-day stability | Median daily quote volume at least 8 million USDT-equivalent; at least 5 of 7 days above 5 million | Avoid one-day volume spikes |
| Median spread | At or below 5 bps (0.05%) | Keep entry/exit friction bounded |
| Spread tail | 95th percentile at or below 10 bps during liquid observation windows | Avoid frequently widening markets |
| Emergency spread exclusion | Suppress new entries if spread exceeds 15 bps for 60 consecutive seconds | Avoid abnormal execution conditions |
| Ask depth for entry | At least 20× the 500 USDT reference notional = **10,000 USDT** within 10 bps of best ask | Provide buy-side executable liquidity |
| Bid depth for exit | At least 20× the 500 USDT reference notional = **10,000 USDT** within 10 bps of best bid | Provide sell-side executable liquidity |
| Estimated slippage | At or below 5 bps per side at configured order notional | Bound market impact |
| All-in round-trip cost | Fees on both legs + spread + estimated slippage must consume no more than 25% of the scenario's conservative gross Target 1 move | Ensure the opportunity is not mostly transaction costs |
| Minimum observation history | 7 complete days for normal ranking | Reduce new-listing and short-history bias |

**bps** means basis points (1 bp = 0.01%). All MVP pairs are USDT-quoted, so the volume and depth thresholds are denominated directly in USDT; no quote-asset conversion is needed for the initial universe. A pair that fails a hard gate is excluded regardless of its composite rank.

Depth should be computed from the actual order-book levels required to fill the configured notional, not from a single displayed depth number. Entry estimates consume asks; exit estimates consume bids. Depth must be measured at a defined distance from the current best price and updated from a synchronized book.

The approved **reference notional for universe qualification is 500 USDT**. The 20× depth ratio therefore means at least 10,000 USDT of executable ask depth and 10,000 USDT of executable bid depth, each within 10 bps of the respective best price. The 500 USDT figure is a liquidity-testing benchmark, not a required subscriber order size or a recommendation to trade that amount. The 20× ratio and 5 bps slippage limit remain provisional hypotheses and must be stress-tested at smaller and larger notionals.

## 4. Trade activity and market behavior checks

Do not use 24-hour volume as the only activity test. Record:
- Trades per minute and quote volume per minute distributions.
- Median, p90, and p99 inter-trade intervals.
- Volume concentration by hour and day.
- Frequency and duration of spread widening.
- Depth replenishment/depletion and book imbalance stability.
- Price gaps, abrupt volatility spikes, and abnormal prints.

The minimum trade-rate gate should be calibrated from observed data rather than fixed now. A high trade count is not sufficient if the book is shallow or spread is wide.

## 5. Freshness, stream health, and latency objectives

Record separate timestamps for venue event time, local receive time, normalization completion, feature update, decision completion, publication, and notification delivery. Measure distributions (p50/p95/p99), not only averages.

Provisional objectives for the technical pilot, measured from a healthy production-like region and clock-synchronized hosts:

| Metric | Initial pilot objective | Failure behavior |
|---|---:|---|
| Trade/quote event receive lag | p95 ≤ 500 ms; p99 ≤ 1,500 ms | Mark feed degraded; suppress new entries when stale |
| Best bid/ask age | ≤ 1,000 ms for entry eligibility | Do not use stale quote for entry or exit estimate |
| Synchronized order-book update age | ≤ 1,000 ms during active market conditions | Depth-dependent decisions disabled until resync |
| Internal event-to-feature/decision processing | p95 ≤ 250 ms after event receipt | Alert on breach; avoid publishing late entry |
| Signal decision-to-publish | p95 ≤ 500 ms after decision | Track queue/backpressure and suppress expired opportunities |
| WebSocket recovery | Reconnect and resynchronize automatically; no fixed recovery promise until tested | Mark affected symbols unhealthy until snapshot/delta sequence is valid |
| Clock offset | Monitor continuously; keep within 100 ms in pilot | Flag timestamps and exclude unreliable latency measurements |

These are pilot objectives, not Binance service-level commitments. Internet routing, server region, host load, stream fan-out, and exchange behavior affect observed latency. Do not claim sub-millisecond or HFT capability.

A signal's usable lifetime is separate from feed latency. The opportunity engine must recalculate maximum entry distance and expiry; a technically fresh feed does not make a stale trade plan valid.

## 6. Universe refresh and anti-churn rules

Proposed initial policy:
- Recompute liquidity metrics continuously from rolling windows.
- Re-rank membership every 15 minutes.
- Use 24-hour and 7-day windows for volume stability; use shorter rolling windows for spread, depth, and feed health.
- Require a candidate to pass hard gates for a sustained observation period before promotion (initial test: 30 minutes).
- Use a lower removal threshold than promotion threshold (hysteresis) and a minimum membership hold (initial test: 60 minutes), except for hard safety exclusions.
- Remove or quarantine immediately for trading-status changes, stale/invalid feed, unsynchronized book, or severe cost deterioration.
- Persist every universe version and each symbol's inclusion/exclusion reason and effective timestamp.
- Do not retroactively change the universe used by a historical signal or backtest run.

The exact refresh cadence and hysteresis must be load-tested against Binance stream limits and observed symbol churn.

## 7. Feed architecture and capacity

- Use WebSocket streams for live trades, best bid/ask, and required depth updates.
- Use REST for exchange metadata, historical backfill, initial depth snapshots, and recovery—not polling as a replacement for the live feed.
- Follow Binance's current documented connection, subscription, message-rate, request-weight, and depth synchronization limits. Limits must be configuration/documentation-driven, not hard-coded from assumptions.
- Shard stream subscriptions across connections where needed; monitor connection health, ping/pong, reconnects, subscription acknowledgements, and per-symbol freshness.
- Apply backpressure and bounded queues. A delayed consumer must not silently process stale market events as current.
- On a depth sequence gap, discard the local book and rebuild from a fresh snapshot plus subsequent deltas before re-enabling depth-based decisions.
- Keep a broader candidate monitoring layer separate from the smaller intensive analysis universe, if stream and compute measurements justify it.

## 8. Decision outputs and auditability

For each universe evaluation, persist:
- evaluation timestamp and effective-from timestamp;
- source/venue and universe-policy version;
- candidate symbol and instrument metadata version;
- quote-volume windows and metrics;
- spread and depth observations, with measurement timestamps;
- configured reference notional and estimated slippage;
- feed-health and latency metrics;
- eligibility decision, rank/score, and inclusion/exclusion reason.

Every published signal must retain the universe membership and market-data context that existed when it was generated. Universe membership is not a guarantee that a symbol is always tradeable.

## 9. Validation plan before finalizing thresholds

1. Collect at least 2–4 weeks of Binance Spot observations across quiet, active, and volatile conditions.
2. Evaluate a broad candidate set, not only the pairs that already rank highly.
3. Replay trades and order-book data where available; quantify spread, depth, and slippage at multiple reference notionals.
4. Compare candidate cohort sizes (for example 20, 30, 50, 75) for coverage, signal count, feed load, and execution quality.
5. Test threshold sensitivity and stability; inspect false inclusions/exclusions and turnover.
6. Run paper trading with realistic fees and latency before claiming scalping viability.
7. Approve final values through a documented Phase 0 decision update; do not silently promote pilot values to production.

## 10. Open decisions
- Future expansion beyond the approved MVP quote asset (USDT); no conversion methodology is needed for the initial USDT-only cohort.
- Final cohort size and whether a separate probation pool is needed.
- Subscriber order-size tiers and applicable Binance fee assumptions; 500 USDT is the approved reference notional for liquidity qualification only.
- Whether depth is a mandatory hard gate for every signal family or only for entry eligibility.
- Final latency SLOs after choosing hosting region and measuring end-to-end behavior.
- Commercial licensing and legal permission for the intended SaaS distribution workflow.
- Exact universe refresh cadence after stream-capacity testing.


## 11. Approved MVP quote asset and reference notional
- Quote asset whitelist: **USDT only**.
- Reference notional for universe liquidity qualification: **500 USDT**.
- Required executable depth at the proposed 20× ratio: at least **10,000 USDT on the ask side** and **10,000 USDT on the bid side**, each within 10 bps of the respective best price.
- This reference notional is a market-universe qualification benchmark only. It does not prescribe subscriber trade size, account size, or risk allocation.
- Other quote assets are excluded unless a future ADR explicitly approves them.
