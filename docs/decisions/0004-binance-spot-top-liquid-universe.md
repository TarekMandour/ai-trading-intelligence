# ADR 0004: Binance Spot and Dynamic Top-Liquid MVP Universe

- Status: Accepted for MVP technical scope; commercial licensing remains a release blocker.
- Date: 2026-11-09

## Context
The MVP is centralized-exchange crypto Spot, regular scalping, long/buy opportunities only, with analysis and alerts but no custody or automated execution. Phase 0 compared direct exchange feeds and third-party providers. The project needs one authoritative reference venue and a universe that favors executable liquidity rather than market capitalization.

## Decision
1. Select **Binance Spot direct market-data API** as the MVP technical reference venue and primary feed.
2. Select **`top_liquid`** as the instrument-universe policy: a dynamic set of eligible active Spot pairs ranked by quote-volume stability, spread, executable order-book depth, estimated slippage/market impact, trade activity, and feed health.
3. Plan for a configurable initial cohort; 30–50 pairs is a capacity-planning range, not a final fixed count.
4. Keep quote-asset whitelist, exact cohort size, final numeric thresholds, and hosting-region latency objectives open pending measurement and explicit approval.
5. Use Binance REST for metadata, backfill, initial depth snapshots, and recovery; use WebSocket for continuous live trades, best bid/ask, and depth updates.
6. Keep provider-specific payloads behind a normalized adapter. Keep universe ranking, hard eligibility gates, freshness checks, and execution-cost checks deterministic.
7. Do not launch paid subscriber display or derived-signal distribution until Binance's terms or a written authorization expressly permit the intended collection, storage, derived analytics, alert distribution, caching/retention, and downstream processing, and jurisdiction eligibility has been checked.

## Rationale
- A single venue-native source avoids mixing execution books and provides a coherent reference price, spread, depth, and fee model.
- A dynamic liquidity-ranked universe adapts to changing market conditions and avoids a static list or market-cap-only selection.
- A configurable cohort controls stream, storage, and analysis load while the pilot establishes the right breadth.
- Separating the adapter and universe service preserves the option to add a licensed vendor or another venue later without changing domain logic.

## Consequences
- Signals are explicitly referenced to Binance Spot. Subscribers executing elsewhere may experience different prices, fees, spread, and fills; the product must disclose this.
- Spot-only remains long/buy then sell-to-exit; no short, margin, futures, or derivatives logic is introduced.
- The product must implement WebSocket health, depth snapshot/delta synchronization, sequence-gap recovery, REST backfill, and per-symbol freshness gates.
- The universe needs versioned membership, auditable inclusion/exclusion reasons, hysteresis, and historical reproducibility.
- Commercial licensing and jurisdiction review remain hard launch gates; API accessibility is not permission to redistribute data or derived works.
- The numeric proposal in `docs/13-top-liquid-universe-and-latency.md` must be validated with multi-week observations, replay, and paper trading before becoming final production thresholds.

## Alternatives considered
- **Static top-N by 24h volume:** rejected because it is vulnerable to temporary volume spikes and ignores spread, depth, and slippage.
- **Market-cap ranking:** rejected as an execution-liquidity proxy.
- **Multiple exchanges from day one:** deferred because it increases normalization, licensing, and operational complexity and risks confusing venue-specific books.
- **CoinAPI/Kaiko as primary MVP feed:** deferred; may be reconsidered if licensing, historical depth, or operational requirements justify the additional cost.

## References
- `docs/12-market-data-provider-comparison.md`
- `docs/13-top-liquid-universe-and-latency.md`
- `docs/11-crypto-scalping-data-requirements.md`


## Approved parameter refinement (2026-11-09)
- MVP quote-asset whitelist is **USDT only**.
- Reference notional for liquidity qualification is **500 USDT**.
- Under the proposed 20× depth ratio, require at least **10,000 USDT executable ask depth** and **10,000 USDT executable bid depth**, each within 10 bps.
- The reference notional is not a mandatory subscriber trade size.
