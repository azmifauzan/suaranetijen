# Detik.com adapter — design spec

Date: 2026-09-06
Status: approved, pending implementation

## Why

Category coverage research this session (see CLAUDE.md "Riset kategori staging" notes) found
Motor, Brand Umum, and Tokoh Publik (non-Politisi) categories have near-zero observations because
no adapter targets them directly — only incidental YouTube/Kaskus name-search hits. Two originally
proposed replacement sources (Otomotifnet, Oto.com) were live-verified and rejected:

- **Otomotifnet**: article pages are crawlable, but comments load from `apis.kompas.com`
  (Kompas Gramedia's shared comment widget), whose robots.txt is a blanket `Disallow: /` for every
  agent — same publisher family already rejected on `kompas.com` itself (blocks ClaudeBot by name).
  No comments reachable without breaking the project's own robots.txt-authoritative rule
  (`.ai/rules/adapters.md`'s spirit, and `KaskusAdapter::robotsDisallowAll()` precedent).
- **Oto.com**: robots.txt explicitly disallows `*userReviews*` / `*user_reviews/isReviewUseful*` —
  they've fenced off their own review feature from crawlers by name.

Detik.com (`detik.com` + topic subdomains) was checked live instead: robots.txt is permissive
across every target subdomain, no AI-bot bans anywhere (unlike `kompas.com`), and unlike
Otomotifnet its comment API (`apicomment.detik.com`) is **native Detik infra**, not a
cross-company dependency on an already-blocked host.

## What was verified live (this session, 6 Sep 2026)

- `detik.com`, `oto.detik.com`, `wolipop.detik.com`, `hot.detik.com`, `news.detik.com`,
  `inet.detik.com` robots.txt: all permissive (`User-agent: *` / `Allow: /` with only
  Googlebot-specific SEO rules — no path relevant to us is disallowed, no bot is blocked by name).
- Article discovery: each desk publishes `https://{subdomain}/{desk}/sitemap_news.xml`
  (Google News sitemap format — `<url><loc>...<news:publication_date>`, last ~2 days of articles,
  no pagination). Article URLs follow `https://{subdomain}/{desk}/d-{numeric_id}/{slug}`.
- Comments are **not** server-rendered — they load via a Zoid cross-domain iframe widget
  (`comment.detik.com`) backed by a GraphQL API at `apicomment.detik.com/graphql`. Reverse-engineered
  from the widget's own public JS bundle (`comment.app.js`) — no auth/cookies required for reads,
  just requires `Origin`/`Referer` headers matching a detik.com subdomain (a basic embed check, not
  a real anti-bot wall — confirmed live, a bare POST with no Origin returns 405, adding
  `Origin: https://{subdomain}` returns 200 with real data).
- Query shape (confirmed working live against a real article):
  ```graphql
  query search($type: String!, $size: Int!, $sort: String!, $page: Int!, $query: [ElasticSearchAggregation]) {
    search(type: $type, size: $size, sort: $sort, page: $page, query: $query) {
      paging
      counter
      hits { results { id content create_date pin child { id content create_date } } }
    }
  }
  ```
  Variables: `type: "comment"`, `sort: "newest"`, `size: 20`, `page: N`,
  `query: [{name: "news.artikel", terms: <idArtikel>}, {name: "news.site", terms: "dtk"}]`.
  We deliberately request only `id`, `content`, `create_date`, `pin` (top-level) and `id`,
  `content`, `create_date` (nested `child` replies) — the schema also exposes `author`, `liker`,
  `disliker`, `reporter` at both levels, which we never ask for. This is stricter than existing adapters' data-minimization: we control the query,
  so PII fields are never even fetched, not just discarded after extraction.
- `kanal` (channel id) is constant per desk in practice (confirmed by fetching two articles from
  the same desk and getting the same value — `oto.detik.com/motor` → `1208`, `hot.detik.com/celebs`
  → `230`, `wolipop.detik.com/fashion` → `1555`, `wolipop.detik.com/beauty` → `234`) but **can't be
  hardcoded per desk in adapter config**, because of a pipeline constraint found during
  implementation: `FetchSourceDocumentJob` reconstructs a bare `SourceDocumentRef` from the
  persisted `SourceDocument` row (external_id, canonical_url, title, published_at only) — it never
  persists or replays `SourceDocumentRef->metadata`, so anything `discover()` stashes in `metadata`
  never reaches `fetch()`. `fetch()` only ever gets `{sourceKey, externalId, canonicalUrl, title,
  publishedAt}`, nothing else — same constraint every existing adapter already lives with.
  So `fetch()` fetches the article HTML instead (`$ref->canonicalUrl`) purely to regex out `kanal`
  — `idArtikel` doesn't need it, that's just `$ref->externalId`. The article HTML embeds `kanal` in
  **two different templates** across the network (older inline
  `CommentComponent({idArtikel: X, kanal: Y, ...})` JS on oto/hot desks, a
  `<script data-itp-json="comment">{"kanal": Y, ...}</script>` JSON blob on wolipop) — a single
  loose regex (`/["']?kanal["']?\s*:\s*(\d+)/`) matches both, since neither template quotes the
  numeric value itself.
- `pin`, `dislike` unused fields aside, comment `content` is free text with no
  HTML entities needing special handling beyond the project's existing `TextNormalizer`.

## Scope (this build)

Four desks, matching the categories this session found weak:

| Desk | Sitemap | Category helped |
|---|---|---|
| `oto.detik.com/motor` | `.../motor/sitemap_news.xml` | Motor |
| `wolipop.detik.com/fashion` | `.../fashion/sitemap_news.xml` | Brand Umum |
| `wolipop.detik.com/beauty` | `.../beauty/sitemap_news.xml` | Brand Umum |
| `hot.detik.com/celebs` | `.../celebs/sitemap_news.xml` | Tokoh Publik (Selebriti) |

More desks can be added later purely as config (no code change) — same extensibility pattern as
`SerayaMotorAdapter`'s `forum_ids` / `LowEndTalkAdapter`'s `category_urls`.

## Adapter shape

- `preflightUrl()`: `https://www.detik.com/` — plain reachability check, matches the
  `MediaKonsumenAdapter`/`MojokAdapter` pattern (no challenge solver needed, everything here is a
  plain HTTP GET/POST).
- `discover(CrawlCursor $cursor)`: reads `crawl_policy.desks` (flat list of sitemap URLs, same
  shape as `SerayaMotorAdapter`'s `forum_ids`), tracks a `desk_index` cursor field (per
  `adapters.md`'s rotation rule — **advances every cycle**, not just on empty results, since a news
  sitemap always has *something* recent; there's no "exhausted" state to detect the way a paginated
  forum has). Fetches `desks[desk_index]`, parses `<url><loc>` entries (Google News sitemap shape —
  needs its own XML parsing, `AbstractHttpSourceAdapter::parseFeedDocuments()` only matches
  `//item | //entry`, not `//url`), builds one `SourceDocumentRef` per article with `externalId` =
  the numeric id parsed from `/d-(\d+)/` in the URL (override `externalIdFromUrl()`, same as
  `KaskusAdapter`-style overrides elsewhere).
- `fetch(SourceDocumentRef $ref)`: GET `$ref->canonicalUrl` (the article page) and regex out
  `kanal` (see above — needed because `fetch()` never sees `discover()`'s metadata). `idArtikel` is
  `$ref->externalId`, already parsed. Then loops `page = 1..max_comment_pages` (config, default 3,
  mirrors `YOUTUBE_MAX_COMMENT_PAGES`), POSTs the GraphQL query above with `Origin`/`Referer`
  headers built from the article's own hostname, collects all `results` (each with its `child`
  array flattened in) into one JSON payload, stops early when `page >= total_page`. Stores
  `{articleId, results: [...]}` as the raw payload — same shape as `YouTubeAdapter::fetch()` (API
  pagination fully resolved before `extract()` ever runs).
- `extract(FetchedDocument $doc)`: parse the stored JSON, one `CandidateOpinion` per comment
  (including flattened `child` replies), `externalItemId` = the comment's own `id` (stable across
  re-crawls — avoids the MediaKonsumen-style duplicate-reset problem from re-polling the same
  recent items), text = `content` run through the same dedup/hash logic every other adapter uses.
  No selector/XPath work here since the source is already-parsed JSON, not HTML.

## Config

New `Source` row, `adapter: 'detik'`:
```php
'crawl_policy' => [
    'rate_limit_per_minute' => 20,
    'max_comment_pages' => 3,
    'desks' => [
        'https://oto.detik.com/motor/sitemap_news.xml',
        'https://wolipop.detik.com/fashion/sitemap_news.xml',
        'https://wolipop.detik.com/beauty/sitemap_news.xml',
        'https://hot.detik.com/celebs/sitemap_news.xml',
    ],
],
```
Seeded `enabled: false` — same DoD gate as every other source this project (live operator check
before backfill).

## Testing (per `docs/22` — fixtures only, never live network)

- Sanitized fixtures: one Google News sitemap XML (2-3 `<url>` entries), one article HTML snippet
  per `kanal` template variant (inline JS and `data-itp-json` JSON blob), one GraphQL response JSON
  (2+ comments including a `child` reply, plus an empty-`hits` case), one malformed/short-payload
  case (missing `content`, null `child`).
- Unit-test `discover()`'s desk rotation (advances every cycle, wraps at end of list) — same test
  shape as `WaveOneAdapterTest`'s `forum_index` rotation coverage.
- Unit-test `externalIdFromUrl()` against the `/d-{id}/` pattern.
- Unit-test the `kanal` regex against both article template fixtures.
- Unit-test `fetch()`'s pagination stop condition (`page >= total_page`) against a fixture with
  `total_page: 2`.
- Unit-test `extract()`: comment + nested child both become opinions, empty `content` skipped,
  no `author` field ever referenced/stored anywhere in the adapter.
- Regression test confirming the GraphQL query string sent by `fetch()` never includes `author`,
  `liker`, or `disliker` — a static assertion on the query text, not just on what `extract()` reads
  back (data minimization should hold at the request level, not just the parsing level).

## Out of scope (this spec)

- Tokopedia review adapter — separately researched this session (SSR review pages, ~1 review per
  product, `/find/*` browse needs FlareSolverr for discovery, `/search` is robots-disallowed).
  Deferred to a follow-up spec per the agreed build order (Kaskus subforums → Detik → Tokopedia).
- Additional Detik desks beyond the four above (e.g. `oto.detik.com/mobil`, `hot.detik.com/kpop`,
  `inet.detik.com/telecommunication` for ISP & Telco) — config-only addition once this adapter is
  live and verified, not blocking this build.
- The two new `Source` rows reusing `KaskusAdapter` unchanged (`kaskus_otomotif`,
  `kaskus_fashion`) — already implemented this session (`database/seeders/SourceSeeder.php`), not
  part of this spec.
