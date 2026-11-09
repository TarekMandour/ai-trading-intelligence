> **Scope note (2026-11-09):** The logical evidence-gated decision pipeline remains a design reference, but the current local MVP is manually triggered, REST-based, and produces three independent profile reports (Scalping 1–30m, Intraday 30m–8h, Swing 1–7d). Continuous event triggers, streaming monitoring, subscriber entitlements, and distribution are deferred. The initial multi-timeframe and regime contract is in docs/18-market-regime-and-multitimeframe-spec.md; local implementation scope is in docs/17-local-mvp-implementation-plan.md.


# Opportunity Detection and Decision Pipeline

Status: Phase 0 design approved for the MVP workflow. Scenario families, features, and score weights are research candidates until validated. This document defines an orchestrated process, not a profitable strategy or a claim of predictive certainty.

## 1. Objective and boundary

Convert market observations into a small number of explainable, time-bounded Spot long opportunities. The system should prefer no trade over a weak or stale trade. It must explain what evidence exists, what is missing, what would confirm the setup, and what invalidates it.

The MVP:
- Binance Spot, USDT-quoted instruments only;
- long/buy Spot entry followed by sell-to-exit;
- regular scalping, not HFT;
- analysis and alerts only; no automated order placement, custody, margin, borrowing, leverage, or derivatives;
- centralized analysis and subscriber entitlement-aware distribution.

## 2. Orchestrated pipeline

### Stage 0 — Data and instrument eligibility
Require active eligible Spot instrument, valid exchange filters, sufficient history, fresh trades/quotes, valid candles, synchronized book when depth is required, acceptable spread/depth/slippage, and no venue or symbol safety restriction.

Failure outcome: REJECT or QUARANTINE with explicit reason. No AI call can override this stage.

### Stage 1 — Market regime and context
Estimate the current regime with probabilities or calibrated state scores:
- directional trend;
- range/balance;
- volatility compression;
- volatility expansion;
- momentum acceleration/deceleration;
- transition/uncertain.

Use 1H as broader context, 15m for structure, 5m for setup, and 1m for entry timing as the initial hypothesis. A regime label is descriptive, not a trade direction. If context conflicts or is uncertain, reduce confidence or abstain rather than forcing alignment.

### Stage 2 — Candidate trigger
Receive a candidate from the Watchlist/Active monitoring system. Examples include:
- persistent signed trade-flow shift;
- price/volume expansion near a meaningful structure level;
- breakout and possible retest;
- pullback into a previously defined demand/support zone;
- volatility compression followed by expansion;
- liquidity sweep/reclaim;
- order-book imbalance or replenishment change confirmed by actual trades and price response.

Triggers initiate evaluation only. No trigger is a BUY by itself.

### Stage 3 — Scenario hypothesis generation
Evaluate a bounded scenario library:
1. Trend continuation.
2. Pullback continuation.
3. Breakout and retest.
4. Momentum expansion.
5. Failed breakdown / liquidity sweep and reclaim.
6. Reversal after exhaustion, only with strong structural confirmation.
7. Mean reversion inside a validated range.

Each candidate must have one primary scenario and may include competing alternatives. The engine must record why alternatives were rejected or remain possible. The scenario library is extensible, but a new family requires versioning and validation.

### Stage 4 — Evidence assembly

Build a structured evidence packet from information available at decision time.

**Market context**
- regime probabilities and transition state;
- 1H/15m directional and structural context;
- nearby support/resistance and swing levels;
- volatility state and recent range.

**Price action**
- swing sequence, break/reclaim/retest status;
- candle body/wick and close location;
- displacement and follow-through;
- distance to invalidation and next opposing level.

**Participation**
- relative quote volume and trade count;
- signed taker-flow imbalance over multiple windows;
- price response per unit of aggressive flow;
- volume concentration and anomaly flags.

**Liquidity and execution**
- current spread and spread percentile;
- synchronized ask/bid depth at relevant price bands;
- order-flow imbalance and depth changes;
- estimated buy-side entry and sell-side exit slippage;
- fees on both legs and all-in cost relative to conservative target.

**Data quality**
- event/receive timestamps and feed lag;
- candle completeness;
- book sequence validity and age;
- missing, stale, contradictory, or corrected data flags.

Each feature must include value, unit, observation window, source timestamp, calculation version, and quality flag. The AI must never receive unlabeled numbers without units and provenance.

### Stage 5 — Confirmation and scenario invalidation

Confirmation is a collection of independent evidence, not a single indicator cross:
- structure condition has occurred and is not immediately reclaimed against the scenario;
- price response is consistent with the scenario;
- participation/flow supports the move or at least does not materially contradict it;
- spread and depth remain acceptable;
- entry remains within the allowed distance and freshness window;
- no higher-timeframe or risk condition invalidates the scenario.

Order-book imbalance alone is weak evidence: displayed orders can be cancelled, depth can be uneven across levels, and the book can change faster than the decision. Require persistence and corroboration from executed trades and price response.

A scenario is invalidated when its structural premise fails, the stop/invalidation level is crossed, execution quality breaches hard limits, the data becomes unhealthy, or the entry window expires. Invalidation must be explicit and auditable.

### Stage 6 — Optional AI evidence review

AI is invoked only after deterministic candidate filtering and evidence assembly, at bounded decision checkpoints. It is not called for every trade, quote, depth delta, tick, or subscriber.

Permitted AI tasks:
- summarize the structured evidence;
- compare the primary scenario with listed alternatives;
- identify contradictions or missing evidence;
- provide a structured recommendation: SUPPORT, WAIT, REJECT, or INSUFFICIENT_EVIDENCE;
- generate a concise subscriber-facing rationale from already validated fields.

AI must not:
- invent prices, levels, volume, order-book values, or news;
- calculate or change authoritative risk limits;
- bypass freshness, eligibility, liquidity, or cost gates;
- promote an unconfirmed candidate to a published trade;
- change the system state directly;
- execute arbitrary code or generate unbounded strategies.

All AI output is schema-validated and stored with model/prompt/schema version, input evidence reference, latency, and usage. Invalid, late, unavailable, or contradictory AI output falls back to WAIT/REJECT according to deterministic policy. The system remains usable if the model provider is unavailable.

### Stage 7 — Entry and risk decision

The deterministic Decision & Risk Engine is the final authority.

Allowed decision states:
- ENTER_NOW: all scenario, confirmation, execution, freshness, and risk gates pass.
- WAIT: scenario is promising but a defined confirmation or entry condition is missing.
- MISSED: the valid entry window or maximum entry distance has passed.
- REJECT: the scenario fails a hard gate or its evidence is contradictory.
- NO_OPPORTUNITY: no candidate meets the minimum research threshold.

A publishable plan must contain:
- symbol, venue, Spot direction (LONG);
- primary scenario and version;
- discovery timestamp and reference price;
- entry zone and explicit entry condition;
- maximum acceptable entry distance;
- stop/invalidation price and rationale;
- target 1 and target 2 or a documented exit method;
- conservative expected reward/risk after estimated costs;
- fees, spread, slippage, and reference execution assumptions;
- valid_until / expiry;
- scenario invalidation conditions;
- data-quality and feed-health state;
- analysis, feature, policy, and model versions.

Entry is evaluated against executable ask-side prices. Exit and target feasibility are evaluated against bid-side liquidity and costs. A reference price or candle close is not a guaranteed fill. If cost assumptions or execution depth are missing, the system must not publish a tradable plan.

### Stage 8 — Persist, distribute, and monitor

Persist the decision snapshot and evidence before distribution. Resolve subscriber entitlements separately. Distribution must not rerun the analysis or AI call for each subscriber.

Monitor price, book health, scenario invalidation, expiry, targets, and execution quality. A signal can become invalid or stale even if the overall market feed remains healthy. Each change creates an immutable event and, where needed, a new plan version.

## 3. Evidence scoring model

Keep independent dimensions:
- Opportunity Quality: structural clarity, scenario asymmetry, and context.
- Confirmation Quality: observed follow-through, participation, and corroboration.
- Execution Quality: spread, depth, slippage, fee burden, and entry distance.
- Risk Quality: stop validity, distance to invalidation, target space, and portfolio-level constraints where available.
- Data Quality: freshness, completeness, synchronization, and provenance.

Do not combine these into a single opaque score before individual hard gates. The initial model may use a transparent weighted ranking for research, but weights must be calibrated and versioned. A score is not a probability of success unless explicitly calibrated on untouched out-of-sample data and monitored for drift.

Hard gates:
- instrument and quote-asset eligibility;
- healthy required feeds and synchronized book;
- acceptable spread/depth/slippage and fees;
- valid entry, stop, and target geometry;
- acceptable maximum entry distance and expiry;
- no conflicting active scenario or duplicate signal;
- risk limits and approved subscriber/market distribution policy.

A high score cannot override a failed hard gate.

## 4. Scenario state machine

Candidate lifecycle:
- DETECTED: a trigger has created a candidate.
- WATCHING: a scenario hypothesis is being evaluated; confirmation is incomplete.
- CONFIRMED: required evidence and deterministic gates pass, but entry may still be WAIT.
- ENTERABLE: entry condition is currently satisfied and the trade plan passes all gates.
- INVALIDATED: the scenario premise or risk condition has failed.
- EXPIRED: the candidate's time window elapsed without a valid entry.
- REJECTED: a hard gate or evidence review rejected the candidate.
- MISSED: the entry condition occurred but the acceptable entry window passed.

A published trade lifecycle remains governed by docs/06-trading-lifecycle.md. Candidate state and published trade state are separate entities; do not conflate WATCHING with an active trade.

Every transition stores previous state, new state, event timestamp, receive timestamp where applicable, reason code, evidence snapshot, and policy/version identifiers.

## 5. Freshness and entry validity

Every candidate and trade plan must have:
- discovered_at;
- evaluated_at;
- reference_price and reference_price_timestamp;
- valid_until;
- maximum entry distance in price or bps;
- explicit confirmation condition;
- invalidation condition.

Re-evaluate entry distance using current ask-side executable price. If price runs away, the system returns MISSED or WAIT only if the original scenario still remains valid under a newly versioned plan. Never silently move entry, stop, or targets to make a missed signal appear successful.

## 6. Conflict handling and duplicate control

- Do not publish multiple long candidates for the same symbol and same structural move without a documented reason.
- If two scenario families conflict, either select the better-supported one with recorded comparison or abstain.
- A new candidate may supersede an old one only through an explicit state transition and immutable version.
- Use deduplication keys and per-symbol/scenario cooldowns; do not use cooldowns to suppress hard invalidation or safety alerts.
- When a symbol is quarantined, all candidates requiring its unhealthy data become non-enterable.

## 7. Validation and research requirements

Evaluate each scenario family independently before combining them. Use:
- event replay with original timestamps and sequence ordering;
- chronological train/validation/test splits and walk-forward evaluation;
- purging/embargo where labels or holding windows overlap;
- realistic fees on entry and exit, spread, slippage, market impact, latency, and missed fills;
- bid/ask execution simulation rather than candle-close fills;
- out-of-sample and regime-specific reporting;
- sensitivity tests for thresholds and feature windows;
- ablation tests to measure whether each feature adds incremental value;
- paper/shadow monitoring before promotion.

Do not infer historical order-book state from current snapshots. If historical depth was not captured, label depth-dependent backtests as unavailable rather than fabricate it. Record all tested hypotheses, including failed variants, to reduce selection bias.

Metrics:
- net expectancy and distribution after costs;
- profit factor and drawdown;
- false-positive and missed-opportunity rates;
- entry delay and adverse excursion;
- target/stop hit rates with realistic fills;
- signal frequency and average opportunity lifetime;
- performance by symbol, regime, scenario, and time of day;
- calibration and drift of any probabilistic output;
- feed, queue, and notification latency.

## 8. Subscriber-facing opportunity contract

The subscriber should see:
- what is happening and why the symbol was selected;
- scenario and current state;
- ENTER NOW / WAIT / MISSED / INVALIDATED / EXPIRED;
- entry zone, stop/invalidation, targets;
- expected cost assumptions and reference venue;
- the key evidence and the main risk/contradiction;
- discovery time, last update time, and expiry;
- clear statement that execution and fills are the subscriber's responsibility.

Do not expose an AI narrative without its structured plan and validated data context.

## 9. References

- Binance Spot WebSocket Streams: https://github.com/binance/binance-spot-api-docs/blob/master/web-socket-streams.md
- Kolm, Turiel & Westray (2023), Deep order flow imbalance: https://doi.org/10.1111/mafi.12413
- Albers et al. (2022), Fragmentation, Price Formation and Cross-Impact in Bitcoin Markets: https://doi.org/10.1080/1350486X.2022.2080083
- Anastasopoulos et al. (2026), Order flow and cryptocurrency returns: https://doi.org/10.1016/j.finmar.2026.101047
- Alexander et al. (2026), High-frequency price discovery in fragmented crypto markets: https://doi.org/10.1016/j.jbankfin.2026.107849

These papers motivate testing order flow and book dynamics; they do not establish profitable signal rules for this MVP.
