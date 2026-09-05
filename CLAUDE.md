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

`MediaKonsumenAdapter` (RSS-based) was built earlier this session but not yet deployed or seeded
on staging; both were done now (5 Sep 2026) — image rebuilt/pushed, staging recreated, `SourceSeeder`
run, `sources.mediakonsumen.enabled` confirmed `true`, 0 `IngestionFailure` rows in the 15 minutes
after enabling.

`MojokAdapter` (Mojok.co's `/esai/` category) was also built and deployed the same session — the
original CSR finding on this data source turned out to apply only to the homepage's feed widget,
not the `/esai/` archive/article pages themselves, which are plain WordPress SSR (confirmed with a
plain `curl`, no JS). Same RSS-discover + `entry-content`-style extract pattern as
`MediaKonsumenAdapter`, including the identical `?paged=N` feed-pagination quirk. Enabled on
staging for a live check: `sources:preflight` + `sources:backfill` run manually right after
enabling rather than waiting for the 30-minute scheduler, confirmed `health_state=healthy`,
2 `source_items` produced from the first feed page, 0 `ingestion_failures`, cursor advanced to
`page_2`.

**Bug found and fixed while doing this: re-running `SourceSeeder` on staging is not safe as a
generic "add one source" operation.** `youtube` and `lowendtalk` were flipped to `enabled: true`
directly in the staging DB earlier this session (live operator check, Epic 6's DoD), but the
seeder file itself still had `'enabled' => false` for both — `Source::updateOrCreate()` overwrites
`enabled` on every run, so seeding MediaKonsumen silently reset both back to disabled. Caught by
diffing `Source::all()->pluck('enabled','key')` right after the seed instead of assuming it was a
no-op for unrelated rows; fixed live (re-flipped both to `true`) and fixed the seeder file itself
(dropped the stale `enabled: false` lines, added a dated comment) so the seeder is idempotent with
actual live state going forward. Lesson: after any live-state DB fix made outside the seeder
(admin toggle, tinker, direct SQL), the seeder file must be updated in the same session — otherwise
it silently reverts on the next reseed, with no error or warning.

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
  again.
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

**Redeploy and live verification (5 September 2026, same session):** built and pushed a new
`azmifauzan/suaranetijen:latest` image containing every fix above, pulled it on staging, and
recreated `suaranetijen-app`/`-horizon`/`-scheduler` (`horizon:terminate` first for a graceful
stop, per the process note above). No pending migrations.

- **New operational gotcha, not previously documented:** the redeploy caused a live 502 —
  `nginx-proxy`'s generated vhost config uses a static `proxy_pass http://suaranetijen-app:80;`
  with no `resolver` directive, so nginx resolves and caches that hostname's IP once and doesn't
  re-resolve on its own. `--force-recreate` gives the container a new internal Docker network IP,
  and because the vhost config *text* didn't change, nginx-proxy's docker-gen watcher didn't detect
  a diff and skipped its usual auto-reload — leaving nginx routing to a dead IP. Fixed with
  `docker exec nginx-proxy nginx -t && docker exec nginx-proxy nginx -s reload` (a zero-downtime
  reload, not a restart — confirmed the co-hosted `satsetops.com` stayed up throughout). **Add this
  reload as an explicit step in any future redeploy that recreates `suaranetijen-app`.**
- **LowEndTalk's cursor-bug fix needed a one-time data repair on top of the code fix**, discovered
  when the first post-redeploy `sources:backfill` cycle left `LowEndTalk`'s `crawl_states` row
  exactly as frozen as before. Root cause: the row's `metadata` had been written by the *old, buggy*
  code before this session's fix existed, so it already contained the poisoned singular
  `category_url` key with no `category_urls` — the fixed read-side code (`?? $cursor->metadata['category_url'] ?? config(...)`)
  still resolves the stale singular string first and short-circuits before ever reaching
  `config()`, exactly like the original bug, just for a different reason (legacy data, not the
  write-back logic). The code fix only prevents *new* poisoning; it can't retroactively heal a row
  already poisoned by the pre-fix code. One-time fix: reset that row's `metadata` to `{}` (and
  `cursor_value`/`last_external_id` to null) so the next cycle falls through to `config()` cleanly,
  same as a fresh source's first-ever run. **Confirmed live**: the very next `sources:backfill`
  cycle (2026-09-05 06:00 UTC) advanced `LowEndTalk`'s cursor to `page_2` for the first time since
  2026-09-04 09:43, and `source_items` for it went 674 → 829 (+155 real new items, not
  re-discovered duplicates — confirmed via `source_documents`'s unique constraint dedup).
- **Confirmed live**: `IndoForumAdapter`'s rotation/wraparound fix is working exactly as designed —
  its cursor cycled `forum_139_page_1` → `forum_107_page_1` → (next cycle) `forum_139_page_1` again
  across three consecutive `sources:backfill` runs, i.e. it tried 139, got zero threads, rotated to
  107, got zero threads, wrapped back to 139 rather than paginating one forum forever. The
  code-level bugs are genuinely fixed; whether it produces data now depends on `forum.or.id`'s
  bot-challenge, covered below.
- `DiskusiWebHosting` and `YouTube` kept growing normally through the redeploy (613 and 96,557
  `source_items` respectively at last check) — no regression from any of this session's changes.
- `MediaKonsumen` stayed seeded `enabled: false` pending a live operator check at the time this
  note was written; enabled later the same day (see the entry above the "known gap" note further
  below) — its code had never run against production before that, only sanitized fixtures and a
  manual `curl` of the real site.

**FlareSolverr deployment and per-source reliability (5 September 2026, same session):** added a
`suaranetijen-flaresolverr` service (`ghcr.io/flaresolverr/flaresolverr:latest`) to staging's
external `docker-compose.yml` (backed up first), set `FLARESOLVERR_URL` in staging's `.env`, and
recreated the app/horizon/scheduler containers to pick it up. **Results are source-specific, not a
uniform "it works now" — tested each of the three sources 3+ times directly against FlareSolverr
before trusting the adapters:**

- **KASKUS: reliable, 3/3 successful, re-enabled in production.** FlareSolverr's response for every
  attempt said `"Challenge not detected!"` — Kaskus's block was never actually Cloudflare, just the
  Next.js client-side render that a real browser executes trivially. Ran `sources:preflight` to
  refresh `health_state` and flipped `sources.kaskus.enabled` to `true` directly in the staging DB;
  `SourceSeeder.php` updated to match so a fresh environment starts the same way.
  `KaskusAdapter::preflight()` reports `healthy` through the app.
- **SerayaMotor: mostly reliable, 2/3 successful, left enabled.** This one *is* a real Cloudflare
  challenge — FlareSolverr actively solves it (`"Challenge solved!"`), but one attempt in three hit
  `"Error solving the challenge. Timeout after 60.0 seconds."` A ~33% discovery-cycle failure rate
  is not zero, but it's the same shape of transient failure the existing retry/backoff machinery
  (`DiscoverSourceDocumentsJob`'s `$tries = 5`) already handles — it should still produce data over
  a run of `sources:backfill` cycles, just not on every single attempt. `health_state` was refreshed
  from stale `policy_disabled` to `healthy` via `sources:preflight`.
- **IndoForum: unreliable but not a lost cause — produces some data through sheer volume of
  attempts.** `forum.or.id` runs a **non-Cloudflare custom bot-detection system** (its own
  `/js/zee/botguard/` script, "Validating browser…" page) that FlareSolverr's built-in challenge
  detector doesn't recognize — most attempts returned `"message": "Challenge not detected!"`
  alongside the *same* unsolved challenge HTML (3-5KB), meaning FlareSolverr didn't know to wait for
  the page's own JS to finish and just returned the initial load. Roughly 1 in 4 manual attempts got
  through with real content, almost certainly a timing fluke (the page's own redirect JS completing
  before FlareSolverr captured the DOM) rather than something to rely on per-request. Left enabled
  since the code-level rotation/wraparound bugs are genuinely fixed and it's harmless to keep
  trying — worth handling properly in a future session (a FlareSolverr `session`-based flow with an
  explicit longer wait, or a custom pierce with `waitForNavigation`/`waitForSelector` instead of
  FlareSolverr's Cloudflare-shaped heuristic) if the current trickle isn't enough.

**Found a second dormant rotation bug and production results after two more `sources:backfill`
cycles (same session):** the first post-FlareSolverr cycle (06:30) produced zero `source_items` for
all three sources despite FlareSolverr working when tested manually — traced to `SerayaMotorAdapter`
having the *exact same* forum-rotation bug as `IndoForumAdapter` (only ever read `forum_ids[0]`,
cursor never wrapped). This was already known — flagged during the IndoForum fix's Phase 2
pattern-comparison earlier this session — but left alone as moot since SerayaMotor was blocked by
Cloudflare regardless. FlareSolverr unblocking it made the dormant bug active: production was stuck
re-paging forum 19 past its real content (page 2+) with zero results, having never touched forums
64/63. Fixed with the identical rotation/wraparound pattern, rebuilt and redeployed the image
(same `horizon:terminate` + `--force-recreate` + `nginx -s reload` sequence as before). **Confirmed
live** after two more cycles (07:30, 08:00 UTC): `source_items` went `SerayaMotor` 0→1,956,
`IndoForum` 0→68 (the ~25% FlareSolverr luck rate adding up over many attempts), `Kaskus` 0→6 (small
but real and growing) — `sentiment_observations` 3,538→3,925, zero new `ingestion_failures`. All
three previously-blocked sources are now producing real data in production.

Current implementation boundary:

| Target per docs | Repository today |
|---|---|
| PostgreSQL | default runtime connection; full suite verified locally |
| Redis queue, cache, locks, rate limits | default runtime drivers; verified locally |
| Horizon supervisors | four documented supervisor groups configured and started locally and on staging; `supervisor-analysis` raised 1→3 and `supervisor-crawl` raised 2→6 on the main staging host after live backlog findings; distributed to two additional worker hosts (5 Sep 2026, `staging-worker` environment, `supervisor-crawl`/`supervisor-analysis` only) — confirmed all three hosts coexist in one `horizon:supervisors` listing over the same Redis |
| `pg_trgm` search | implemented and verified against real PostgreSQL |
| FTS on name/category/description (`docs/13`, ADR-004) | not implemented — tracked gap |
| Sentiment data model (Epic 3) | implemented and verified against real PostgreSQL |
| Adapter framework (Epic 4) | implemented and verified against real PostgreSQL/Redis |
| Wave-1 adapters (Epic 5) | `DiskusiWebHostingAdapter` live and producing on staging; `SerayaMotorAdapter` also had a dormant forum-rotation bug (fixed alongside FlareSolverr) — confirmed live producing real data (0→1,956 `source_items` over two cycles); `IndoForumAdapter`'s bot-detection (non-Cloudflare, FlareSolverr doesn't recognize it) makes it unreliable but not blocked — confirmed live at 0→68 `source_items` via the ~25% pass-through rate; `BlueskyAdapter` disabled — Jetstream is WebSocket-only, adapter needs a rewrite (see staging deployment notes) |
| Wave-2 adapters (Epic 6) | `YouTubeAdapter` enabled and dominant producer on staging; `LowEndTalkAdapter`'s cold-start cursor bug is fixed and deployed, plus a one-time `crawl_states` data repair for the row it had already poisoned — confirmed live (674→829 `source_items`, first cursor advance since 2026-09-04); `KaskusAdapter` re-enabled (5 Sep 2026) — FlareSolverr-deployed and reliable (3/3 in live testing), its block was never Cloudflare, just the Next.js client-side render |
| Pagination query-string bug (all adapters) | Fixed and deployed (5 Sep 2026) — `AbstractHttpSourceAdapter::request()` was silently discarding every paginated request's query string on every adapter; see the crawler-status notes below. |
| MediaKonsumen adapter | Added and enabled on staging (5 Sep 2026) after passing fixture tests and a live check (0 `IngestionFailure` in the first 15 minutes) — new source found via the alternative-data-source research below |
| Mojok.co adapter | Added and enabled on staging (5 Sep 2026) — `/esai/` archive/article pages are WordPress SSR despite the homepage widget being CSR; live check produced 2 `source_items`, 0 `IngestionFailure`, cursor advanced |
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
| Entity candidate pipeline (`docs/23` "Growth") | implemented (5 Sep 2026), not yet deployed to staging — closes the previously-untouched gap where zero-result search queries were logged but nothing turned that into new-entity candidates |
| Shared LLM settings (`llm_settings`, `/admin/llm-settings`) | implemented — OpenAI-compatible chat completions over plain HTTP, no new SDK dependency; meant to be the one place any future LLM feature (e.g. the still-unimplemented `docs/10` ambiguous-entity-matching fallback) resolves its client through |
| Tokoh Publik category / `person` entity type (ADR-010 override) | `EntityType::Person` + Tokoh Publik category tree implemented, 25 seed entities added, generic pipeline unchanged; `kaskus_politik` Source seeded `enabled: false` pending live check; not yet deployed to staging |

The repository's `.env.example` now carries the PostgreSQL + Redis baseline. Tests retain isolated
SQLite/array/sync defaults (with the trigram shim above) unless an explicit integration run
overrides them.

## Entity candidate pipeline (5 September 2026)

Answers "does the seed list stay current with new brands/products" — before this, only a manual
CSV edit + `php artisan app:import-seed-entities` re-run could add entities; `docs/23`'s own
"Growth" section (log zero-result queries, promote high-frequency ones) was never built past the
logging half.

- **4 pluggable sources** behind `App\Domains\Entities\Contracts\EntityCandidateSource`
  (`app/Domains/Entities/CandidateSources/`): `SearchQueryCandidateSource` (primary signal —
  zero-result `search_queries` grouped by `normalized_query`, frequency >= `config('entity_candidates.min_search_query_frequency')`,
  default 3), `WikidataCandidateSource` (free SPARQL endpoint, no auth, covers
  Smartphone/Mobil/Motor in one query), `DailySocialCandidateSource` (RSS, new Indonesian
  digital-economy brands), `GoogleTrendsCandidateSource` (free Daily Trends RSS for Indonesia,
  `approx_traffic` as weight). One source failing never blocks the others (same resilience
  principle as the crawler adapters) — see `EntityCandidateAggregator::collectFromSources()`.
- **`unmatched_mentions` is a cross-reference booster, not a fifth source.** A live measurement
  this session found it's ~99.8% genuinely irrelevant text (78,559 of 78,689 `entity_not_resolved`
  mentions had zero entity-name overlap even at a lenient tie-match check; only 130 were truly
  ambiguous ties between two seeded entities) — not worth building free-text extraction against.
  `EntityCandidateAggregator::countUnmatchedMentions()` just counts how often an already-surfaced
  candidate term appears in `raw_payloads` as supporting evidence.
- **LLM enrichment** (`EntityCandidateEnricher` + `LlmClient`): OpenAI-compatible `/chat/completions`
  over plain `Http::` calls (no Anthropic/OpenAI SDK dependency — deliberately dropped mid-build
  when asked for an OpenAI-compatible shape instead), requesting a JSON-schema response
  (`suggested_name`, `suggested_entity_type` restricted to `EntityType::cases()`,
  `suggested_category` matched case-insensitively against real `Category` names — an unmatched
  category is left `null` for the admin to pick, never guessed/created). Only genuinely-new
  candidates get enriched (an existing `entity_candidates` row, any status, never resurfaces or
  re-enriches).
- **Shared LLM settings, not hardcoded to this one feature.** `llm_settings` (single row) holds
  `base_url`/`model`/`api_key` (Laravel `encrypted` cast)/`max_tokens`/`temperature`/
  `timeout_seconds`, editable at `/admin/llm-settings` with immediate effect (same "no redeploy"
  pattern as the source kill switch) — a blank `api_key` on save keeps the stored one rather than
  clearing it. Falls back to `config('services.llm.*')`/env when no row exists yet. Every future
  LLM-backed feature (e.g. `docs/10`'s ambiguous-entity-matching LLM fallback, still not built) is
  meant to resolve its client through this same `LlmClient`, not read env/config directly.
- **Admin review queue** at `/admin/entity-candidates`: pending candidates ranked by
  `frequency_score` desc, each showing raw terms/source types/unmatched-mention count/LLM
  reasoning, with editable name/type/category/parent-brand/aliases fields pre-filled from the
  suggestion. Approve creates the `Entity` + primary alias + any additional aliases and links
  `entity_candidates.entity_id`; reject sets `status=rejected` — permanent dismissal, matching the
  same "no resurfacing" decision as the crawler's `unmatched_mentions`.
- Scheduled weekly (`entities:scan-candidates`, `routes/console.php`) — curation is not a
  real-time concern.
- **Not yet deployed to staging** — needs a redeploy (build+push image, pull, recreate, migrate)
  same as every other change this session, plus an admin visit to `/admin/llm-settings` to set a
  real `base_url`/`model`/`api_key` before the weekly scan can actually enrich anything (no
  external LLM endpoint configured by default; `services.llm.base_url` defaults to
  `api.openai.com/v1` but `LLM_API_KEY` is blank).

## Extended crawler monitoring, second worker-starvation bug (5 September 2026)

Continued monitoring after the FlareSolverr rollout (several more hours, matching the earlier
"pantau lagi beberapa jam" ask) surfaced one more real bug, distinct from the FlareSolverr
reliability findings above.

- **Bug found and fixed: staging's `crawl` queue backed up to 924 pending (oldest 39 minutes),
  starving fast sources behind slow ones.** `FetchSourceDocumentJob` for every source shares one
  `crawl` queue; FlareSolverr-routed fetches (SerayaMotor/Kaskus/IndoForum) take far longer per job
  than a plain HTTP fetch, and `supervisor-crawl` was still on staging's default 2-worker cap.
  `DiskusiWebHosting` had 20 documents stuck in `discovered` state for hours — not failing, just
  queued behind hundreds of slower jobs; `source_documents.last_seen_at` kept advancing every
  discovery cycle (proving discovery still found them fine) while `state` never moved past
  `discovered`. Fixed by raising `supervisor-crawl` to `maxProcesses: 6` on staging (`config/horizon.php`,
  matching production's existing value) and hot-patching both live containers the same way as the
  earlier `supervisor-analysis` fix. **Confirmed live**: DiskusiWebHosting went from 20 stuck
  documents to 0 (56/56 `fetched`) within minutes of the extra workers coming online.
- **Not a bug, a volume/rate-limit characteristic worth knowing about: Kaskus's 9 documents stayed
  in `discovered` state even after the fix**, while SerayaMotor (also FlareSolverr-routed) grew
  from 0 to 6,406 `source_items` in the same window. Confirmed Kaskus isn't actively rate-limited
  (`source_crawl_limiter:6` key doesn't exist — `TTL` returns `-2`) — it's queue-position
  starvation, not a lock. Kaskus's `crawl_policy.rate_limit_per_minute` is 10, the lowest of any
  enabled source, and YouTube's comment fan-out dispatches such high volume into the same shared
  `crawl` queue (the backlog refilled to 820 within minutes of draining to 0) that a source with
  only a handful of jobs and a tight rate limit can end up waiting a long time for its turn purely
  on FIFO position, even with plenty of workers running. **Not fixed here** — a real fix would mean
  segregating high-volume sources (YouTube) onto their own queue so low-volume sources don't
  compete with them for the same worker pool, a bigger topology change than this session's
  worker-count tuning; `examples/queue-topology.yaml`'s single shared `crawl` queue was a deliberate
  design choice, not an oversight, so this needs a real discussion before changing, not a quick
  patch. Worth revisiting if Kaskus (or any other low-rate-limit source) stays starved for days
  rather than hours.

## Distributed crawl workers (5 September 2026)

Two additional hosts (`erp-live`, `myneterp` — **shared servers already running unrelated
production ERP workloads**, not dedicated boxes) were added as Horizon worker nodes to help drain
the `crawl`/`analysis` bottleneck, on top of the worker-count tuning above.

- **No application code changes were needed.** Laravel/Horizon queue consumption is already
  stateless — any host with the right `.env` (`DB_HOST`/`REDIS_HOST` pointing at the shared
  instances) running `php artisan horizon` pulls from the same named queues. Confirmed
  `RawPayloadStorage` is fully DB-backed (`raw_payloads` table), not local-disk, so it's safe
  across hosts; the only local-disk writer in the app (`BackupDatabaseCommand`) stays main-host-only
  by simply not running the scheduler on the worker hosts.
- **Infra work done:** Redis (previously passwordless, internal-network-only) now requires a
  password (`REDIS_PASSWORD`, Laravel's `encrypted`-style secret handling doesn't apply here since
  it's a plain env var — rotate it if this doc's history is ever public) and is published on
  `6379:6379`, restricted via `iptables -I DOCKER-USER` to only the two worker IPs (confirmed live:
  reachable from both workers, blocked from an arbitrary IP) — **not** relying on `ufw`, because
  **Postgres on this same host is already published to `0.0.0.0:5432` and reachable from the open
  internet despite `ufw` not listing 5432 as allowed** (Docker's own iptables rules bypass `ufw`
  for published container ports — confirmed live from an external network). That Postgres exposure
  predates this session, wasn't introduced by this work, and was **not changed** here — tightening
  it risks breaking other apps on this "shared PostgreSQL" host without knowing who else connects;
  flagged here as a real, standing security finding for whoever owns that host to address
  deliberately. The new `DOCKER-USER` iptables rules are **not persisted across reboot** (no
  `netfilter-persistent`/`iptables-persistent` installed) — same gap the pre-existing port
  8080/9100 restrictions on this host already had, not a new fragility introduced here, but worth
  fixing if this host reboots.
- Each worker runs its own local `FlareSolverr` (avoids a network hop for browser rendering and
  avoids exposing that service externally too) plus a `suaranetijen-horizon-worker` container
  (`php artisan horizon`, no app/scheduler role) pointed at the main host's public IP for
  `DB_HOST`/`REDIS_HOST`, and a new `staging-worker` `APP_ENV`/Horizon environment
  (`config/horizon.php`) so they get their own worker allocation (`supervisor-crawl` maxProcesses 4,
  `supervisor-analysis` maxProcesses 2) instead of the main host's.
- **Bug found and fixed immediately after first deploying this: Horizon rejects
  `minProcesses => 0` and crash-loops on *every* environment defined in the config file at boot,
  not just the one selected.** The first version of `staging-worker` tried to fully disable
  `supervisor-critical`/`supervisor-maintenance` there with `minProcesses: 0, maxProcesses: 0` —
  this took down Horizon on the *main* staging host too (plain `APP_ENV=staging`, which never
  touched the broken key), confirmed via `ProvisioningPlan.php`'s validation error
  (`"The value of [supervisor-critical.minProcesses] must be greater than 0"`) and a live restart
  crash-loop. Fixed by minimizing to `minProcesses: 1, maxProcesses: 1` instead — Horizon's actual
  floor is one idle worker per supervisor, not zero. Validated locally
  (`new \Laravel\Horizon\ProvisioningPlan(...)` against all four environments) before redeploying a
  second time. **Confirmed live**: all three hosts now appear together in one `horizon:supervisors`
  listing, sharing the same Redis-backed queues — distributed crawl is working.

## Search results were never wired to the real score (found and fixed, 5 September 2026)

Asked directly "does search show real results now that there's real data" — checked the DB first
(89 entities have `sentiment_snapshots`, 26 clear the public threshold; Samsung leads at 1,632
opinions / score 72.12 for `365d`), then hit the live `/api/search?q=samsung` endpoint to confirm —
it returned `score: null, opinion_count: 0` for every result, contradicting the DB entirely.

- **Root cause**: `SearchService::mapRow()` hardcoded `'score' => null, 'opinion_count' => 0,
  'rating' => null, 'rating_count' => 0` with a comment reading `// Sentimen Netijen (null for MVP
  until Epic 8)` — a stub written before Epic 8 (public score) existed, never updated once Epic 8
  actually shipped. `EntityShowController` (the entity page) had the correct 365d→all-time
  fallback + eligibility-threshold logic all along — `SearchService` just never called it, so this
  bug was invisible on the entity page and only showed up in the search results list.
- **Fix**: `SearchService::fetchPublicData()` batch-fetches `SentimentSnapshot` and `RatingSnapshot`
  for every result in one query each (not per-row), applies the same
  `ScoreCalculator::isPublicScoreEligible()` threshold check and 365d→all-time fallback
  `EntityShowController` already used, so a below-threshold entity still reports its
  `opinion_count` but withholds `score` — consistent behavior between the search list and the
  entity page. **Confirmed live**: `/api/search?q=samsung` now returns
  `"score":72.12,"opinion_count":1632`, matching the database exactly.
- This shipped with the redeploy in the distributed-worker session above — only the main app host
  needed it (search is web-facing, not something the crawl/analysis workers touch).

## Tokoh Publik category (5 September 2026)

Added a `person` entity type and a Tokoh Publik category (children: Politisi, Selebriti & Artis,
Atlet, Pengusaha, Kreator Konten) at explicit operator request, **overriding ADR-010** (politics
was previously deferred — see the override note on ADR-010 in `docs/21` and the corresponding
`docs/18`/`docs/02` updates). No separate political-entity module or compliance review was built;
`person` entities run through the same generic entity-centric pipeline as `brand`/`product`/
`service` (matching, sentiment, scoring, ranking), unchanged.

- `SeedEntityImporter::$categoryTaxonomy` and `ensureCategoriesExist()` gained the 5 new
  child categories under a new `Tokoh Publik` parent; 25 seed entities (type `person`) were added
  to `database/data/seed_entities.csv` across all 5 subcategories, aliases included, same CSV
  format as every existing row. `SeedEntityImporterTest`'s `>=` count assertions cover the larger
  totals without needing changes.
- **Source research, evaluated against the working-adapter pattern (native comments/threads, SSR,
  no AI-crawl ban) before building anything:** Kompasiana rejected (robots.txt blocks
  `/komentar/*` and explicitly disallows ClaudeBot/GPTBot/anthropic-ai with an AI/LLM-mining
  prohibition). Okezone's dedicated "Tokoh" RSS feed rejected (dead since 2017, and it's static
  bio profiles, not netizen opinion). Kapanlagi.com and IDN Times both checked live: permissive
  robots.txt, SSR article pages, but **no comment section at all** on either — pure third-person
  journalism, no netizen opinion signal to extract, so neither is adapter-grade despite passing
  the robots/SSR checks. Kumparan explicitly allows ClaudeBot/GPTBot in robots.txt (unusual,
  favorable) but is CSR (`Sedang memuat...` shell, same problem class as the original Kaskus
  Next.js finding) — flagged as a real future candidate once FlareSolverr-based selectors are
  actually verified live, not built blind.
- **Chosen source: `kaskus_politik`**, a new `Source` row (`SourceSeeder.php`) reusing the
  existing `KaskusAdapter` unchanged — `crawl_policy.listing_url` scopes it to the confirmed-real
  "Berita dan Politik Indonesia" subforum (`kaskus.co.id/komunitas/1167/berita-dan-politik-indonesia`),
  same scoping pattern as LowEndTalk's `category_urls`. No new adapter class. Seeded
  `enabled: false` pending a live operator check, same DoD gate as every other source added this
  project.
- **Bug found and fixed, without which the above is moot:** `YouTubeAdapter`/`KaskusAdapter`
  auto-search every active+searchable entity's name (`DiscoverSourceDocumentsJob`). YouTube
  rotates through them correctly, but `KaskusAdapter::discover()` only ever read
  `queries[0]` and then baked the resulting search URL into `metadata['listing_url']` on the
  first cycle — from then on the persisted `listing_url` short-circuited any re-derivation from
  `queries`, so Kaskus's generic entity-name search has been stuck on a single entity's name
  forever, since it first launched. Same root-cause shape as the earlier IndoForum/SerayaMotor
  `forum_ids[0]`-only bug. Fixed by splitting `discover()` into an explicit-listing-url path
  (unchanged behavior, used by `kaskus_politik` and any future single-category Kaskus source) and
  a query-rotation path (`query_index`, rotates on an empty results page, wraps back to the first
  query — mirrors `IndoForumAdapter`'s `forum_index` fix exactly). Regression tests added to
  `WaveTwoAdapterTest.php` (rotate, wrap, and explicit-listing-url-ignores-queries cases). Without
  this fix, adding Tokoh Publik entities would not actually have gotten them searched via Kaskus.
- **Not yet deployed to staging** — needs a redeploy (build+push image, pull, recreate, migrate)
  same as every other change this session, plus running `php artisan db:seed --class=SourceSeeder`
  (or the full seeder) to pick up the new `kaskus_politik` row, and re-running the entity seed
  import to pick up the 25 new `person` entities.

## Admin UX and LLM pipeline fixes (5 September 2026, same session)

Follow-up after the theme-pipeline fix above: user reported the search-result click-through still
felt broken, couldn't find the LLM settings menu, and admin saves gave no feedback. All confirmed
live and fixed.

- **Admin sidebar had no navigation at all beyond "Dashboard".** Every admin page (entities,
  categories, sources, operations, entity candidates, LLM settings) was only reachable by typing
  the URL directly or clicking through `/admin`'s dashboard cards — `AppSidebar.vue`'s
  `mainNavItems` was still the unmodified starter-kit default. Added an "Admin" nav group (shown
  only when `auth.user.is_admin`) linking to every admin sub-page.
- **Every admin controller's save gave zero visible feedback — the actual root cause of "LLM
  settings save looks like it does nothing."** `AdminCategoryController`, `AdminEntityController`,
  `AdminEntityAliasController`, `AdminSourceController`, `AdminEntityCandidatesController`,
  `AdminOperationsController`, and `AdminLlmSettingsController` all used
  `redirect()->back()->with('success', '...')` — a plain session flash key that
  `HandleInertiaRequests` never shared as an Inertia prop and no frontend code ever read. Confirmed
  live: an LLM settings save returned a clean 303 with no error, but the row was never
  persisted, and there was no toast either to reveal the silent failure. Switched every one of
  these to `Inertia::flash('toast', [...])` — the mechanism already working correctly in
  `Settings/ProfileController`/`SecurityController` (Inertia v3's `flash()` + the existing
  `initializeFlashToast()` + vue-sonner wiring, `router.on('flash', ...)`).
- **The Max Tokens/Temperature/Timeout fields had no error display at all**, unlike Base
  URL/Model/API Key. Confirmed live: submitting `max_tokens: 1000000` (over the 128000 cap)
  correctly failed validation server-side (`props.errors.max_tokens` present in the raw Inertia
  response) and the new `onError` toast fired, but nothing pointed at *which* field was wrong — no
  `<p v-if="form.errors.max_tokens">` existed for that field group. Added it for all three fields.
  `max_tokens` is the per-request completion output cap, not a model's context window — the 128000
  ceiling is intentional and wasn't raised; a user hitting it should lower the value, not expect a
  bigger cap.
- **Toast position moved from bottom-right to top-right** (`components/ui/sonner/Sonner.vue`
  default) — the user reported never seeing the bottom-right toast on their screen.
- **The click-through fix from earlier in this session (`Search/Index.vue`'s `pointer-events-none`
  on the "Lihat detail" arrow) was verified live via real browser automation, not just
  `elementFromPoint()` reasoning**: before the fix, a click centered exactly on the "Lihat detail"
  text resolved to a plain `<span>` inside the arrow-indicator `<div>` (not the stretched-link
  overlay), confirming the hover-triggered `group-hover:translate-x-0.5` transform really was
  promoting that decorative element above the link. After the fix redeployed, the same click
  resolved to the overlay `SPAN.absolute.inset-0` and navigated to `/e/samsung` correctly.
- **`APP_KEY` stability check (asked directly, since `llm_settings.api_key` is the first `encrypted`-cast
  column in production):** confirmed no `key:generate` call anywhere in `Dockerfile`/
  `docker-entrypoint.sh`, confirmed `APP_KEY` lives in staging's host-side `.env`
  (`/home/dev/compose/suaranetijen/.env`, outside the image, untouched by `--force-recreate`), and
  functionally confirmed by decrypting a saved `api_key` across several redeploys in this session.
  **Known gap, not fixed**: `backup:database` only backs up Postgres, never `.env`/`APP_KEY` — if
  the host is ever lost, the DB backup alone cannot recover any `encrypted`-cast column (just
  `llm_settings.api_key` today) since ciphertext is useless without the matching key. `APP_KEY`
  should be stored somewhere separate from this host (password manager/secrets vault).
- **Entity candidate approval crashed with a 500** (`POST /admin/entity-candidates/{id}/approve`)
  the first time it was exercised against a real LLM-enriched candidate ("XLSMART"): the
  suggested-aliases list included the entity's own name, and `approve()` always created a primary
  alias from `name` first, then blindly inserted every suggested alias afterward — colliding with
  `entity_aliases`' `(entity_id, normalized_alias)` unique constraint mid-transaction (rolled back
  cleanly, no orphaned data, but the candidate stayed stuck `pending`). Fixed by tracking normalized
  aliases already inserted (seeded with the primary) and skipping any later one — from the LLM or a
  same-request duplicate — that normalizes the same. Regression test added
  (`AdminEntityCandidatesTest.php`).
- **First live LLM connectivity + `entities:scan-candidates` run, real credentials
  (`https://ai.sumopod.com`, `qwen3.7-flash-2026-07-15`)**: raw `/chat/completions` call returns
  200 with a real completion (confirmed the model also returns a `reasoning_content` field
  alongside `content` — `LlmClient` only reads `choices.0.message.content`, so this is harmless,
  just worth knowing if debugging why a response "looks empty" when tested with a non-JSON prompt,
  since `LlmClient::chat()` always attempts `json_decode` on the content and returns `[]` on
  anything that isn't valid JSON — expected for its structured-extraction design, not a
  connectivity problem). The scan created 34 pending candidates.
  `GoogleTrendsCandidateSource`'s top-ranked candidates by `frequency_score` were almost entirely
  sports-match and transit-schedule noise ("Man City vs Coventry City" at 20,000, "Jadwal KRL Solo
  Jogja" at 200) drowning out genuine brand candidates like XLSMART (score 1) — Google's Daily
  Trends feed has no brand/product filter, so any viral search term scores far higher than an
  actual candidate ever could. **Fixed same session**: see the relevance-filter entry below. The 10
  confirmed sports/transit rows from this first run (`entity_candidates` ids 25-34) were manually
  flipped to `rejected` afterward — the fix only changes what a *future* scan does, it doesn't
  retroactively re-judge rows a scan already created.
- **Fix: `EntityCandidateEnricher` can now say a candidate isn't a real entity at all**, instead of
  being forced to invent one. Its JSON schema had every `suggested_*` field `required` with no
  escape hatch, so the LLM always rationalized *something* — the football match got suggested as a
  "brand" under "Brand Umum". Added a required `is_relevant` boolean; when false,
  `EntityCandidateAggregator::scan()` persists the candidate as already `rejected` (never shown for
  admin review) instead of `pending`, while still recording it so the same term isn't re-enriched
  (and re-billed) on the next weekly scan. Broadened the prompt so a specific named public figure
  is a legitimate `person` candidate (`EntityType::Person` already exists), not noise, even when
  politics-adjacent — only the underlying policy/political topic is out of scope, not the person
  themself.
- **Two more data-quality issues found while reviewing this first scan's 34 candidates, not fixed
  this session:**
  1. `WikidataCandidateSource` produced two candidates whose `normalized_term`/`suggested_name` is
     a raw Wikidata entity ID (`q141025060`, `q139719408`) instead of a resolved human-readable
     label — the SPARQL query or its result mapping isn't always pulling the label field. Needs
     its own investigation; not touched here.
  2. `DailySocialCandidateSource`'s RSS item titles are often a single run-on headline covering
     3 unrelated stories (e.g. "Ajaib secures $270M mega round, GoTo leadership reshuffle, 500
     Global sunsets, Sea fund" as one title) — the LLM still extracts one genuine, real brand name
     out of the mess (Ajaib, Grab, Xendit, SeaBank, Kopi Kenangan, etc. were all correctly pulled),
     so these are NOT noise like the Google Trends case and were left as-is, but the
     `normalized_term` dedup key is the entire garbled headline rather than the actual brand —
     worth a future pass to split/clean the feed's titles before they reach the aggregator, so the
     dedup key means something and doesn't create one candidate row per daily digest email instead
     of one per brand.

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
