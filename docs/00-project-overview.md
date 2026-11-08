# Project Overview

## Product
AI Trading Intelligence is a centralized trading-opportunity intelligence platform. It identifies candidate opportunities, evaluates context and execution quality, creates explicit trade plans, monitors active scenarios, and distributes eligible opportunities to subscribers.

The product is not primarily a user strategy-builder or a generic indicator scanner. Its core value is helping subscribers understand:
- Which instrument is worth watching or trading?
- Why is an opportunity present?
- Is entry valid now, should the user wait, or has the opportunity been missed?
- Where are the entry zone, invalidation/stop, and targets?
- What conditions cancel the scenario?
- How fresh is the opportunity, and what has changed since publication?

## Centralized operating model
1. Market data is collected centrally.
2. The platform analyzes the market and stores opportunities centrally.
3. Each opportunity has a versioned plan and lifecycle.
4. Subscription entitlements determine which subscribers may receive which opportunity types/markets/features.
5. Notifications distribute the stored result. They do not trigger a duplicate analysis for each subscriber.

OpenAI API is a centrally configured service used only when model reasoning adds value. It is not required for routine notification formatting or delivery.

## Product outcomes
- Timely, explainable, risk-aware opportunities.
- Explicit abstention when evidence is insufficient.
- Reliable monitoring and lifecycle updates.
- Transparent historical performance with immutable records.
- Predictable operating costs and tenant isolation.

## Non-goals for the first release
- Guaranteed returns or claims of certainty.
- Unrestricted AI-generated executable strategies.
- User-defined strategy marketplace unless separately approved.
- Broker execution or automatic order placement unless explicitly scoped later.
- Per-subscriber AI analysis of the same centrally generated opportunity.

## Operating principles
- Data quality before model sophistication.
- Evidence before narrative.
- Risk controls before opportunity scores.
- Reproducibility before optimization.
- Out-of-sample evidence before production promotion.
- Central analysis, entitlement-aware distribution.
- Fail safely when feeds are stale, incomplete, or contradictory.

## Current status
The project is in Phase 0. Market, asset universe, timeframes, provider, and implementation stack remain open pending product decisions.


## MVP scope decision — Crypto Scalping
The first MVP market is **crypto** and the first trading style is **regular scalping**. The initial release is not intended to target ultra-low-latency/HFT execution. The product remains architecturally extensible to additional markets and trading styles after the MVP.

The MVP venue/data source is **Binance Spot** and the instrument universe policy is **`top_liquid`**: a dynamic, liquidity-ranked subset of eligible active spot pairs. The commercial licensing gate and operating/subscriber jurisdiction review remain open; technical selection does not grant redistribution rights.

### Initial timeframe proposal (pending validation)
- **1H:** broader intraday market regime and directional context.
- **15m:** market structure and important levels.
- **5m:** setup development and confirmation.
- **1m:** entry timing and short-term invalidation monitoring.

These are proposed analysis roles, not yet hard-coded requirements. They must be validated against data quality, signal frequency, latency, fees, spread, slippage, and realistic execution. Higher context such as 4H may be evaluated but is not mandatory for the first test.

## MVP product decision — Spot only
The first MVP is **crypto spot scalping**. Margin, leveraged spot, perpetual futures, futures, options, funding-rate logic, liquidation logic, and automated order execution are outside the approved MVP scope unless separately approved through an ADR. The product provides analysis and alerts; subscribers execute trades independently on their chosen venue.


## MVP data-source and universe decision — Binance Spot / top_liquid
- Reference venue and primary feed: **Binance Spot**.
- Universe policy: **`top_liquid`**, dynamically selected from eligible active spot pairs using quote-volume stability, spread, executable order-book depth, estimated slippage, trade activity, and feed health.
- The universe is not selected by market capitalization alone and is not a permanently fixed list.
- Initial operating target is a configurable cohort (planning range: 30–50 pairs); the final count and quote-asset whitelist remain subject to capacity, liquidity, and execution validation.
- Numeric liquidity and latency thresholds are provisional Phase 0 hypotheses in `docs/13-top-liquid-universe-and-latency.md`, not validated performance claims.
- Binance Spot is the technical reference venue. Commercial SaaS display/derived-signal/subscriber distribution rights and jurisdiction eligibility must be cleared in writing before production distribution.
