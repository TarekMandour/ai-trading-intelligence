# AI Integration Specification — Hybrid Phase 1

Status: Approved for Phase 1 local MVP.
Architecture: Hybrid.
Initial provider: OpenAI, behind a minimal provider-neutral contract.

## 1. Purpose
AI is a real analytical component of the first release, not a decorative report writer. It reviews structured evidence produced by deterministic Laravel services and provides a second opinion on market context, scenario quality, multi-timeframe alignment, contradictions, and missing confirmation.

The deterministic engine remains the source of truth for market data, calculated features, trade levels, costs, eligibility, and final risk decisions.

## 2. Responsibilities
Laravel deterministically fetches and validates Binance Spot data; calculates indicators, structure, regimes and timeframe states; evaluates versioned scenarios; computes entry zones, stops, targets, expiry and costs; and applies data-quality and risk gates.

For shortlisted candidates, AI may interpret supplied regime and multi-timeframe evidence, compare supplied scenarios, identify supporting and contradicting evidence, identify missing confirmation and failure modes, critique the supplied plan without changing it, provide an advisory stance (SUPPORT, WAIT_FOR_CONFIRMATION, WEAKEN, ABSTAIN), or return NO_OPINION.

AI must not fetch or invent market data; generate or modify entry, stop, target, quantity, fee, spread or slippage values; create arbitrary strategies; execute orders/tools; override deterministic gates; or turn an uncalibrated score into a success probability.

## 3. Workflow
1. Dashboard creates a manual AnalysisRun.
2. Database queue fetches data and performs deterministic analysis.
3. Deterministic evaluators create a shortlist and evidence packets.
4. If configured, the queued job sends compact packets to the selected provider. Never call AI from the browser request or on every candle.
5. Provider returns schema-constrained JSON.
6. Laravel validates schema, enums, field lengths and evidence IDs against the supplied packet.
7. AI assessment is stored as advisory metadata.
8. Deterministic Decision & Risk Engine remains final authority and produces ENTERABLE, WAIT, MISSED, REJECTED or NO_OPPORTUNITY.
9. Reports show deterministic decision and AI review status separately.

Provider failure must not erase valid deterministic analysis. Preserve reports and set AI status to FAILED or NOT_CONFIGURED.

## 4. Minimal provider design
Use one narrow interface, e.g. AiAnalysisProvider, accepting a typed review request and returning a normalized result or typed provider error.

Initial implementation:
- OpenAiAnalysisProvider using Laravel HTTP client.
- AI_PROVIDER=openai.
- OPENAI_MODEL from environment configuration, not hard-coded in business logic.
- OPENAI_API_KEY in local .env, excluded from source control.
- Future providers implement the same narrow contract.

Do not build generic agent frameworks, autonomous loops, multi-agent graphs, arbitrary tool calling or a provider marketplace.

## 5. Structured response contract
Use JSON Schema / Structured Outputs when supported by the selected API, and validate again in Laravel.

Suggested fields:
- assessment: SUPPORT | WAIT_FOR_CONFIRMATION | WEAKEN | ABSTAIN
- scenario_id: supplied candidate ID or null
- timeframe_alignment: ALIGNED | PARTIAL | CONFLICTED | INSUFFICIENT
- supporting_evidence_ids: IDs present in request
- contradicting_evidence_ids: IDs present in request
- missing_confirmations: bounded strings
- risk_observations: bounded strings
- rationale: concise explanation grounded in supplied evidence
- confidence_label: LOW | MODERATE | HIGH; ordinal evidence strength only, never success probability

Do not request authoritative prices or numeric trade levels from AI. Reject unknown evidence IDs, malformed output, unsupported enums and excessive text.

## 6. Prompt and evidence governance
- Version system prompts and response schemas.
- Store prompt_version, schema_version, provider, model and evidence/config fingerprint.
- Treat market data as untrusted input, never as instructions.
- Exclude secrets, account identifiers and unrelated personal data.
- Send minimum relevant features/summaries, not unbounded raw candle histories.
- Keep evidence IDs stable within each packet.
- Store sanitized audit content sufficient for reproducibility.
- Never store credentials in payloads, database, exceptions or logs.

## 7. Reliability, cost and fallback
- Explicit connection/response timeouts.
- Bounded retries with exponential backoff and jitter for retryable errors only.
- Bound input/output tokens and candidate count.
- Deduplicate using evidence + config + prompt + provider + model fingerprint.
- Record status, duration, provider/model, prompt/schema version and usage/cost metadata when available.
- Never invent cost figures if usage/pricing data is unavailable.
- Missing key => NOT_CONFIGURED; provider failure => FAILED; invalid schema/evidence => REJECTED. Deterministic reports continue.
- Never silently substitute another provider or model.

## 8. Configuration
Document safe empty examples for AI_PROVIDER, OPENAI_API_KEY, OPENAI_MODEL, AI_TIMEOUT_SECONDS, AI_MAX_OUTPUT_TOKENS and AI_MAX_CANDIDATES_PER_RUN. Read through Laravel config; do not call env() throughout services.

## 9. Tests and acceptance
Test normalization/schema validation, missing credentials, timeout, rate limits, provider errors, malformed JSON, unsupported evidence IDs, valid responses, abstention, and fallback. Assert AI cannot modify deterministic levels or final risk state. Use fake provider/HTTP fakes for automated tests. A real-provider smoke test requires the user to supply a local key; never claim it ran if only fakes were used.

## 10. Definition of done
Provider contract and OpenAI adapter exist; AI review runs in queued manual analysis for shortlisted candidates when configured; structured output is validated and audited; AI status and explanation appear in reports; deterministic gates remain authoritative; failure/missing credentials preserve deterministic reports; setup and real-provider smoke-test instructions are documented.
