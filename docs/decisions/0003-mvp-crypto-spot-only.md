# ADR 0003: MVP Crypto Spot Only

- Status: Accepted
- Date: 2026-11-09

## Context
After selecting crypto regular scalping as the MVP style, the product must choose between spot and derivatives to bound data, risk, and execution requirements.

## Decision
The MVP is centralized-exchange **Spot only**, with long/buy opportunities and sell exits. The platform provides analysis and alerts; subscribers execute independently. Margin, borrowing, leverage, futures, perpetuals, options, funding, liquidation, custody, and automated order placement are excluded from MVP.

## Consequences
- No derivatives-specific data or risk model is required.
- The MVP is long-only; no short-selling signals are actionable.
- Entry estimates use ask-side liquidity; exit estimates use bid-side liquidity.
- Fee calculations include both entry and exit legs.
- Spread, slippage, tick size, quantity step, minimum notional, and venue status are hard execution considerations.
- Signals must identify the reference venue and fee assumptions; actual subscriber fills may differ.
- Provider selection must consider commercial use and downstream subscriber distribution rights, not only API availability.
- Future derivatives support requires a separate ADR and independent risk design.
