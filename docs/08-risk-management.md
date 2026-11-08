# Risk Management and Safety Controls

## 1. Purpose
Prevent attractive narratives or high scores from overriding unacceptable trade or data risk.

## 2. Hard gates
Candidate publication must be blocked when any required condition fails, including:
- stale or incomplete critical data
- invalid instrument/session status
- inadequate liquidity or excessive spread
- unacceptable estimated slippage/market impact
- undefined entry or invalidation
- stop distance outside approved limits
- risk/reward or exposure constraints not met
- contradictory timeframe/context conditions where policy requires alignment

Exact thresholds are market- and style-specific and remain unapproved.

## 3. Trade plan requirements
Each publishable opportunity needs entry logic, stop/invalidation, targets or exit logic, maximum entry deviation, expiry, and clear conditions for cancellation.

## 4. Portfolio and concentration controls
Potential later controls include correlated exposure, sector concentration, per-instrument limits, daily loss limits, and simultaneous open-risk caps. Whether the platform merely informs users or models portfolio exposure is an open product decision.

## 5. AI safety
AI cannot bypass hard gates, change computed prices, or fabricate data. Uncertainty and missing evidence must be surfaced. When uncertain, abstain.

## 6. Subscriber communication
Avoid guarantees, certainty language, or claims of expected profit without validated statistical support. Clearly show timestamp, freshness, risk, invalidation, and whether an alert is informational or actionable.

## 7. Operational kill switches
Operators must be able to pause a provider, market, scenario family, analysis version, and notification channel independently.

## 8. Spot-specific controls
- MVP risk model is long-only spot; no leverage, borrowing, margin, or short exposure.
- Use ask-side assumptions for buys and bid-side assumptions for sells.
- Apply fees to entry and exit, plus spread and estimated slippage.
- Validate minimum notional, quantity step, tick size, symbol status, and available liquidity.
- Never imply that a signal's reference price is a guaranteed execution price.
- Clearly disclose the exchange and fee-tier assumptions behind estimated net return.


## 5. Market-data and opportunity safety gates

The following are mandatory fail-closed conditions for any new tradable opportunity:
- inactive/ineligible instrument or non-USDT quote asset;
- stale, malformed, contradictory, or incomplete required market data;
- invalid or unsynchronized local order book when the scenario relies on depth;
- spread, depth, estimated slippage, fees, or all-in costs outside approved limits;
- missing or invalid entry, stop/invalidation, target/exit method, expiry, or max entry distance;
- price has moved beyond the allowed entry distance or the opportunity has expired;
- scenario invalidation, duplicate/conflicting candidate, or breached risk policy.

Entry feasibility is assessed on the ask side; exit feasibility on the bid side. Fees apply to both legs. Displayed depth is not guaranteed liquidity and may disappear; depth features must include persistence/change context and conservative slippage assumptions.

Data-health failures immediately disable dependent decisions and cannot be overridden by AI, rank, or dwell/cooldown periods. See docs/14 and docs/15.
