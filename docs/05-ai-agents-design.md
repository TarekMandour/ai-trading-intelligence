# AI Responsibilities and Orchestration

## 1. Principle
Use an orchestrated workflow, not a collection of autonomous agents chatting without control. Deterministic services compute facts and enforce constraints; AI performs bounded reasoning over supplied evidence.

## 2. Proposed roles
- **Opportunity Analyst:** synthesizes market context and evidence into candidate scenarios or abstention.
- **Scenario Reviewer:** challenges the candidate, checks contradictory evidence, and identifies missing inputs.
- **Trade Plan Explainer:** turns an approved deterministic plan into a clear subscriber explanation without changing numeric values.
- **Research Assistant (non-production):** proposes hypotheses and constrained strategy specifications for offline testing only.

These are logical responsibilities; they do not require separate model instances or separate agents in the MVP.

## 3. Tool boundary
AI may request approved read-only tools such as:
- get_market_context
- inspect_price_structure
- inspect_volume_features
- inspect_liquidity_snapshot
- retrieve_key_levels
- compare_timeframes
- evaluate_scenario_evidence

The deterministic application owns:
- market data and timestamps
- indicator/feature calculations
- trade price calculations
- risk limits and hard rejection rules
- state transitions
- persistence and subscriber eligibility
- notification delivery

## 4. Structured output
All model output must conform to a versioned schema. Include decision, scenario, evidence references, counter-evidence, uncertainty, missing data, and rationale. Numeric trade-plan fields are sourced from deterministic calculations and must not be silently overwritten by AI.

## 5. Guardrails
- No arbitrary model-generated code execution.
- No secret or subscriber personal data in prompts unless strictly necessary and approved.
- Validate every response; reject malformed or unsupported claims.
- Require evidence references for factual assertions.
- Allow timeout/failure fallback to deterministic rules or abstention.
- Log model and prompt versions plus usage metadata.
- Research output cannot modify production rules automatically.

## 6. Cost policy
Batch and cache context where appropriate. Do not call the model per subscriber or per notification. Define budgets, maximum tokens, rate limits, and model selection after workload tests.
