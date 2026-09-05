# SuaraNetijen

> Sebelum pilih, cek kata netizen.

Search-first public sentiment index for Indonesia. Search a brand, product, or service and see
whether public conversation about it leans positive or negative.

**Baseline:** 4 September 2026 · **Market:** Indonesia · **Status:** Phase 0 (foundation), Phase 1
(search), Phase 2 (sentiment substrate), Phase 3 (first observations), Phase 4 (public score),
Phase 5 (coverage expansion), Phase 6 (first-party rating), and Phase 7 (public launch readiness)
verified locally against PostgreSQL/Redis

## Open source & lisensi

SuaraNetijen adalah proyek open source untuk publik. Kode sumber dan dokumentasi tersedia di
[GitHub](https://github.com/azmifauzan/suaranetijen) dan kode proyek ini dirilis di bawah
[MIT License](LICENSE). Lisensi ini berlaku untuk kode dan dokumentasi repository; dependensi serta
data/konten pihak ketiga tetap mengikuti lisensi dan ketentuan sumber masing-masing.

## Product definition

SuaraNetijen indexes public opinion about an **entity**, turns each relevant opinion into a
positive / neutral / negative classification, then aggregates that into **Sentimen Netijen** and
category rankings.

Third-party content is processing input, not republished product. The durable assets are the
entity graph, sentiment observations, historical aggregates, rankings, and first-party ratings.

### Public metrics

| Metric | Range | Source |
|---|---|---|
| Sentimen Netijen | 0-100 | aggregated crawler sentiment |
| Top Suara Netijen | ranked themes | most-discussed themes, by frequency, from the same opinion data (`docs/25`) |
| Rating Netijen | 1-5 | first-party user star ratings |
| Jumlah Opini | count | relevant observations in the period |
| Distribusi Sentimen | % | positive / neutral / negative split |
| Trend Sentimen | time series | schema supports it; not required for first launch |

Sentimen Netijen, Top Suara Netijen, and Rating Netijen are **never merged** — provenance has to
stay obvious for each. Top Suara Netijen is theme frequency, never a numeric per-theme score —
it is not aspect scoring.

### Score formula v1

```text
score = 100 * (positive_count + 0.5 * neutral_count) / opinion_count
```

Equivalent to positive=100, neutral=50, negative=0. A score goes public at >= 30 opinions and
becomes ranking-eligible at >= 100 (`examples/score-config.yaml`).

## MVP frozen scope

- ~200 seed entities, types `brand` / `product` / `service`.
- Search-first, mobile-first, SEO-first.
- Laravel modular monolith, Inertia + Vue + Tailwind.
- PostgreSQL as source of truth and as the MVP search engine (`pg_trgm` + FTS).
- Redis from day one for queue, cache, locks, and rate limits; Horizon for queue observability.
- Source adapters: YouTube, KASKUS, DiskusiWebHosting, SerayaMotor, IndoForum (selective),
  Bluesky, LowEndTalk (targeted), MediaKonsumen, plus first-party ratings.
- Top Suara Netijen: theme extraction/normalization and Top 5 frequency ranking per entity
  (`docs/25`) — no numeric per-theme score, no per-category aspect taxonomy.
- Out: specification database, price comparison, aspect scores, expert scores, benchmarks,
  verified purchase, opinion fact checking, and political entities.

## Stack as installed

| Layer | Installed |
|---|---|
| PHP | 8.4 (composer requires `^8.3`) |
| Framework | Laravel 13 |
| Frontend | Inertia v3 + Vue 3.5 + Tailwind 4, Vite 8 |
| Auth | Laravel Fortify (incl. two-factor) |
| Routing types | Laravel Wayfinder |
| Database | PostgreSQL |
| Queue / cache | Redis + PhpRedis |
| Queue operations | Laravel Horizon 5.x |
| Tests | Pest |
| Lint / static analysis | Pint, PHPStan, `vue-tsc` |

Horizon runs the documented `supervisor-critical`, `supervisor-crawl`, `supervisor-analysis`, and
`supervisor-maintenance` groups. The test suite keeps SQLite/array/sync defaults unless an explicit
integration run supplies PostgreSQL and Redis settings.

## Getting started

```bash
composer setup     # install deps, .env, key, migrate, npm install, build
composer dev       # serve app, queue worker, and Vite together
```

Other useful scripts:

```bash
composer test        # config clear + pint check + phpstan + artisan test
composer lint        # pint --parallel
composer types:check # phpstan
composer ci:check    # npm check + vue-tsc + test
```

## Current implementation status

**Phase 0 (foundation), Phase 1 (search), Phase 2 (sentiment substrate), Phase 3 (first
observations), Phase 4 (public score), Phase 5 (coverage expansion), Phase 6 (first-party
rating), and Phase 7 (public launch readiness)** are implemented and verified against real
PostgreSQL and Redis, not just SQLite tests.
Admin access uses an authenticated `access-admin` Gate,
non-admin users receive 403, the ~200-entity seed CSV imports cleanly (209 entities), and local
lint, static analysis, and tests pass.

Search (`/`, `/search`, `GET /api/search`) implements PRD acceptance criteria 1 and 2 — typo and
multi-word matching — verified against live seed data. One tracked gap: full-text search on
name/category/description (`docs/13`, ADR-004) is not implemented, only `pg_trgm` similarity,
exact/prefix matching, and token-based matching.

Epic 3 (sentiment data model) and Epic 4 (adapter framework) implement `docs/17`'s Definition of
Done exactly: `sentiment_observations` rejects a duplicate `(entity_id, source_item_id)` at the
database level (verified live on Postgres); the aggregate/ranking services reproduce the
60/20/20 -> 70.0 worked example from `docs/11`; `FakeSourceAdapter` runs
discover -> fetch -> extract -> temp storage -> expiry end-to-end via queued jobs, and one
adapter's simulated failure doesn't affect another running in parallel; `SourceRateLimiter` goes
through real Redis at runtime. A gap was found and fixed during this verification pass: public
score / ranking thresholds and the formula version were hard-coded PHP constants instead of
reading `examples/score-config.yaml` — now sourced from `config/scoring.php`
(`SCORING_PUBLIC_MIN_OPINIONS`, `SCORING_RANKING_MIN_OPINIONS`, `SCORING_FORMULA_VERSION` env
overrides).

Epic 5 (source adapters wave 1) and the Phase-3 slice of Epic 7 (entity matching, relevance,
sentiment) implement `docs/17`'s Definition of Done: `DiskusiWebHostingAdapter`,
`SerayaMotorAdapter`, `IndoForumAdapter`, and `BlueskyAdapter` all reach `healthy` preflight and
produce at least one `CandidateOpinion` against fixtures (never live network, per `docs/22`);
`EntityMatcher` never lets a brand match cascade to a service observation; unresolved or
non-evaluative mentions go to `unmatched_mentions`, never a best-guess entity;
`UpsertSentimentObservationJob` is idempotent on `(entity_id, source_item_id)`, confirmed live on
Postgres. Two gaps were found and fixed during this verification pass: `IndoForumAdapter` didn't
actually enforce its forum-ID allowlist (any numeric ID would still crawl); and the sentiment
classifier had never been evaluated against `docs/22`'s Indonesian test set as the Epic 7 DoD
requires — now measured at 16/16 on a curated formal/slang/typo/mixed-English/emoji/negation/
sarcasm set, which also surfaced and fixed an emoji-stripping bug that made emoji-only sentiment
always return null.

Epic 8 (public score) and Epic 12 (Top Suara Netijen) implement `docs/17`'s Definition of Done:
PRD acceptance criteria 3-4, 8, 11, and 12 pass — score only shown at >= 30 opinions, deterministic
recomputation from raw counts (60/20/20 -> 70.0), `/top/{slug}` reuses the Epic 3 ranking query
unchanged (score desc, opinion_count desc, name asc, no popularity bonus), no `AggregateRating`
schema.org markup, and Top Suara Netijen shows theme frequency with the correct empty-state copy
below threshold, no numeric per-theme score. A gap was found and fixed during this pass:
`ThemeExtractor` derived a theme's sentiment purely from its canonical-key suffix with no negation
handling, mis-stamping negated mentions (e.g. "servernya gak cepat sama sekali") as positive —
fixed with an Indonesian negation-marker check. The `noindex` gap noted here for below-threshold
entities was closed in Phase 7 (Epic 10).

Epic 6 (source adapters wave 2) implements `docs/17`'s Definition of Done: `KaskusAdapter`,
`YouTubeAdapter`, and `LowEndTalkAdapter` all reach `healthy` preflight and produce at least one
`CandidateOpinion` against fixtures, never live network; `KaskusAdapter` and `YouTubeAdapter` each
report an explicit `policy_disabled` state (robots disallow-all for KASKUS, missing
`YOUTUBE_API_KEY` for YouTube) instead of guessing; all three sources are seeded `enabled: false`
so wave 2 stays off until an operator records a live preflight result. A gap was found and fixed
during this pass: `KaskusAdapter` and `LowEndTalkAdapter` both accept an operator-supplied
`thread_urls` override for targeted backfill, and resolved it with no host check — unlike the
neighboring `forum_ids`/`category_urls` allowlists, an absolute URL on any host would have been
crawled as-is. Fixed with a shared `isSameHost()` guard on `AbstractHttpSourceAdapter`.

Epic 9 (Rating Netijen) implements `docs/17`'s Definition of Done: `PUT`/`DELETE
/api/entities/{id}/rating` upsert on `(user_id, entity_id)`, never insert-only, with
`RatingAggregator` recomputing `rating_snapshots` synchronously on every write and a regression
test confirming rating writes never alter `sentiment_snapshots` (ADR-007/011). `docs/12`'s minimum
anti-abuse list (auth, rate limit, CSRF, unique constraint, burst logging, account ban) is
implemented; a gap was found and fixed during this pass — "account ban/admin disable" was the one
item from that list with no implementation at all, so `users.is_banned` was added and enforced in
the rating request's `authorize()`. No admin UI toggles the flag yet (Epic 11's admin views cover
crawl/source operations, not users). The unique constraint was confirmed to reject a duplicate
insert with `UniqueConstraintViolationException` live on Postgres.

Epic 10 (UX/SEO) and Epic 11 (Operations) implement `docs/17`'s Definition of Done: `noindex`
meta on below-threshold entity pages plus a `/sitemap.xml` that excludes the same entities, the
category page and static/trust pages, admin diagnostics (`crawl_states`, `ingestion_failures`,
`unmatched_mentions`) with failure replay, a source kill switch that stops discovery/fetch with no
deploy, and encrypted daily Postgres backups plus a scheduled `monitor:metrics` alert command.
Three gaps were found and fixed during this pass: the sitemap's eligibility check wasn't scoped to
the same `365d`/`all` periods used for `lastmod`, so a `30d`/`90d`-only eligible entity could slip
through inconsistently; a `discovery`-stage ingestion failure had no retry path in the admin panel
(neither a document nor an item exists yet at that stage) and silently marked itself resolved
without re-queuing anything; and `docs/16`'s monthly restore-test requirement had no scheduled
`--verify` run. **Known gaps, not fixed here:** backups are local-disk only, and PRD acceptance
criterion 9 (360px usability) was checked by pattern consistency with already-verified pages, not
a live Lighthouse/browser run.

The `Admin`, `Entities`, `Search`, `Sources`, `Ingestion`, `Sentiment`, `Themes`, and `Ratings`
domain modules are present. Implementation order lives in `docs/17-implementation-backlog.md`.

### Phase status

Phases and epic mapping match `docs/18-roadmap.md`.

| Phase | Epics | Status |
|---|---|---|
| 0. Foundation | 0, 1 | Done |
| 1. Findability | 2 | Done |
| 2. Sentiment substrate | 3, 4 | Done |
| 3. First observations | 5, 7 (partial) | Done |
| 4. Public score | 8, 12 | Done |
| 5. Coverage expansion | 6 | Done |
| 6. First-party rating | 9 | Done |
| 7. Public launch readiness | 10, 11 | Done |

## Documentation

`docs/` holds 25 numbered documents and is the source of truth for product, schema, and
architecture decisions. `CLAUDE.md` / `AGENTS.md` carry the condensed version for coding agents.

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

Config examples live in `examples/`: `score-config.yaml`, `source-registry.yaml`,
`queue-topology.yaml`, `.env.example`.

## Implementation rule

> Build a modular monolith. Separate a component only after a measured bottleneck or ecosystem
> requirement makes separation clearly beneficial.

No Kafka, Kubernetes, dedicated search cluster, separate Python service, or microservices in the
MVP without evidence of need.
