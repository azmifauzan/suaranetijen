# 27 - Codex Subscription as an LLM Provider

- **Status:** Proposed, not implemented
- **Date:** 28 September 2026
- **Scope:** Optional LLM provider for existing background work and operator commands. No public Codex login.

## Decision

Add `codex_subscription` as an opt-in provider behind the shared `LlmClient`. Keep the current
OpenAI-compatible API provider as the default. Use the documented, scriptable `codex exec` CLI
with a ChatGPT Plus/Pro login. Do not send a ChatGPT OAuth token to `/v1/chat/completions` or
implement Hermes's native Codex transport in PHP: the former is the wrong protocol, while the
latter would make this project maintain its own OAuth refresh and Codex wire adapter.

This is a capacity-limited option for trusted, operator-controlled processing. It does not turn a
Plus/Pro subscription into OpenAI API credit. The subscription's Codex usage limits still apply.
There is no automatic fallback to the paid API when Codex authentication, quota, or runtime fails;
an operator must explicitly change providers. This prevents an unnoticed bill.

OpenAI documents [ChatGPT sign-in for subscription access](https://learn.chatgpt.com/docs/auth),
[scriptable workflows on Plus/Pro](https://learn.chatgpt.com/docs/pricing), and
[`codex exec` for scripts](https://learn.chatgpt.com/docs/non-interactive-mode), including
[`codex exec` structured output](https://learn.chatgpt.com/docs/developer-commands). Hermes
demonstrates a different implementation using [Codex OAuth and a native Responses transport](https://hermes-agent.nousresearch.com/docs/integrations/providers).

## Current application boundary

- `LlmClient::chat()` sends OpenAI-compatible chat completions and returns decoded JSON. Theme
  extraction (single and batch), theme summaries, theme consolidation, and entity-candidate
  enrichment all use that method and already supply JSON schemas. Preserve that PHP contract.
- The admin LLM form stores one shared model, base URL, API key, temperature, max tokens, and
  timeout. The API key is encrypted in `llm_settings`; it must remain intact when providers change.
- The application image has Node.js but does not install Codex CLI. The `themes` and `aggregate`
  Horizon workers currently have 60-second timeouts; Redis `retry_after` defaults to 90 seconds.
- Existing theme observations remain keyed by `extractor = llm`. Switching provider changes future
  inference only; it does not reprocess observations or change Sentimen Netijen, Rating Netijen,
  theme frequency, or organic ranking rules in [docs/25](25-top-suara-netijen.md).

## Implementation

1. Add `provider` and `codex_model` to the single `llm_settings` row, with
   `services.llm.provider` and `services.llm.codex_model` environment fallbacks. Provider values
   are `openai_compatible` (default) and `codex_subscription`; the initial Codex model is
   `gpt-6-luna`. The admin form gets a provider selector. Its API fields continue to edit the
   existing `model`, base URL, API key, temperature, and max tokens; the Codex view edits only
   `codex_model` and timeout. Preserve all API fields when Codex is selected so rollback is one
   provider change. Use the existing admin authorization.
2. Keep `LlmClient::chat($messages, $jsonSchema)` as the only caller-facing method. Route the API
   branch through the current HTTP request. Route the Codex branch to one small internal runner;
   require a JSON schema in that branch and return the same decoded array. Do not add a general
   provider framework or alter feature prompts and evidence checks.
3. Run a single Codex runner container on a private application network. It has Codex CLI, an
   empty temporary working directory, and a private persistent `CODEX_HOME` volume for the
   operator's ChatGPT login. It receives no application source tree, database credentials, or
   Laravel `.env`. Expose only `POST /chat` and `GET /health` to the Laravel containers, protected
   by an internal bearer secret; never publish its port. The request carries `messages`, the
   existing JSON schema, Codex model, and timeout. The response is a JSON object or a typed error.
4. The runner executes `codex exec` without a shell, passing the prompt through stdin and the
   caller's schema through a temporary JSON Schema file. Use `--ephemeral`, `--output-schema`,
   `--skip-git-repo-check`, `--ignore-user-config`, and a read-only sandbox. Disable Codex tools
   for this inference-only path, including the shell tool; reject startup if the pinned CLI
   version cannot enforce that setting. Parse only the final JSON response, reject empty or
   malformed output, and remove the temporary schema file after each call. Do not log prompts,
   opinion text, CLI output, OAuth tokens, or session files. The runner handles at most one call
   at a time and initially admits five calls per minute; both limits are configurable.
5. Install and pin a tested Codex CLI release in the runner image. Log in interactively once per
   runner with `codex login --device-auth` or the browser flow. Never put OAuth credentials in
   `llm_settings`, a build layer, or a repository file. The admin page shows runner readiness and
   rejects a switch to Codex when the runner lacks a valid login. Treat quota as HTTP 429 with
   `Retry-After`, missing login as a permanent error, and process timeout as a retryable error.
6. Give Codex calls a 120-second process limit and a slightly longer Laravel HTTP limit. Raise
   the affected Horizon worker timeout to 150 seconds and Redis `retry_after` above it (180
   seconds initially). Preserve the existing `themes-llm` Redis limiter; add equivalent release
   handling to theme-summary jobs. Keep candidate scans and consolidation operator-invoked.
   Subscription exhaustion must stop work visibly in Horizon instead of silently switching API.

## Verification and rollout

- Test admin authorization, provider switching, API-key preservation, runner readiness, and the
  unchanged API branch. Fake runner success, invalid JSON, malformed schema, 429, missing login,
  and timeout; assert no API request is made after a Codex failure.
- Run the sanitized Indonesian theme fixtures and a small staging batch through both providers.
  Compare schema validity, evidence-grounding rejection, summaries, latency, and repeated-job
  idempotency. Test the runner's empty filesystem/tool restrictions before sending third-party
  opinion text. No test should call live Codex as part of the normal suite.
- Deploy the runner and login volume to staging first. Verify the CLI version, login, health
  endpoint, queue timeout ordering, and no raw content in logs or session files. Start with a
  small manual backfill; do not run the full historical corpus against a Plus/Pro allowance.
  Enable the admin provider switch only after those checks pass. Monitor quota errors, job age,
  failed jobs, and generated-theme quality. Roll back by selecting `openai_compatible`; retry
  failed jobs deliberately after the provider is healthy.

The runner is a process boundary for untrusted opinion text, not a new product domain. If the
CLI cannot run without tools inside that boundary, or subscription access is unsuitable for the
measured workload, keep the API provider and do not enable Codex in production.
