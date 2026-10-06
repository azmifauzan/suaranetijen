---
paths:
  - 'app/Domains/Sources/Adapters/**'
  - app/Domains/Sources/Adapters/AbstractHttpSourceAdapter.php
  - app/Domains/Sources/Adapters/KaskusAdapter.php
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

## KASKUS listing pages ignore ?page=N: cursor paging finds nothing new
Measured 30 Sep 2026 through FlareSolverr: pages 1, 300, 412, 414 and 900 of /komunitas/306/fashion all return the identical 5 thread links (first id 6a9977f9c7dbcf11e00970e6), the HTML has a "load more" control, and one of the titles is unrelated to the forum, so those links are probably a trending widget while the real thread list loads client-side. The `page_N` cursor of the category sources (kaskus_fashion/otomotif/isp/bisnis/kuliner) therefore walks pages that do not exist (kaskus_fashion was on page 412 with no new document since 24 Sep). discoverExplicitListing() wraps to page 1 on an empty page, which only helps when a page really is empty. Discovering the forum's actual threads needs Kaskus's own JSON endpoint or a browser "load more" click, an operator decision (unofficial API), not a selector tweak. Do not assume a deeper `?page=` exists.

## No whole-page fallback selector in an adapter's extract()
An extract() selector list that ends in //main or //body turns any page without a post node (bot challenge, unhydrated shell, changed markup) into one big "opinion", and every site-wide mention in boilerplate then matches an entity. IndoForum did this: 1,197 of its 1,243 observations (96%, one per unrelated thread, all positive) went to GitHub before anyone saw it, and Kaskus had the same bug earlier. Extract nothing and retry next cycle instead. Other HTML adapters (DiskusiWebHosting, LowEndTalk, MediaKonsumen, Mojok, SerayaMotor) still end in //main and //body; production data shows no concentration there (top entity 9-16% of each source), so they were left alone, but check any new one. monitor:metrics now alerts when one entity takes more than 60% of a source's last-24h observations. To clean up a polluted entity: entities:purge-mismatched-opinions --entity=X --purge-unverifiable=X --source=Y (use --dry-run first).
