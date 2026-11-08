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
- MVP trading style: **Regular Scalping**. Ultra-scalping/HFT is out of initial scope.
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

## Open decisions / blockers
- Spot vs perpetual futures; leverage policy
- Initial exchange/venue and fallback data provider
- Initial symbol universe, quote assets, long/short policy
- Commercial redistribution rights and target user jurisdictions
- Exact holding-time definition for regular scalping
- Final timeframe and intrabar decision rules
- WebSocket/REST data coverage, historical trades/depth availability
- Data freshness and end-to-end latency service objectives
- Spread, liquidity, slippage, fees, and risk thresholds
- Subscriber plans, entitlements, channels, and event notification policy
- Initial technology stack, deployment topology, regulatory/legal review

## Data requirements identified
- Instrument metadata and symbol status
- Real-time trades and best bid/ask
- 1m, 5m, 15m, 1h candles with explicit closed/forming status
- Historical candles and preferably historical trades for replay and execution validation
- Order-book depth snapshots/deltas strongly preferred for liquidity-aware scalping
- Source event timestamp and receive timestamp on every real-time observation
- Funding, mark/index price, open interest, and liquidation data only if perpetuals are selected

## Work completed
- README.md and AGENTS.md
- Product overview, vision, PRD, architecture, analysis methodology, AI responsibilities, lifecycle, data sources, risk, validation, roadmap, and project status
- ADR 0001: centralized analysis and entitlement-aware distribution
- MVP scope addendum: crypto regular scalping and proposed timeframe roles
- Crypto scalping data requirements and validation considerations

## Next actions
1. Decide spot or perpetual futures.
2. Select candidate exchange/venue and validate WebSocket, historical data, and commercial terms.
3. Define initial tradable symbol universe and direction policy.
4. Validate the proposed 1H/15m/5m/1m timeframe hierarchy.
5. Set latency, freshness, spread, fee, slippage, and liquidity gates.
6. Complete remaining Phase 0 architecture and stack decisions.

## Current gate
**Not approved for production implementation.** Continue Phase 0 decisions and review.
