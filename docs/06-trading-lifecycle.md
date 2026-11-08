# Opportunity and Trade Lifecycle

## 1. States
- **DETECTED:** a candidate pattern has been identified but is not yet ready.
- **WATCHING:** candidate passes initial checks and awaits confirmation or a valid entry location.
- **CONFIRMED:** scenario conditions are met and a trade plan passes deterministic gates.
- **ACTIVE:** the entry condition is considered triggered under the approved execution model.
- **INVALIDATED:** a defined invalidation condition occurred before completion.
- **EXPIRED:** the time or price-validity window elapsed without a valid entry.
- **CLOSED:** the trade scenario has completed under stop, target, or approved exit logic.
- **REJECTED:** candidate failed a hard data, evidence, liquidity, or risk gate.

Allowed transitions must be explicit and validated by a state machine. Reopening a terminal signal must create a new linked version/opportunity, not erase history.

## 2. Required timestamps
created_at, detected_at, confirmed_at, entry_triggered_at, invalidated_at, expired_at, closed_at, last_evaluated_at, source_event_time, received_at.

## 3. Immutable snapshots
Persist the original discovery snapshot and every material decision snapshot. Store market data references, entry zone, stop, targets, expiry, rationale, analysis/rule version, model/prompt version when used, and the reason for each transition.

## 4. Entry freshness
Every opportunity must define valid_until, max_entry_distance, and conditions for WAIT/MISSED/EXPIRED. If price moves beyond the acceptable entry zone, do not continue distributing an obsolete ENTER NOW instruction.

## 5. Monitoring events
Examples: entry reached, target reached, stop reached, early-exit warning, momentum deterioration, market regime shift, data feed degraded, scenario invalidated, expiry reached. Event priority and subscriber notification policy are configurable and pending product approval.

## 6. Historical integrity
Never rewrite historical entry/stop/target values. Corrections must be represented as new events or explicitly linked revisions with reason and timestamp. Performance calculations must use what was knowable at each historical time.


## 5. Candidate lifecycle boundary

Candidate evaluation is a separate state machine from the published trade lifecycle:
- DETECTED → WATCHING → CONFIRMED → ENTERABLE;
- any non-terminal candidate may become INVALIDATED, EXPIRED, REJECTED, or MISSED under explicit rules.

DETECTED means a market event triggered evaluation, not that a trade exists. CONFIRMED means evidence gates pass, but entry may still be WAIT. ENTERABLE means the current executable ask-side entry, cost, freshness, and risk gates pass. MISSED means the acceptable entry window has passed. A published trade then follows the existing trade lifecycle.

Every transition stores prior/new state, event and receive timestamps, reason code, evidence snapshot, and policy/feature/model versions. Candidate state must not be rewritten to make historical signals appear valid. See docs/15 for transition semantics.
