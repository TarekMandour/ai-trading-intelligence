# ADR 0002: MVP Market and Trading Style — Crypto Scalping

- Status: Accepted (MVP scope)
- Date: 2026-11-09

## Context
The project needs a bounded initial market and trading style to make data, latency, execution, and validation requirements concrete.

## Decision
The first MVP targets the crypto market and regular scalping. Ultra-scalping/HFT is excluded from the initial scope. The platform remains extensible to other markets and styles after the MVP.

A proposed timeframe hierarchy is 1H context, 15m structure, 5m setup/confirmation, and 1m entry timing. This hierarchy remains a hypothesis to validate, not a final production rule.

## Not decided by this ADR
- Spot versus perpetual derivatives
- Initial exchange/venue and data vendor
- Symbol universe and quote assets
- Long-only versus long/short
- Holding-time definition
- Final data freshness and latency thresholds
- Whether order-book depth is mandatory for all signal types

## Consequences
- Streaming data and WebSocket reliability are important architecture requirements.
- Historical trades are strongly preferred for realistic execution replay; candles alone cannot fully validate scalping fills.
- Fees, spread, slippage, liquidity, and market impact must be included in signal eligibility and performance evaluation.
- Venue selection and commercial data redistribution rights are launch blockers.
- The system must support short-lived opportunities with explicit expiry and maximum entry distance.
