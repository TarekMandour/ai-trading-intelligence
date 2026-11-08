# Project Status

Last updated: 2026-11-09

## Current phase
**Phase 0 — Project Foundation.** Documentation and decisions only; production implementation is not yet approved.

## Repository
- Repository: TarekMandour/ai-trading-intelligence
- Visibility: Public
- Default branch: main
- Working branch: phase-0-foundation

## Confirmed product decisions
- Product: centralized trading-intelligence SaaS for discovering actionable opportunities, monitoring their lifecycle, and distributing them to eligible subscribers.
- MVP market: **Crypto**.
- MVP product: **Centralized-exchange Spot only**.
- MVP trading style: **Regular Scalping**. Ultra-scalping/HFT is out of initial scope.
- MVP signals: **Long/buy spot opportunities only**; no native short exposure.\n- MVP reference venue/data source: **Binance Spot direct API** (technical selection; commercial licensing pending).\n- MVP instrument universe policy: **`top_liquid`**, a dynamic liquidity- and execution-quality-ranked set of eligible active Spot pairs. The initial 30–50 pair range is a planning target only.
- Margin, borrowing, leverage, futures, perpetuals, options, funding and liquidation are excluded from MVP.
- Automated order execution and custody are out of scope; platform provides analysis and alerts.
- Analysis is performed centrally; a stored opportunity is distributed to eligible subscribers without rerunning the same analysis per subscriber.
- Subscription plans determine entitlements and delivery eligibility.
- One centrally managed OpenAI API integration is intended for bounded model reasoning where it adds value. API usage is billed separately from ChatGPT/Codex.
- Routine notification formatting and delivery do not require an AI call when the opportunity is already stored.
- Deterministic calculations, risk gates, and lifecycle transitions are authoritative; AI cannot bypass them.
- The system may abstain and return NO OPPORTUNITY.
- Historical signal snapshots and decision events must remain auditable and immutable.

## Proposed timeframe stack (pending validation)
| Timeframe | Role |
|---|---|
| 1H | Regime and directional context |
| 15m | Market structure and key levels |
| 5m | Setup development and confirmation |
| 1m | Entry timing and immediate monitoring |

These are proposed roles, not final approved trading rules. Validation must include realistic latency, fees, spread, slippage, liquidity, and entry-fill assumptions.

## Data requirements identified
- Spot instrument metadata and symbol status
- Real-time trades and best bid/ask
- 1m, 5m, 15m, 1h candles with explicit closed/forming status
- Order-book depth and historical trades where available
- Source event timestamp and receive timestamp on every real-time observation
- Ask-side entry and bid-side exit execution estimates
- Both-leg trading fees, spread, slippage, tick size, lot step and minimum notional
- Commercial rights for display and derived signal distribution must be verified contractually

## Open decisions / blockers
- Fallback data provider and venue-outage policy
- Final cohort capacities and validated minimum liquidity/ranking thresholds
- Venue availability and regulatory/legal eligibility for company and subscriber jurisdictions
- Commercial data licensing: display, derived analytics, retention, caching, redistribution, attribution and downstream processors
- Exact holding-time definition for regular scalping
- Final timeframe and intrabar decision rules
- WebSocket/REST data coverage, historical trades/depth availability
- Data freshness and end-to-end latency service objectives
- Spread, liquidity, slippage, fees, and risk thresholds
- Subscriber plans, entitlements, channels, and event notification policy
- Initial technology stack, deployment topology, regulatory/legal review

## Work completed
- README.md and AGENTS.md
- Product overview, vision, PRD, architecture, analysis methodology, AI responsibilities, lifecycle, data sources, risk, validation, roadmap, and project status
- ADR 0001: centralized analysis and entitlement-aware distribution
- ADR 0002: crypto regular scalping MVP
- ADR 0003: crypto spot-only MVP
- Crypto scalping data requirements and timeframe proposal
- Initial market-data provider comparison and licensing review\n- ADR 0004: Binance Spot and dynamic `top_liquid` MVP universe\n- Provisional top-liquid selection, liquidity, freshness, and latency policy

## Next actions
1. Validate Binance Spot data coverage, stream behavior, historical depth availability, and execution-cost assumptions.
2. Obtain written confirmation of commercial rights for the intended SaaS/derived-signal distribution and verify jurisdiction eligibility.
3. Measure and validate the Watchlist/Active Analysis cohort capacities, ranking weights, and liquidity thresholds using replay and paper trading.
4. Evaluate a licensed fallback provider (such as CoinAPI or Kaiko) only if licensing, coverage, or resilience requirements justify it.
5. Validate the proposed 1H/15m/5m/1m hierarchy and execution-cost model.
6. Complete remaining Phase 0 architecture and stack decisions.

## Current gate
**Not approved for production implementation.** Continue Phase 0 decisions and review.


### Newly approved universe parameters
- Quote-asset whitelist: **USDT only**.
- Reference notional for pair liquidity qualification: **500 USDT** (not a subscriber minimum).
- Provisional 20× order-book depth gate: **10,000 USDT ask-side and 10,000 USDT bid-side**, each within 10 bps.
- These approvals are reflected in `docs/13-top-liquid-universe-and-latency.md`; numeric thresholds remain subject to empirical validation.


### Approved universe ranking and cohort policy (2026-11-09)
- Universe selection is a two-stage process: **hard eligibility filters first**, then **weighted ranking** among eligible symbols. Ranking can never override a failed hard gate.
- Initial ranking-weight hypothesis: 30% quote-volume stability/activity, 25% executable depth/resilience, 20% spread quality, 15% estimated slippage/market impact at 500 USDT reference notional, and 10% feed health/data continuity.
- Adopt two cohorts: a lightweight **Universe Watchlist** (initial planning range 75–150 eligible symbols) and an intensive **Active Analysis Set** (initial planning range 30–50 symbols).
- Resist temporary volume spikes using rolling 7-day robust statistics, multi-day persistence, activity/concentration checks, and capped short-window contributions.
- Use hysteresis and dwell periods to limit membership churn; hard safety failures trigger immediate quarantine/removal from entry eligibility.
- These weights, cohort sizes, refresh/promotion/dwell windows, and numerical gates are pilot hypotheses subject to replay, sensitivity analysis, capacity testing, and paper-trading validation.
- Detailed approved policy: `docs/13-top-liquid-universe-and-latency.md`.
