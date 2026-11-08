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


## Market monitoring and opportunity detection design (2026-11-09)

Completed documentation design:
- docs/14-market-monitoring-and-data-pipeline.md: two-tier monitoring, Binance event contracts, local book synchronization/recovery, candle aggregation, feature families, candidate triggers, bounded workload, reliability, metrics, retention, and acceptance criteria.
- docs/15-opportunity-detection-and-decision-pipeline.md: orchestrated candidate-to-opportunity pipeline, scenario families, evidence packet, confirmation/invalidation, bounded AI review, deterministic decision gates, candidate state machine, freshness, validation, and subscriber output.
- ADR 0005: docs/decisions/0005-event-driven-market-monitoring-and-opportunity-detection.md.

Approved design principles:
- Lightweight Universe Watchlist and intensive Active Analysis Set; initial capacities remain planning hypotheses.
- Event triggers create candidate evaluations, never direct BUY signals.
- Deterministic data/risk/entry gates are authoritative; AI is optional, bounded, and not on the market-event hot path.
- A local order book is usable only when snapshot/delta sequence synchronization is valid; gaps invalidate the book until resynchronization.
- Capture historical depth prospectively if book-based replay is required; do not invent historical depth.
- Candidate lifecycle is separate from published trade lifecycle.
- Validate using event replay, chronological out-of-sample testing, realistic bid/ask execution and costs, and paper/shadow monitoring.

Research references and official Binance stream contracts are included in docs/14 and docs/15. Research motivates feature testing; it does not prove profitability or replace validation on the project's own data.

## Remaining Phase 0 gates
- Final technology stack and deployment topology.
- Final holding-time definition and validated timeframe/intrabar policy.
- Stream/storage retention budgets and measured cohort capacities.
- Commercial Binance data rights for storage, derived analytics, and subscriber distribution, plus target-jurisdiction eligibility.
- Final subscription plans, entitlements, channels, and release/operational criteria.

No production application code has been created or authorized. Continue documentation and approval; do not merge to main.
