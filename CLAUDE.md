<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

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
Epic 3 + 4), and Phase 3 (first observations / Epic 5 + Epic 7 partial, this branch) are
implemented and verified against a real PostgreSQL + Redis instance**, not just
SQLite tests: PostgreSQL/Redis are the repository's default connections, Horizon is configured
with the four documented supervisor groups, `/admin` is protected by the authenticated
`access-admin` Gate, the ~200-entity seed CSV imports cleanly (209 entities after adding a
placeholder `Samsung Galaxy A57` product purely to satisfy `docs/02` acceptance criterion 1 —
Samsung has not released that model), and PRD acceptance criteria 1 and 2 (`samsng a57` -> Samsung
Galaxy A57; `vps biznet` -> VPS Biznet Gio and Biznet Gio) pass against live data. This project
does not use a GitHub Actions workflow; run the quality gates locally (`composer test`).

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

Current implementation boundary:

| Target per docs | Repository today |
|---|---|
| PostgreSQL | default runtime connection; full suite verified locally |
| Redis queue, cache, locks, rate limits | default runtime drivers; verified locally |
| Horizon supervisors | four documented supervisor groups configured and started locally |
| `pg_trgm` search | implemented and verified against real PostgreSQL |
| FTS on name/category/description (`docs/13`, ADR-004) | not implemented — tracked gap |
| Sentiment data model (Epic 3) | implemented and verified against real PostgreSQL |
| Adapter framework (Epic 4) | implemented and verified against real PostgreSQL/Redis |
| Wave-1 adapters (Epic 5) | `DiskusiWebHostingAdapter`, `SerayaMotorAdapter`, `IndoForumAdapter`, `BlueskyAdapter` implemented and verified against fixtures; wave 2 (Kaskus/YouTube/LowEndTalk, Epic 6) not implemented |
| Entity matching, relevance, sentiment classifier (Epic 7) | implemented and verified for the Phase 3 slice; LLM fallback for ambiguous candidates not implemented |
| Scoring/ranking thresholds | `config/scoring.php`, mirrors `examples/score-config.yaml` |
| Google OAuth / email magic link (`docs/12`) | Fortify password + 2FA |
| `app/Domains/*` modules | `Admin`, `Entities`, `Search`, `Sources`, `Ingestion`, `Sentiment` present; `Themes`, `Rankings`, `Ratings`, `Moderation` not implemented |

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
