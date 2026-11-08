# Project Status

Last updated: 2026-11-09

## Current phase
**Phase 0 — Project Foundation**

## Repository
- Repository: TarekMandour/ai-trading-intelligence
- Visibility: Public
- Default branch: main
- Working branch: phase-0-foundation

## Confirmed decisions
- Product is a centralized trading-intelligence SaaS platform.
- Core value is discovering actionable opportunities and monitoring their lifecycle, not primarily providing a strategy builder.
- Analysis is performed centrally; the same stored opportunity can be distributed to many eligible subscribers.
- Subscriber plans control access/distribution, not repeated AI analysis.
- One centrally managed OpenAI API integration is intended for model tasks where needed. API usage is billed separately from ChatGPT/Codex usage.
- Routine notifications of already stored opportunities do not require an OpenAI API call.
- AI output cannot override deterministic risk gates.
- Historical opportunity snapshots and decision events must remain auditable.
- System must support abstention and NO OPPORTUNITY.
- Initial repository documentation is the project source of truth.

## Not yet decided
- MVP market and jurisdiction
- Instruments/universe and exclusions
- Trading styles and precise holding-period definitions
- Timeframes and entry timing model
- Market data provider(s), licensing, and coverage
- Liquidity and execution thresholds
- Exact signal states and subscriber-facing event policy
- Subscription tiers and entitlements
- Notification channels
- Initial application stack and deployment topology
- Regulatory/legal requirements for launch markets

## Work completed in this branch
- Initial repository README
- AGENTS.md operating rules
- Product, requirements, architecture, methodology, AI, lifecycle, data, risk, validation, and roadmap documents

## Next actions
1. Review all Phase 0 documents for consistency.
2. Resolve MVP market, trading style, and asset universe.
3. Evaluate data-provider coverage and commercial redistribution rights.
4. Define signal and risk policies.
5. Approve the initial technology stack and deployment model.
6. Update this status and create an explicit Phase 0 approval record.

## Current gate
**Not approved for production implementation.** Documentation and decision work only.
