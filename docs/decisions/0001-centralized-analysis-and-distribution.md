# ADR 0001: Centralized Analysis and Entitlement-Aware Distribution

- Status: Accepted (product-level decision)
- Date: 2026-11-09

## Context
The platform stores centrally generated trade opportunities and serves subscribers under different plans. Re-running the same analysis for each subscriber would duplicate model usage, increase latency, and create inconsistent results.

## Decision
Analyze each market opportunity centrally, persist the result and its lifecycle, then distribute it to eligible subscribers according to plan entitlements. Use one centrally managed OpenAI API integration for model tasks when needed. Routine notification formatting and delivery use stored data and templates without an AI call.

## Consequences
- Lower and more predictable AI usage than per-subscriber analysis.
- Consistent opportunity content across eligible subscribers.
- Subscription and notification services remain independent from analysis.
- Requires clear entitlement evaluation, deduplication, and delivery audit.
- API usage still incurs usage-based costs; one API key does not mean unlimited or flat-rate use.
- Market-data licensing must permit the intended commercial distribution.
