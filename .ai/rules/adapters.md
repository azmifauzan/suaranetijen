---
paths:
  - 'app/Domains/Sources/Adapters/**'
---

# Adapters

## AbstractHttpSourceAdapter::request() query-string trap
Always call `$this->request($url)` with the page/cursor query string already embedded in `$url` (via `pageUrl()`/`offsetUrl()` or your own concatenation) — never pass a second `$query` array unless it's actually non-empty.

Why: `Illuminate\Http\Client\PendingRequest::get()` only omits Guzzle's `query` request option when called with exactly one argument. `request()` used to always call `->get($url, $query)` with two arguments even when `$query` defaulted to `[]`, which set `'query' => []` and made Guzzle replace the URL's own query string with nothing — silently discarding every page/cursor param and re-fetching page 1 forever. Fixed in `request()` to only pass `$query` when non-empty, but any new adapter method that calls Laravel's HTTP client directly (bypassing `request()`) can reintroduce this.

Regression test: `tests/Feature/Sources/AbstractHttpSourceAdapterTest.php` asserts the exact requested URL (not `str_contains`) to catch this class of bug — prefer exact-URL assertions over `str_contains` in new adapter tests for the same reason; `str_contains` matchers hid this bug for months across every adapter.
