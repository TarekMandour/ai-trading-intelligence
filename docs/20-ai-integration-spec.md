# AI Integration Specification — Hybrid Phase 1

Status: Implemented for local validation; real-provider smoke test remains user-side.
Architecture: Hybrid.
Providers: OpenAI and Google Gemini behind the same narrow AiAnalysisProvider contract. OpenAI remains the default; Gemini is opt-in through AI_PROVIDER=gemini.

## 1. Authority and scope

Laravel is authoritative for Binance market data, indicators, timeframe states, scenarios, entry, stop, targets, quantity, fees, spread, slippage, eligibility and final decision. AI is an advisory second opinion only. It cannot browse, call tools, invent market facts, calculate or modify trade levels, execute orders, or override deterministic gates.

AI review runs only for deterministic ENTERABLE candidates, in the queued manual-analysis job, and is bounded by AI_MAX_CANDIDATES_PER_RUN. No AI request is made from the browser or on every candle.

## 2. Current structured review contract

Both provider adapters request JSON-schema-constrained output with exactly these fields, then Laravel independently validates the returned contract:

- assessment: SUPPORT | WAIT_FOR_CONFIRMATION | WEAKEN | ABSTAIN
- timeframe_alignment: ALIGNED | PARTIAL | CONFLICTED | INSUFFICIENT
- confidence_label: LOW | MODERATE | HIGH (ordinal evidence strength, never a success probability)
- supporting_evidence_ids: supplied evidence IDs only
- contradicting_evidence_ids: supplied evidence IDs only; may not overlap supporting IDs
- missing_confirmations: up to five concise strings
- risk_observations: up to five concise strings
- rationale: non-empty text up to 1,000 characters

Laravel independently validates exact keys, enums, ID membership, duplicate/overlapping IDs, array bounds and text lengths. Unknown keys, malformed output and unsupported IDs are rejected. AI does not return authoritative prices or numeric trade levels.

## 3. Reliability and fallback

- Missing API key: NOT_CONFIGURED; deterministic report continues.
- Provider exception, timeout or unsuccessful HTTP response: FAILED; deterministic report continues.
- Invalid response contract: REJECTED; deterministic report continues.
- Candidate cap reached: SKIPPED_LIMIT.
- AI assessment such as WEAKEN or WAIT_FOR_CONFIRMATION is displayed as a caution and never changes the deterministic report status or trade levels.
- OpenAI remains the default provider. Gemini is an explicitly selected alternative; there is no silent provider substitution.
- Provider selection is controlled by AI_PROVIDER=openai|gemini. Gemini credentials use GEMINI_API_KEY and are sent only in the x-goog-api-key header.

The adapter uses a bounded HTTP timeout, short bounded retry, JSON response mode and an output-token cap. The API key is read from Laravel configuration and is never included in the evidence packet or persisted review metadata.

## 4. Audit and reproducibility

The AI review record stores provider, model, status, latency, token usage when returned, prompt version, schema version, evidence hash and normalized review JSON. The evidence hash is SHA-256 over the exact evidence packet supplied to the review. Prompt/schema versions are configuration constants, not inferred from provider output.

## 5. Local setup

Set these values in the local .env file only:

```dotenv
AI_PROVIDER=openai
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4.1-mini
OPENAI_TIMEOUT=20
GEMINI_API_KEY=
GEMINI_MODEL=gemini-3.8-flash
GEMINI_TIMEOUT=20
AI_MAX_OUTPUT_TOKENS=600
AI_MAX_CANDIDATES_PER_RUN=5
```

Then run:

```bash
php artisan config:clear
php artisan migrate
php artisan test
```

For an OpenAI smoke test, keep AI_PROVIDER=openai and add OPENAI_API_KEY. For a Gemini smoke test, set AI_PROVIDER=gemini and add GEMINI_API_KEY. In either case, clear config, create one manual analysis run, and inspect its AI status and review metadata. Gemini's free tier has model-specific rate limits and its free-tier content may be used by Google to improve products; do not send sensitive information. Automated tests use HTTP fakes and do not prove real API credentials, billing, model availability or live network access.

## 6. Acceptance boundaries

Automated coverage includes valid structured output, missing credentials, malformed JSON, unsuccessful HTTP responses, strict contract validation, unsupported/duplicate/overlapping evidence IDs, bounded explanation fields, AI caution without deterministic override, and provider failure fallback.

A real provider smoke test and the full Laravel test suite must still be run in the user's Laragon environment. No profitability claim is made.
