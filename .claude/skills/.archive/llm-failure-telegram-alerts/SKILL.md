---
name: llm-failure-telegram-alerts
description: Use when LLM calls fail or Telegram LLM alerts go missing.
category: alerting
---

Every LLM failure path goes through `LlmClient::chat()` into `LlmFailureNotifier::notify()`. Keep it that way: a new LLM feature must call `LlmClient`, never the HTTP API directly, or its failures stay silent.

Alert rules (in `LlmFailureNotifier::classify()`):
- Connection error: always alert.
- Balance/quota gone (HTTP 402, or body matching quota/credit/balance/saldo/billing/insufficient/payment): alert, "perlu top up".
- 401/403: alert, key rejected. 5xx and other 4xx: alert.
- Plain 429: never alert. Jobs already retry it, so it is routine noise.
- Throttle: one alert per cause key per 30 minutes via `Cache::add`.
- Notifier never throws. A failed Telegram send only logs `llm.failure_alert_not_sent`.
- `APP_ENV=local` never sends (`LlmFailureNotifier::notify()` returns early). The user wants local tests quiet; only staging and production alert. Do not add a local test that fires a real Telegram message.

Config: bot token and chat id come from `config('sponsorship.telegram.*')` (`SPONSORSHIP_TELEGRAM_BOT_TOKEN`, `SPONSORSHIP_TELEGRAM_CHAT_ID`). LLM calls run inside queue jobs, so these keys must be in the `.env` of every worker host as well as the web host. If alerts never arrive, count the keys on each worker before debugging code.

Verify live with `php artisan tinker --execute` calling `notify(new ConnectionException(...), 'test')` on the app container. Tests: `tests/Feature/Entities/LlmClientTest.php`.
