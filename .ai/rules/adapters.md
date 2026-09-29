---
paths:
  - 'app/Domains/Sources/Adapters/**'
  - app/Domains/Sources/Adapters/AbstractHttpSourceAdapter.php
---

# Adapters

## AbstractHttpSourceAdapter::request() query-string trap
Always call `$this->request($url)` with the page/cursor query string already embedded in `$url` (via `pageUrl()`/`offsetUrl()` or your own concatenation) — never pass a second `$query` array unless it's actually non-empty.

Why: `Illuminate\Http\Client\PendingRequest::get()` only omits Guzzle's `query` request option when called with exactly one argument. `request()` used to always call `->get($url, $query)` with two arguments even when `$query` defaulted to `[]`, which set `'query' => []` and made Guzzle replace the URL's own query string with nothing — silently discarding every page/cursor param and re-fetching page 1 forever. Fixed in `request()` to only pass `$query` when non-empty, but any new adapter method that calls Laravel's HTTP client directly (bypassing `request()`) can reintroduce this.

Regression test: `tests/Feature/Sources/AbstractHttpSourceAdapterTest.php` asserts the exact requested URL (not `str_contains`) to catch this class of bug — prefer exact-URL assertions over `str_contains` in new adapter tests for the same reason; `str_contains` matchers hid this bug for months across every adapter.

## Rotation-index bug pattern: never read list[0] without advancing
Third time this exact bug class has been found (IndoForum forum_ids[0], SerayaMotor forum_ids[0], now KaskusAdapter queries[0]): an adapter that discovers from a list (forum ids, search queries, category URLs) but only ever reads index 0 and never advances gets stuck crawling the first item forever, with no error — health_state stays healthy, crawl_states keeps advancing pages, it just silently never touches items 1..n.

Any new adapter (or edit to an existing one) that discovers from a list must track an explicit `*_index` cursor field, rotate to `(index + 1) % count` when the current item's page returns zero results, and reset to page 1 on rotation — see `IndoForumAdapter::discover()`'s `forum_index` or `KaskusAdapter::discoverByQuery()`'s `query_index` for the reference pattern. A single fixed item (one forum, one listing_url) doesn't need this — only a list does.

## FlareSolverr: one browser session per slot, capped, rotated, with a crash breaker
Every FlareSolverr session is a resident Chromium. Measured on staging (27-29 Sep 2026, 1-2GB containers): three per-host sessions grew from ~40MB to the container limit within hours; and with six crawl processes hitting one container, 40-104 request.get/min ALL ended "tab crashed" while the failed jobs' retries sustained the load. In AbstractHttpSourceAdapter:
- `withFlareSolverrSlot()` allows `services.flaresolverr.max_concurrent` (default 1) renders at once per container (Cache::lock per `gethostname()`, since every worker runs its own FlareSolverr while Redis is shared) and bounces the job with RateLimitExceededException after `slot_wait_seconds`.
- The session id is per slot (`src-slotN`), never per host, so a session is never used by two requests at once and only N browsers stay resident.
- The session is destroyed then created every `session_ttl_minutes` (15); the "session missing" recovery only creates it.
- After a "tab crashed" the session is destroyed and the container refuses requests for `crash_cooldown_seconds` (60), bouncing jobs instead of feeding a retry storm.
More Horizon crawl processes do not add throughput for FlareSolverr-routed sources. Raise the container's memory before raising max_concurrent.
