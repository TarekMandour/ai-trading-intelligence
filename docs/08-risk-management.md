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
