---
name: llm-codex-api-fallback
description: Use when Codex LLM fails, or paid API fallback fires.
category: llm
---
# Codex failures in queued LLM jobs wait for the last attempt before paid API fallback

In a queued LLM job, a Codex failure with retries left throws `CodexUnavailable` and must not call the paid OpenAI-compatible API. The API fallback runs only on the job's final attempt. Outside queue jobs (admin actions, candidate enrichment), a Codex failure still falls back to the API at once.

- `CodexBeforeApiFallback` (`app/Domains/Themes/Jobs/Middleware/`) sets `LlmClient::$deferApiFallback` for each attempt. It counts failures in cache `llm:codex-failures:{jobUuid}` (24h) and clears the flag in `finally`.
- It is attached to `ExtractThemesJob` (llm mode), `ExtractThemesBatchJob`, `RecheckRelevanceBatchJob`, and `SummarizeEntityThemesJob`. Any new LLM queue job that needs the same guard goes through this middleware, not a copy.
- While deferring, the Codex timeout is 120s, because no API call waits behind it. The final attempt keeps 45s, then the API.
- Retries use backoff 30/60/120s with `maxExceptions` 6. Failures of the last attempt fall back as before.
- `.ai/rules/services.md` ("Codex paid fallback requires explicit per-node opt-in") still states a fixed 45s/50s Codex bound. That bound now applies only to the final attempt and to non-queued calls. Update the rule file when next touched.
- Do not raise the Sumopod budget or switch to an unapproved model to clear a budget or 504 error. Ask the user first.

Verify with `tests/Feature/Themes/CodexBeforeApiFallbackTest.php` and the `LlmClientTest` case that throws `CodexUnavailable` with no request to `llm.internal` before the final attempt.
