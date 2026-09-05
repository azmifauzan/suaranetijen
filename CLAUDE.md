<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Test every code change by adding or updating a test.
- Run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>

# SuaraNetijen

`docs/` holds 25 numbered documents produced during brainstorming. They are the **source of truth**
for product, schema, and architecture decisions, and they describe a target that is not built yet.
Read the relevant document before designing anything new; do not rewrite `docs/` to match code.

## What this is

Search-first public sentiment index for Indonesia. The system crawls public opinion about an
**entity** (brand / product / service), classifies each relevant opinion as positive / neutral /
negative, and aggregates that into a public score.

Third-party content is processing input, not republished product. The durable assets are the
entity graph, sentiment observations, historical aggregates, rankings, and first-party ratings.

## Public metrics

| Metric | Range | Source |
|---|---|---|
| Sentimen Netijen | 0-100 | aggregated crawler sentiment |
| Top Suara Netijen | ranked themes | theme frequency from the same opinion data (`docs/25`) |
| Rating Netijen | 1-5 | first-party user star ratings |
| Jumlah Opini | count | relevant observations in the period |
| Distribusi Sentimen | % | positive / neutral / negative split |

**The three metrics are never merged** (ADR-007, ADR-011). No public aspect score, experience
score, authenticity score, performance score, or source weight exists. Top Suara Netijen shows
theme *frequency*, never a numeric per-theme score — do not build one.

## Score formula v1

```text
score = 100 * (positive_count + 0.5 * neutral_count) / opinion_count
```

Equivalent to positive=100, neutral=50, negative=0. Thresholds are configuration, not hard-coded
truth (`examples/score-config.yaml`): public score at >= 30 opinions, ranking eligible at >= 100.
Periods `30d`, `90d`, `365d`, `all`; public default `365d`, fallback `all` for new entities.

Ranking sort: score desc, then `opinion_count` desc, then name asc. No popularity bonus.
Snapshots carry `sentiment_model_version` and `score_formula_version`.
Rating Netijen is computed separately as `sum(active_user_rating) / rating_count`.

Detail: `docs/11-sentiment-scoring-ranking.md`.

## Standing constraints

- **Entity-centric.** `brand`, `product`, and `service` share one table and one engine. Parent-child
  is navigation and matching only — brand opinions never cascade to child services
  (`IDCloudHost bagus` -> IDCloudHost; `VPS IDCloudHost bagus` -> VPS IDCloudHost).
- **No aspect scoring.** No camera/support/performance subscores, no specs, no prices, no
  benchmarks, no verified purchase, no fact checking. Top Suara Netijen (`docs/25`) shows theme
  frequency and does not reopen this — it must never render a numeric per-theme score.
- **No source weighting.** Source role affects crawl priority only, never the score.
- **Derived index first.** Raw payloads are temporary with a per-adapter TTL. Hashes and external
  IDs persist for deduplication; raw text does not.
- **Data minimization.** The index rates entities, not authors. Never store usernames, avatars,
  follower graphs, emails, or inferred demographics from third-party sources.
- **Adapter guardrails, not product logic.** Every adapter has `preflight()` and a feature flag.
  A blocked or unstable source is disabled without touching the core pipeline.
- **Precision over recall on matching.** When an entity is uncertain, write to
  `unmatched_mentions` or discard. Losing signal beats attributing sentiment to the wrong entity.
- **Jobs are idempotent.** A retry must never create a second observation for the same
  `(entity_id, source_item_id)`.
- **Politics is out of scope** for MVP and near roadmap.

## Data model

Core tables (`docs/06-domain-data-model.md`): `entities`, `entity_aliases`, `categories`,
`sources`, `source_documents`, `source_items`, `sentiment_observations` (unique
`(entity_id, source_item_id)`), `sentiment_daily`, `sentiment_snapshots`, `themes`,
`theme_aliases`, `theme_observations`, `entity_theme_daily`, `entity_theme_snapshots` (`docs/25`),
`user_ratings` (unique `(user_id, entity_id)`), `rating_snapshots`.

Operational: `crawl_states`, `ingestion_failures`, `unmatched_mentions`, `search_queries`,
`source_preflight_logs`.

## Pipeline

```text
discovery -> fetch -> extract -> normalize -> deduplicate -> entity matching -> opinion relevance
  -> sentiment classification -> observation -> daily aggregate -> snapshot / ranking
  -> theme extraction -> normalization/clustering -> theme observation -> theme daily aggregate
```

Theme extraction (`docs/25`) is a second, independent branch off the same relevant-opinion
output — it never blocks, and is never blocked by, sentiment classification.

Adapter contract (`docs/08-source-adapter-specs.md`): `preflight()`, `discover()`, `fetch()`,
`extract()`. Health states: `healthy`, `degraded`, `blocked`, `policy_disabled`, `parser_broken`,
`quota_limited`. One adapter failing never blocks another.

Queues (`examples/queue-topology.yaml`): `critical`, `discovery`, `crawl`, `analysis`,
`aggregate`, `maintenance`. Jobs are listed in `docs/14-api-jobs-scheduler.md`.

## Planned module layout

Modular monolith, one codebase, workload separated by queue rather than by service:

```text
app/Domains/
  Entities/  Search/  Sources/  Ingestion/
  Sentiment/  Themes/  Rankings/  Ratings/  Moderation/  Admin/
```

Extract a service only after a measured bottleneck — `docs/05-technical-architecture.md`.

## Routes

Public: `/`, `/search?q=`, `/e/{slug}`, `/category/{slug}`, `/top/{slug}`, `/methodology`,
`/sources`, `/about`, `/terms`, `/privacy`.

Internal web API (not a public developer API): `GET /api/search?q=`,
`GET /api/entities/{slug}`, `GET /api/categories/{slug}/ranking`,
`PUT|DELETE /api/entities/{id}/rating`.

Admin: entities, aliases, categories, sources, crawl jobs, unmatched mentions, sentiment
diagnostics, ratings/moderation.

## Search and SEO

Search is PostgreSQL only for MVP — normalized exact match, `pg_trgm` similarity, aliases, FTS.
Match order: exact name, exact alias, prefix, trigram, category context. Sentiment is at most a
small secondary tie-breaker after textual relevance.

- Do not emit `AggregateRating` schema for Sentimen Netijen. That schema is only ever valid for
  the first-party rating.
- Entities under threshold may be `noindex`; do not ship thin pages.
- Ranking copy says "sentimen netijen tertinggi", never "terbaik di Indonesia".

## Testing

Adapter parser tests run against sanitized HTML/JSON fixtures and never hit the live network.
Fixture set per adapter: normal page, pagination, empty page, changed markup, quoted forum post,
promo/signature filtering. Unit-test the score formula, normalization, alias matching, rating
upsert, threshold eligibility, and cursor parsing. Detail: `docs/22-testing-strategy.md`.

## Current implementation status

**Phase 0 (foundation), Phase 1 (findability / Epic 2 search), Phase 2 (sentiment substrate /
Epic 3 + 4), Phase 3 (first observations / Epic 5 + Epic 7 partial), Phase 4 (public score /
Epic 8 + 12), and Phase 5 (coverage expansion / Epic 6) are implemented and verified against a
real PostgreSQL + Redis instance**, not just
SQLite tests: PostgreSQL/Redis are the repository's default connections, Horizon is configured
with the four documented supervisor groups, `/admin` is protected by the authenticated
`access-admin` Gate, the ~200-entity seed CSV imports cleanly (209 entities after adding a
placeholder `Samsung Galaxy A57` product purely to satisfy `docs/02` acceptance criterion 1 —
Samsung has not released that model), and PRD acceptance criteria 1 and 2 (`samsng a57` -> Samsung
Galaxy A57; `vps biznet` -> VPS Biznet Gio and Biznet Gio) pass against live data. This project
does not use a GitHub Actions workflow; run the quality gates locally (`composer test`).

**Phase 6 (Rating Netijen / Epic 9) is implemented and verified against a real PostgreSQL
instance** (155/155 tests passing, pint clean), same bar as Phase 0-5: migrations
(`user_ratings`, `rating_snapshots`, `users.is_banned`) run cleanly against live Postgres, and the
`(user_id, entity_id)` unique constraint was confirmed to reject a duplicate insert with
`UniqueConstraintViolationException` on live Postgres, not just SQLite. Redis was not running
during this verification pass, so the Redis-dependent parts of the suite (`SourceRateLimiter` etc.,
unrelated to Ratings) were not re-exercised here — that was already verified live in Phase 2/4.

**Phase 7 (Public launch readiness / Epic 10 + 11) is implemented and verified against a real
PostgreSQL + Redis instance** (166/166 tests passing, pint clean, phpstan clean), reviewed
4 September 2026: `noindex` now covers below-threshold entity pages, the XML sitemap excludes
thin entities, admin operations diagnostics (`crawl_states`, `ingestion_failures`,
`unmatched_mentions`) with failure replay are live, the source kill switch stops
discovery/fetch within one job run with no deploy, and encrypted daily Postgres backups plus a
`monitor:metrics` alert command are scheduled. Three gaps were found and fixed during this
review — see the implementation notes below.

Search implementation notes:
- `SearchService` covers `docs/13`'s exact / alias / prefix / trigram / category-context tiers,
  plus a `browse` tier (name-ordered listing, no query text) so `/search` and a homepage category
  card have something to show before the user types anything.
- **Known gap against `docs/13` and ADR-004:** full-text search (FTS) on name/category/description
  is not implemented — only `pg_trgm` similarity, exact/prefix matching, and LIKE-based token
  matching. ADR-004 commits to "`pg_trgm` + FTS"; only the first half exists. Low risk for the
  current short entity names, but description-heavy or stemmed queries will under-match. Track
  this as a fast-follow inside Epic 2, not something to build silently as a side effect of an
  unrelated change.
- `pg_trgm` runs against real PostgreSQL; the test suite additionally shims `similarity()` /
  `greatest()` as SQLite functions (`TrigramSimilarity::registerSqliteFunctions()`) so the same
  `SearchService` SQL is exercised in CI without a Postgres dependency. Trust the live-Postgres
  verification over the SQLite-shimmed test run for anything trigram-ranking-specific.
- Autocomplete and live search-as-you-type only fire below a 2-character query, and the
  autocomplete preview and the `/search` page are distinct log paths — noise below that length
  would otherwise dominate the `search_queries` growth-loop log `docs/23` depends on.

Sentiment substrate implementation notes (Epic 3 + 4, verified 2 September 2026):
- `sentiment_observations` rejects a duplicate `(entity_id, source_item_id)` at the database
  level, confirmed against live PostgreSQL (`UniqueConstraintViolationException`), not just the
  SQLite test run.
- `SentimentAggregator` reproduces the `docs/11` 60/20/20 -> 70.0 worked example on both daily and
  snapshot aggregates; `SentimentRankingService` sorts score desc, opinion_count desc, name asc.
- `FakeSourceAdapter` exercises the full Epic 4 DoD through queued jobs:
  discover -> fetch -> extract -> temp storage -> expiry, and a simulated failure on one source
  does not affect a second source running in parallel.
- `SourceRateLimiter` wraps Laravel's `RateLimiter` facade, confirmed to resolve through real
  Redis (`RedisStore`) at runtime, not the array cache.
- **Gap found and fixed during this verification pass:** public-score/ranking thresholds and the
  formula version (`docs/11`'s "thresholds are configuration, not hard-coded truth") were PHP
  constants on `ScoreCalculator`, duplicating `examples/score-config.yaml` instead of reading it.
  Replaced with `config/scoring.php` (`SCORING_PUBLIC_MIN_OPINIONS`, `SCORING_RANKING_MIN_OPINIONS`,
  `SCORING_FORMULA_VERSION` env overrides); `tests/Unit` now boots the Laravel app (no
  `RefreshDatabase`) so plain-class unit tests can call `config()`.

First-observations implementation notes (Epic 5 + Epic 7 partial, verified 2 September 2026):
- All four wave-1 adapters (`DiskusiWebHostingAdapter`, `SerayaMotorAdapter`, `IndoForumAdapter`,
  `BlueskyAdapter`) reach `healthy` preflight and produce >= 1 `CandidateOpinion` against sanitized
  fixtures, never live network (`docs/22`); `DiskusiWebHostingAdapter` drops offers/WTS threads,
  `SerayaMotorAdapter` is scoped to its named sub-forums, `BlueskyAdapter` filters the Jetstream
  firehose by normalized entity alias.
- `EntityMatcher` picks the longest textual match and rejects equal-length ambiguity, so a brand
  match never cascades to a service observation (confirmed with "IDCloudHost" vs.
  "VPS IDCloudHost").
- Mentions without an evaluation, and mentions with an unresolved entity, are written to
  `unmatched_mentions` with a reason code and no PII — never a best-guess entity.
- `UpsertSentimentObservationJob` is idempotent on `(entity_id, source_item_id)`, confirmed live
  on Postgres (`UniqueConstraintViolationException` on a raw duplicate insert).
- **Gaps found and fixed during this verification pass:** `IndoForumAdapter` fell back to crawling
  any numeric forum ID not on its allowlist instead of rejecting it — `discover()` now filters
  `forum_ids` through `FORUM_PATHS` first. The sentiment classifier had never actually been
  evaluated against `docs/22`'s Indonesian test set as the Epic 7 DoD requires — added a
  16-example curated set (formal/slang/typo/mixed-English/emoji/negation/sarcasm), **measured
  accuracy 16/16**; that evaluation also exposed and fixed a bug where emoji-only sentiment always
  returned null because `TextNormalizer::normalize()` stripped emoji before the classifier saw
  them. Typo and sarcasm remain honest, tested ceilings (typo -> null; sarcasm -> neutral), not
  defects. LLM fallback for ambiguous candidates (`docs/10`) is not implemented — deferred until
  ambiguous-candidate volume justifies it.

Public score implementation notes (Epic 8 + Epic 12, verified 2 September 2026):
- PRD acceptance criteria 3-4, 8, 11, 12 pass: score only surfaced at >= 30 opinions, score is
  deterministically recomputable from raw aggregate counts (60/20/20 -> 70.0), `/top/{slug}` and
  `GET /api/categories/{slug}/ranking` reuse `SentimentRankingService::getRanking()` unchanged
  (score desc, opinion_count desc, name asc, no popularity bonus), no `AggregateRating` schema.org
  markup anywhere, and Top Suara Netijen shows the correct empty-state copy
  ("Belum cukup opini untuk merangkum Suara Netijen") below threshold, never a padded/empty list.
- `theme_observations`'s unique `(entity_id, theme_id, source_item_id)` constraint keeps
  `UpsertThemeObservationJob` idempotent, confirmed live on Postgres; theme frequency counts match
  a hand-computed check exactly (5 "cepat" / 3 "murah" observations).
- Epic 12 thresholds live in `config/themes.php` (`THEMES_MIN_ENTITY_OPINIONS`,
  `THEMES_MIN_THEME_OCCURRENCES`, `THEMES_DEFAULT_LIMIT`), same pattern as `config/scoring.php`.
- **Gap found and fixed during this verification pass:** `ThemeExtractor::determineSentimentForTheme()`
  derived a theme's sentiment purely from its canonical-key suffix with no negation handling, so a
  negated mention ("servernya gak cepat sama sekali") was mis-stamped positive — fixed with an
  Indonesian negation-marker window check that flips theme-name-derived polarity when negated.
- The `noindex` gap noted here (entities below the public-score threshold had no `<meta
  name="robots">` mechanism, `docs/13`) was closed in Phase 7 (Epic 10), not in this pass.

Coverage expansion implementation notes (Epic 6, verified 2 September 2026):
- All three wave-2 adapters (`KaskusAdapter`, `YouTubeAdapter`, `LowEndTalkAdapter`) reach
  `healthy` preflight and produce >= 1 `CandidateOpinion` against fixtures, never live network
  (`docs/22`); `KaskusAdapter` treats robots.txt as authoritative and returns `policy_disabled`
  when it disallows all user agents, per `docs/24`'s instruction not to encode historical KASKUS
  policy as fact; `YouTubeAdapter` uses the official Data API and returns `policy_disabled` until
  `YOUTUBE_API_KEY` is configured; `LowEndTalkAdapter` stays scoped to its Reviews/Providers/
  Outages categories. All three sources are seeded `enabled: false`, satisfying the DoD's "before
  it is turned on for backfill" gate.
- **Gap found and fixed during this verification pass:** `KaskusAdapter` and `LowEndTalkAdapter`
  both accept an operator-supplied `thread_urls` cursor override for targeted backfill, but
  `documentRef()` resolved it through `absoluteUrl()` with no host check — unlike the neighboring
  `forum_ids` (IndoForum, Epic 5) and `category_urls` (LowEndTalk's own listing path) allowlists,
  an absolute URL on any host would have been crawled as-is. Fixed with a shared
  `AbstractHttpSourceAdapter::isSameHost()` guard applied in both adapters' `documentRef()`, with a
  regression test per adapter (`WaveTwoAdapterTest`).

Rating Netijen implementation notes (Epic 9, reviewed 3 September 2026):
- `UserRating` (unique `(user_id, entity_id)`) and `RatingSnapshot` (`Ratings` domain) implement
  the `docs/12` upsert model: `PUT /api/entities/{id}/rating` upserts via
  `updateOrCreate(['user_id', 'entity_id'])` so a second submission replaces the first rather than
  logging a new row, `DELETE` removes the contribution, and `RatingAggregator::refresh()`
  recomputes `rating_count`/`rating_average` synchronously on every write — satisfying PRD
  acceptance criterion 7 (`RatingEndpointTest`). The unique constraint was confirmed live on
  PostgreSQL (`UniqueConstraintViolationException` on a raw duplicate insert), not just SQLite.
- `rating.updated`/`rating.deleted` are logged per write and never touch `sentiment_observations`
  or `sentiment_snapshots` — confirmed by a regression test that submits a rating and asserts the
  entity's existing `SentimentSnapshot` row is byte-for-byte unchanged, enforcing ADR-007/011's
  "the three metrics are never merged".
- Anti-abuse minimums from `docs/12` are covered: `auth` + `throttle:ratings` (10/min, keyed by
  user id, IP fallback for guests) on the route group, the `web` middleware group's CSRF applies
  to `routes/web.php` by default, the DB-level unique constraint blocks duplicate contributions,
  and every write and every 429 is logged (`rating.updated`/`rating.deleted`/`rating.rate_limited`).
  No text review/report workflow exists, matching `docs/12`'s explicit MVP scope-out.
- The entity page (`resources/js/pages/Entities/Show.vue`) shows "Rating Netijen" as its own card,
  never merged with Sentimen Netijen, never says "verified rating" (`docs/12`), and prompts a guest
  to log in rather than rendering a disabled control.
- **Gap found and fixed during this review:** `docs/12`'s minimum anti-abuse list includes
  "account ban/admin disable" alongside rate limiting and CSRF — the other four items were
  implemented but this one was entirely missing, so a banned user had no way to be stopped from
  rating even after a burst/anomaly log flagged them. Added `users.is_banned` (migration + cast +
  `User::isBanned()`, mirroring the existing `is_admin` pattern) and wired it into
  `StoreRatingRequest::authorize()` so a banned user gets 403 on new/updated ratings, with a
  regression test (`RatingEndpointTest`). No admin UI toggles it yet — Epic 11's admin
  moderation views don't cover users, only crawl/source operations — so today it's set directly
  (e.g. via `tinker` or a seeder), same MVP posture as the rest of Epic 9.
- **Known gap, not fixed here:** `docs/12`'s auth options are "Google OAuth or email magic link";
  this repo reuses the existing Fortify password + 2FA guard for ratings, same already-tracked
  deviation noted for the rest of the app (see the boundary table below) — not something to
  special-case for ratings alone.
- Existing gap flagged for other epics remains open and unrelated to this review: no FTS
  (Epic 2, ADR-004). The `noindex` gap previously listed here was closed in Phase 7 (Epic 10).

Public launch readiness implementation notes (Epic 10 + 11, verified 4 September 2026):
- `Entities/Show.vue` renders `<meta name="robots" content="noindex, follow">` when
  `sentiment.is_eligible` is false and `index, follow` otherwise, closing the tracked SEO gap from
  Epic 8/12 (`docs/13`). The entity page's JSON-LD only attaches `AggregateRating` when a
  first-party `RatingSnapshot` exists, never for Sentimen Netijen (ADR-007/011).
- `SitemapController` builds `/sitemap.xml` from active/searchable entities that clear the public
  threshold, plus active categories/top-lists and the static trust pages — a below-threshold
  entity never appears. `CategoryShowController` adds the `/category/{slug}` page (search/filter,
  Top Sentimen, Most Discussed, Recently Updated) and `StaticPageController` adds `/methodology`,
  `/sources`, `/about`, `/terms`, `/privacy`.
- Admin operations diagnostics (`AdminOperationsController`) surface `crawl_states`,
  `ingestion_failures`, and `unmatched_mentions` with per-item/per-failure replay, satisfying PRD
  acceptance criterion 10 (every queue/crawler failure visible in Horizon or the admin panel) —
  every job that records an `IngestionFailure` also rethrows, so it lands in Horizon's failed jobs
  too. The source kill switch (`AdminSourceController::toggleStatus`) flips `sources.enabled`,
  and `DiscoverSourceDocumentsJob`/`FetchSourceDocumentJob` both check it before doing any work, so
  a disabled source stops within the next job run with no deploy (confirmed with `Queue::fake()`).
- `BackupDatabaseCommand` produces an encrypted daily `pg_dump` (`storage/app/backups`, `Crypt`
  envelope), prunes to 7 daily + 4 weekly retention, and `--verify` decrypts and sanity-checks the
  newest backup. `CheckSystemMetricsCommand` (`monitor:metrics`) checks queue depth/age, 24h failed
  job count, per-source crawl/preflight success rate, and 24h parse failure rate against
  `docs/16`'s alert list, logging a warning per breach. Both are scheduled in `routes/console.php`
  (`backup:database` daily at 02:00, `monitor:metrics` every 15 minutes).
- **Three gaps found and fixed during this review:**
  1. `SitemapController`'s `whereHas('sentimentSnapshots', ...)` checked threshold eligibility
     against *any* period row, while the eager-loaded snapshot used for `lastmod` was restricted to
     `365d`/`all` — an entity eligible only on a `30d`/`90d` snapshot (not the periods the rest of
     the app treats as the public default, `docs/11`) could pass the `whereHas` check yet have no
     matching eager-loaded snapshot, silently falling back to `$entity->updated_at`. Scoped
     `whereHas` to `365d`/`all` to match the eager load and the entity page's own eligibility logic;
     regression test added (`SitemapAndTrustPagesTest`).
  2. `AdminOperationsController::retryFailure()` handled failures with a `source_item_id` (retries
     `MatchEntitiesJob`) or a `source_document_id` (retries `FetchSourceDocumentJob`), but a
     `discovery`-stage failure has neither — it was silently marked resolved with nothing
     re-queued, misleading whoever clicked "retry". Added a fallback that re-dispatches
     `DiscoverSourceDocumentsJob` for the failure's source; regression test added
     (`AdminOperationsAndKillSwitchTest`).
  3. `docs/16`'s backup requirement includes a monthly restore test; only the daily backup was
     scheduled, with no periodic `--verify` run. Added `backup:database --verify` on a monthly
     schedule (`routes/console.php`).
- **Known gaps, not fixed here:** backups are written to local disk
  (`storage/app/backups`) with no off-host copy step — acceptable for the single-VPS MVP baseline
  in `docs/16`, but worth revisiting before scaling past one host. `monitor:metrics` covers queue
  depth/age, job failure rate, crawl success rate, and parser failure rate from `docs/16`'s list;
  it does not cover opinions/day, unmatched-candidate rate, classifier latency, aggregate
  freshness, search latency, or page p95 — those need real APM/tracing rather than a console
  command and were out of scope for this pass. Mobile usability at 360px (PRD acceptance
  criterion 9) was checked by pattern consistency with the already-verified `Entities/Show.vue` and
  `Top/Show.vue` layouts (same `max-w-5xl` container, `grid-cols-1` mobile-first breakpoints,
  48px-min search inputs) rather than a live Lighthouse/browser run — do a manual pass before
  launch.

**Staging deployment (4 September 2026):** first deploy to a real external environment —
`https://suaranetijen.web.id`, a shared Docker host (nginx-proxy + certbot, shared PostgreSQL,
own Redis per app) alongside other unrelated apps. Deploying for real (not just `composer test`
against local Postgres/Redis) surfaced six bugs that no test run had caught:

1. `config/horizon.php`'s `environments` map had no `staging` key (only `production`/`local`) —
   `php artisan horizon` would have thrown "no environment matched". Added a `staging` entry
   mirroring `local`'s minimal overrides.
2. The repository had no `Dockerfile` at all. Added `Dockerfile` (`php:8.5-apache`, Node build
   stage for Vite/Wayfinder, `composer install` + `npm run build` in one stage),
   `.dockerignore`, and `docker-entrypoint.sh` (caches config/routes/views on boot, then execs
   the container's command — same image serves the app, Horizon, and the scheduler via
   different `command:` overrides in `docker-compose.yml` on the server).
3. `bootstrap/app.php` never called `trustProxies()`, so the app ignored `X-Forwarded-Proto`
   from the staging Nginx reverse proxy and rendered `http://` asset/preload links on an
   `https://` page. Fixed, then a background security review flagged the first fix
   (`trustProxies(at: '*')`) as trusting spoofable headers from *any* client — narrowed to
   RFC1918 private ranges only, matching the app's actual deployment (always behind a proxy on
   a private Docker network, never directly internet-facing). Regression test:
   `tests/Feature/TrustedProxyTest.php`.
4. **Crawler self-rate-limiting caused permanent job failures, not backoff.** First observed
   when `DiskusiWebHostingAdapter` hit its own 30/min limit right after a discovery burst: 21 of
   ~22 fetches died permanently, because `SourceRateLimiter` threw a generic `RuntimeException`
   that `FetchSourceDocumentJob` caught and logged as a permanent `IngestionFailure` — combined
   with Horizon's `tries: 1` default, a self-imposed throttle was indistinguishable from a real
   adapter error. Fixed in two passes: (a) a dedicated `RateLimitExceededException` plus
   `release()`/`tries: 5`/backoff — insufficient once `LowEndTalkAdapter` was enabled and a
   single discovery run queued 102 fetches against its 10/min limit (53 still died with
   `MaxAttemptsExceededException`, since 5 attempts' worth of backoff couldn't cover a 102-item
   backlog); (b) rate-limit hits now `delete()` the current attempt and redispatch a fresh job
   instance with the limiter's exact retry-after delay, up to 30 bounces, entirely decoupled
   from the genuine-error `$tries` budget. Regression tests:
   `tests/Feature/Ingestion/RateLimitRetryTest.php` (recovery and exhaustion paths for both
   `FetchSourceDocumentJob` and `DiscoverSourceDocumentsJob`).
5. **`BlueskyAdapter` cannot work as written.** Confirmed live: Jetstream
   (`jetstream2.us-east.bsky.network/subscribe`) is a WebSocket-only firehose; the adapter's
   `discover()` issues a plain HTTP GET, which the server rejects with `400 Bad Request`. This
   never surfaced in Epic 5's fixture-based tests because fixtures mock a normal HTTP response,
   never the real WebSocket handshake. Disabled in `SourceSeeder` (was seeded `enabled: true`)
   with the root cause recorded in a comment. Needs a proper WebSocket client / persistent
   listener process before re-enabling — a different job shape than the poll-every-30-minutes
   `DiscoverSourceDocumentsJob` pattern the rest of the adapters use, out of scope for this pass.
6. **`KaskusAdapter` cannot work as written either**, for a different reason: `kaskus.co.id`'s
   search page is a Next.js app whose SSR payload ships an empty fallback cache (`"fallback":{}`)
   — results load client-side only, so a plain HTML GET's thread-link parser (`~/thread/~i`)
   always finds zero results, independent of query, robots.txt, or preflight health (which
   reports `healthy` since it only checks reachability, not content). It was already seeded
   `enabled: false`; the fix here is just recording the confirmed root cause in `SourceSeeder`
   so it isn't re-investigated later as "maybe just needs a selector fix". Needs the site's
   underlying JSON API (if publicly reachable) or a JS-rendering fetcher.

Also verified live and **not** bugs, despite looking like ones at first: `IndoForumAdapter` and
`DiskusiWebHostingAdapter` produced source items that were 100% `unmatched_mentions`
(`entity_not_resolved`) in the first crawl cycle — manually replayed several raw payloads through
the real adapters' `extract()` and confirmed the text genuinely doesn't mention any of the
209-seed entities (forum intros, general "any Virtualizor outage?" chatter, etc.) — `EntityMatcher`
correctly discarding rather than guessing, per the precision-over-recall standing constraint.

`YouTubeAdapter` and `LowEndTalkAdapter` were enabled for the first time this session (both were
seeded `enabled: false` pending exactly this kind of live operator check, per Epic 6's DoD). A
dedicated GCP project (`suaranetijen`) and an API-restricted key
(`suaranetijen-staging-youtube`, scoped to YouTube Data API v3 only) were created for
`YOUTUBE_API_KEY`. Both now run clean against live data: `sentiment_observations` went from 0 to
307 in this session, `source_items` to ~12k (YouTube's per-video comment pagination — up to
`YOUTUBE_MAX_COMMENT_PAGES=3` pages per video — accounts for most of that volume).

**Known gap, not fully diagnosed:** the `analysis` queue grew faster than `supervisor-analysis`'s
single worker (staging's Horizon env mirrors `local`'s minimal 1-process default) could drain it
— observed ~2.8k → ~3.9k pending in a few minutes, ~4.3k at last check. Most likely explanation is
legitimate volume (YouTube's comment fan-out landing all at once), not a retry storm — `failed_jobs`
at the same time contained only pre-fix rate-limit/discovery errors, nothing from the `analysis`
queue. Not confirmed either way; worth another look once the backlog has had time to fully drain,
and worth raising `supervisor-analysis` `maxProcesses` for the `staging` Horizon environment if it
doesn't keep up going forward.

**Process note for future staging redeploys:** this session's redeploys used
`docker compose up -d` / `--force-recreate`, which hard-kills the Horizon container instead of
draining it — `php artisan horizon:terminate` (Horizon's documented graceful-restart command)
inside the running container before recreating it would let in-flight jobs finish instead of
being interrupted and requeued. Not done consistently this session; worth scripting into whatever
deploy step replaces manual `docker compose pull && up -d` next.

**Crawler status check (5 September 2026):** live connectivity and pipeline health verified
against staging (`https://suaranetijen.web.id`, SSH to the Docker host) — this is an operational
check, not a code change, except where noted.

- Connectivity: `/` and `/up` both 200, TLS cert valid (Let's Encrypt, expires 3 Dec 2026), SSH to
  the shared Docker host confirmed. All `suaranetijen-*` containers (`app`, `horizon`, `scheduler`,
  `redis`) up 14-15h with no restart loop. Horizon running, all six queues (`critical`,
  `discovery`, `crawl`, `analysis`, `aggregate`, `maintenance`) at 0 pending — the `analysis`
  backlog flagged above (~4.3k pending) has fully drained since.
- Pipeline snapshot at check time: `source_items` 92,860, `sentiment_observations` 3,216,
  `unmatched_mentions` 89,644 (`entity_not_resolved` 70,056, `not_an_evaluation` 19,588),
  `ingestion_failures` 146 (all unresolved, awaiting admin replay per Epic 11 design),
  `crawl_states` 7 rows (one per active source).
- Per-source: `DiskusiWebHosting` and `YouTube` are healthy and actively producing
  (`YouTube`'s comment fan-out accounts for 91,717 of the 92,860 total `source_items`).
  `SerayaMotor` is correctly stalled — every discovery attempt gets an HTTP 403 Cloudflare
  "Just a moment…" challenge page (30 failures logged, `health_state=policy_disabled`; arguably
  should read `blocked` per the `docs/08` health-state enum, but the practical effect — crawling
  halted, no silent bad data — is correct either way). `Bluesky` and `KASKUS` remain disabled per
  the already-documented gaps above.
- **New finding — `IndoForumAdapter` has produced zero `source_items` since staging launch,
  despite reporting `health_state=healthy` and its `crawl_states` row advancing every cycle
  (`page_1` → `page_33` over ~15h with no errors).** Root cause, confirmed by fetching
  `forum.or.id` live: the site now serves a bot-challenge interstitial ("Validating browser…",
  HTTP 200, `robots: noindex,nofollow,noarchive`) on every page, page 1 included — so
  `parseHtmlDocumentLinks()`'s thread-link regex always matches zero links. `preflight()` only
  checks HTTP reachability (200 OK), not that the body contains real content, so this failure mode
  is invisible in the health dashboard — the same blind spot already known for `KaskusAdapter`
  (`docs/24`), just silent instead of loud. **Fixed (5 Sep 2026, `IndoForumAdapter.php`):** tracing
  this surfaced two independent adapter bugs, both root-caused with `superpowers:systematic-debugging`
  and covered by new regression tests in `WaveOneAdapterTest.php` — (a) `discover()` only ever read
  `$forumIds[0]`, so the configured `107` (`info-terbaru-reviews`) and `93` (`computer-stuff`)
  forums were never crawled even once; (b) the page cursor always advanced to `page + 1` regardless
  of `hasMore`/documents found, so it could never wrap back to page 1 to pick up newly posted
  threads. Now rotates to the next configured forum (wrapping back to the first) whenever the
  current page returns zero threads, resetting to page 1 on every rotation. **Still blocked** by
  the site-side bot challenge itself — these fixes make IndoForum correct once `forum.or.id` is
  reachable, but don't unblock it on their own; needs the JS-rendering/challenge-solving fetch path
  below.
- **Fixed (5 Sep 2026, `LowEndTalkAdapter.php`) — `LowEndTalkAdapter` (enabled,
  `health_state=healthy`) had stopped advancing past its initial backfill.** Its `crawl_states` row
  (674 `source_items` produced) hadn't updated since 2026-09-04 09:43, ~22h stale, versus
  `DiskusiWebHosting`/`IndoForum`/`YouTube` which all ran again at 2026-09-05 00:00 via the
  `sources:backfill` scheduler (`*/30 * * * *`, `app/Domains/Sources/Commands/BackfillSourcesCommand.php`),
  despite satisfying `Source::operational()` and being dispatched every cycle with no
  `ingestion_failures` recorded. Root cause, found with `superpowers:systematic-debugging`:
  `discover()` read the resolved category list from `metadata['category_urls']` but only ever
  wrote back the single `metadata['category_url']` it picked; on a source's very first cycle,
  `category_urls` comes from `config()` rather than the input cursor, so it never gets copied into
  metadata at all — the second cycle then finds neither key, resolves an empty category list, and
  returns a null `nextCursor` forever with no exception raised anywhere. Fix: always write the full
  resolved `category_urls` list back into the next cursor's metadata. Regression test in
  `WaveTwoAdapterTest.php` reproduces the exact cold-start cursor shape and asserts a second
  discovery cycle still finds documents.
- **Fix applied:** `config/horizon.php`'s `staging` environment left `supervisor-analysis` at the
  `defaults` block's `maxProcesses => 1`, closing the loop on the "known gap, not fully diagnosed"
  note above. Raised to `maxProcesses => 3` in the repo config, then hot-patched onto both the
  running `suaranetijen-app` and `suaranetijen-horizon` containers and applied with
  `horizon:terminate` (graceful — in-flight jobs finished, container restarted itself under
  Docker's restart policy). Confirmed via `horizon:supervisors`: `supervisor-analysis` is running
  and will auto-scale up to 3 workers under load (`autoScalingStrategy: time`); it sits at 1 while
  the queue is empty, which is expected. **The container patch is a live hot-fix, not yet built
  into a pushed image** — the next full `docker build`/push/redeploy cycle will pick up the repo
  change directly, but if that cycle is skipped, the hand-patch would be lost on a
  `--force-recreate` and need to be reapplied.
- **Recommended for the sources actually blocked by anti-bot/JS-rendering (`KaskusAdapter`,
  `SerayaMotorAdapter`, and now `IndoForumAdapter`):** don't hand-roll a headless browser or
  challenge solver — `FlareSolverr` alone covers all three (see "FlareSolverr integration" below;
  the Browsershot half of this original recommendation was dropped once that became clear).
  **Implemented** in this session, not yet deployed to staging.

**Alternative data source research (5 September 2026):** live feasibility check (robots.txt, API
availability, SSR vs. client-rendering) against `docs/07`'s roadmap candidates plus new candidates,
scored against `docs/07`'s selection criteria (density, category relevance, freshness, incremental
ingestion, access stability, cost, noise, preflight feasibility). Research only — nothing
implemented.

- **Crawlable now, no new infra:** MediaKonsumen (robots.txt allows `/`, WordPress SSR, high
  consumer-complaint density — best near-term fit found); Otomotifnet and Oto.com (Gridoto network;
  robots.txt permissive, SSR, fill the same automotive niche as `SerayaMotorAdapter` with an
  independent second source); Tokopedia review pages (robots.txt explicitly allows `/*/review`
  paths — better than `docs/07` assumed for marketplace reviews, but rendering depth not yet
  confirmed, needs a pilot before committing).
- **Needs the Browsershot/FlareSolverr infra once it exists:** Female Daily (Next.js CSR, narrow
  category fit — beauty isn't in the current seed categories); WebHostingTalk (Cloudflare
  challenge, same wall as `SerayaMotorAdapter`/`forum.or.id`); Carmudi Indonesia (uses the newer
  per-path `Content-Signal` robots directive instead of a blanket allow/deny — needs its own
  parsing pass, not yet done).
- **Stay in `docs/07`'s licensed/paid/later bucket — confirmed, not changed by this research:**
  Trustpilot Data Solutions (`/api/*` and `/reviews/` blocked in robots.txt, licensed-only by
  design); Google Play reviews (official API is owner-only per ToS; the unofficial `batchexecute`
  endpoint is fragile/ToS-risk, not adapter-grade); X API (pay-per-use since the Basic tier's
  Feb 2026 discontinuation, ~$0.005/read — not economical yet per `docs/07`'s own framing);
  TikTok/Instagram (no public firehose, permissioned business APIs only); Reddit (blanket
  `Disallow: /` in robots.txt since its 2024 API lockdown, API now commercial-only — newly
  evaluated, not previously in `docs/07`).
- **Ranked for near-term adapter effort:** MediaKonsumen first (SSR + permissive + zero new infra),
  then Otomotifnet/Oto.com (same effort tier, automotive coverage), then Tokopedia review pages
  pending a rendering-depth check, then Carmudi pending its Content-Signal parsing, then Female
  Daily/WebHostingTalk once Browsershot/FlareSolverr lands.

**FlareSolverr integration + MediaKonsumen adapter (5 September 2026):** acted on the research
above.

- Dropped the earlier Browsershot recommendation once it became clear FlareSolverr alone covers
  all three blocked sources — it loads the URL in a real undetected browser and returns the fully
  rendered/challenge-solved page, so it handles Kaskus's Next.js CSR the same way it handles
  SerayaMotor's Cloudflare 403 and IndoForum's "Validating browser…" wall. This needed **zero new
  Composer dependencies** — FlareSolverr is a separate Docker sidecar called over plain HTTP.
  `AbstractHttpSourceAdapter::request()` now routes through it when an adapter overrides
  `usesChallengeSolver(): true` and `FLARESOLVERR_URL`/`services.flaresolverr.url` is configured,
  wrapping the solved response back into a real `Illuminate\Http\Client\Response` so no adapter's
  `discover()`/`fetch()` needed any change. Falls back to a direct GET when unconfigured, so this
  is a no-op until FlareSolverr is actually deployed. **Still needs deploying**: FlareSolverr itself
  isn't something this repo's `Dockerfile` can start — staging's `docker-compose.yml` (external to
  this repo) needs a `flaresolverr` service added and `FLARESOLVERR_URL` pointed at it before any
  of Kaskus/SerayaMotor/IndoForum actually unblocks live.
- **Bigger finding, found while building MediaKonsumen's test with an exact-URL assertion instead
  of the `str_contains()` every other adapter test used:** `AbstractHttpSourceAdapter::request()`
  had been silently discarding every paginated request's query string on every adapter, the entire
  time. `Illuminate\Http\Client\PendingRequest::get()` only omits Guzzle's `query` request option
  when called with exactly one argument; `request()` always called `->get($url, $query)` with two
  arguments even when `$query` defaulted to `[]`, which set `'query' => []` and made Guzzle replace
  the URL's own embedded query string with nothing. Since every adapter builds its paginated URLs
  by appending `?page=`/`?paged=`/`?start=` directly onto the URL string (never through the
  `$query` array), this meant **every adapter's pagination beyond page 1 was silently re-fetching
  page 1 forever** — independent of, and in addition to, the IndoForum/LowEndTalk cursor bugs fixed
  earlier this session. Fixed: only pass `$query` to `Http::get()` when non-empty. Recorded as a
  durable rule (`.ai/rules/adapters.md`) and the new `AbstractHttpSourceAdapterTest.php` asserts
  exact request URLs rather than `str_contains()`, specifically to catch a regression of this class
  again. **Not yet verified live on staging** — needs a redeploy, same as the IndoForum/LowEndTalk
  fixes above.
- Added `MediaKonsumenAdapter` (`app/Domains/Sources/Adapters/MediaKonsumenAdapter.php`): mirrors
  `DiskusiWebHostingAdapter`'s RSS-feed discovery pattern via the existing
  `parseFeedDocuments()`/`extractHtmlOpinions()` helpers. One WordPress-specific quirk: feed
  archives paginate with `?paged=N`, not the `?page=N` that `pageUrl()` builds for forum-style
  adapters (WordPress silently ignores `page=` on a feed endpoint), so it builds its own URL rather
  than reusing `pageUrl()`. Confirmed live (curl, not through the adapter itself) that
  `mediakonsumen.com/robots.txt` allows crawling and `/feed` returns real complaint/response items
  mentioning seeded entities by name (a live Biznet complaint + the company's own reply, both
  through the same feed). Seeded `enabled: false` in `SourceSeeder`, same DoD as every other wave —
  not yet run against real production traffic.

Current implementation boundary:

| Target per docs | Repository today |
|---|---|
| PostgreSQL | default runtime connection; full suite verified locally |
| Redis queue, cache, locks, rate limits | default runtime drivers; verified locally |
| Horizon supervisors | four documented supervisor groups configured and started locally and on staging (`config/horizon.php` has a `staging` environment entry); `supervisor-analysis` raised from `maxProcesses=1` to `3` on staging (5 Sep 2026 crawler status check) after the analysis-queue backlog noted above |
| `pg_trgm` search | implemented and verified against real PostgreSQL |
| FTS on name/category/description (`docs/13`, ADR-004) | not implemented — tracked gap |
| Sentiment data model (Epic 3) | implemented and verified against real PostgreSQL |
| Adapter framework (Epic 4) | implemented and verified against real PostgreSQL/Redis |
| Wave-1 adapters (Epic 5) | `DiskusiWebHostingAdapter` live and producing on staging; `SerayaMotorAdapter`/`IndoForumAdapter` have their code-level bugs fixed (forum rotation, page wraparound, and the global pagination query-string bug below) and now route through FlareSolverr when configured, but still need FlareSolverr actually deployed as a staging sidecar before they unblock live; `BlueskyAdapter` disabled — Jetstream is WebSocket-only, adapter needs a rewrite (see staging deployment notes) |
| Wave-2 adapters (Epic 6) | `YouTubeAdapter` enabled and dominant producer on staging; `LowEndTalkAdapter`'s cold-start cursor bug is fixed (5 Sep 2026) — needs a staging redeploy to take effect, not yet confirmed live; `KaskusAdapter` stays `enabled: false` but now routes through FlareSolverr when configured (covers its Next.js CSR case too, once deployed) |
| Pagination query-string bug (all adapters) | Fixed (5 Sep 2026) — `AbstractHttpSourceAdapter::request()` was silently discarding every paginated request's query string on every adapter; see the crawler-status notes below. Needs a staging redeploy to take effect. |
| MediaKonsumen adapter | Added (5 Sep 2026), seeded `enabled: false` pending a live operator check, same DoD as every other wave — new source found via the alternative-data-source research below |
| Entity matching, relevance, sentiment classifier (Epic 7) | implemented and verified for the Phase 3 slice; LLM fallback for ambiguous candidates not implemented |
| Public score (Epic 8) | implemented and verified against real PostgreSQL |
| Top Suara Netijen (Epic 12) | implemented and verified against real PostgreSQL; `config/themes.php` thresholds |
| Scoring/ranking thresholds | `config/scoring.php`, mirrors `examples/score-config.yaml` |
| `noindex` for below-threshold entities (`docs/13`) | implemented (Epic 10) — `Entities/Show.vue` sets `robots` meta from `sentiment.is_eligible` |
| Rating Netijen (Epic 9) | implemented and verified against real PostgreSQL (unique-constraint rejection confirmed live) |
| Rating anti-abuse minimums (`docs/12`) | auth, rate limit, CSRF, unique constraint, burst/anomaly logging, and account ban all implemented; ban has no admin UI yet |
| Google OAuth / email magic link (`docs/12`) | Fortify password + 2FA |
| Sitemap, category page, static trust pages (Epic 10) | implemented and verified against real PostgreSQL (below-threshold entities excluded) |
| Admin operations diagnostics + replay, source kill switch (Epic 11) | implemented and verified; every recorded `IngestionFailure` also surfaces in Horizon's failed jobs |
| Encrypted backups + restore verification, ops alerting (Epic 11, `docs/16`) | `backup:database` (daily + monthly `--verify`) and `monitor:metrics` (every 15 min) scheduled; local disk only, no off-host copy; alerting covers queue/job/crawl/parser metrics only, not the full `docs/16` list |
| `app/Domains/*` modules | `Admin`, `Entities`, `Search`, `Sources`, `Ingestion`, `Sentiment`, `Themes`, `Ratings` present; ranking stays in `Sentiment`; `Rankings` and `Moderation` not implemented as separate modules |
| Docker deploy artifacts | `Dockerfile`, `.dockerignore`, `docker-entrypoint.sh` added; image built locally and pushed to Docker Hub (`azmifauzan/suaranetijen`), staging server pulls and runs via its own `docker-compose.yml` (not in this repo) |
| Staging environment | live at `https://suaranetijen.web.id`; see staging deployment notes above for the bugs found and fixed getting there |

The repository's `.env.example` now carries the PostgreSQL + Redis baseline. Tests retain isolated
SQLite/array/sync defaults (with the trigram shim above) unless an explicit integration run
overrides them.

## Document map

| File | Purpose |
|---|---|
| `docs/01-product-vision.md` | Vision, positioning, product principles |
| `docs/02-prd-mvp.md` | MVP PRD, user stories, acceptance criteria |
| `docs/03-scope-entity-taxonomy.md` | Entity types, hierarchy, categories |
| `docs/04-ux-information-architecture.md` | Search-first IA, mobile UX rules |
| `docs/05-technical-architecture.md` | Stack and modular monolith layout |
| `docs/06-domain-data-model.md` | Tables and raw-content policy |
| `docs/07-source-strategy-registry.md` | Source selection, roles, governance |
| `docs/08-source-adapter-specs.md` | Per-adapter contract and health states |
| `docs/09-crawler-ingestion-indexer.md` | Pipeline, dedupe, incremental crawl |
| `docs/10-entity-matching-opinion-filter.md` | Entity resolution and relevance filter |
| `docs/11-sentiment-scoring-ranking.md` | Score formula, thresholds, ranking |
| `docs/12-first-party-rating-moderation.md` | Star rating and anti-abuse minimum |
| `docs/13-search-seo.md` | Search ranking, SEO page model, sitemap |
| `docs/14-api-jobs-scheduler.md` | Endpoints, queues, jobs, schedule |
| `docs/15-security-privacy-governance.md` | Security, privacy, source guardrails |
| `docs/16-observability-deployment.md` | Deployment, metrics, logs, backup |
| `docs/17-implementation-backlog.md` | Epics, order, launch gate |
| `docs/18-roadmap.md` | Product, source, and scale roadmap |
| `docs/19-success-metrics.md` | Product, index, and source KPIs |
| `docs/20-risk-register.md` | Risks and mitigations |
| `docs/21-architecture-decisions.md` | ADR-001..011, frozen decisions |
| `docs/22-testing-strategy.md` | Unit, adapter fixture, NLP, E2E strategy |
| `docs/23-seed-entity-strategy.md` | ~200 seed entity plan |
| `docs/24-current-reference-baseline.md` | Externally validated facts (2 Sep 2026) |
| `docs/25-top-suara-netijen.md` | Theme Index / Top Suara Netijen: pipeline, data model, ranking, scope |

Config examples: `examples/score-config.yaml`, `examples/source-registry.yaml`,
`examples/queue-topology.yaml`, `examples/.env.example`.
