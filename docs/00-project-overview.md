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
