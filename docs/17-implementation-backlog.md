# 17 - Implementation Backlog

Sequencing follows dependency, not calendar time. An epic starts only when every epic it
depends on is done. "Done" means the Definition of Done at the end of this document is met, not
just that code exists.

## Dependency graph

```text
Epic 0 (foundation)
  -> Epic 1 (entity catalog)
       -> Epic 2 (search)
       -> Epic 3 (sentiment data model)
            -> Epic 4 (adapter framework)
                 -> Epic 5 (adapters wave 1) -> Epic 7 (matching/relevance/sentiment)
                                                    -> Epic 8 (public score)
                                                    -> Epic 12 (Top Suara Netijen / theme index)
                 -> Epic 6 (adapters wave 2, after wave 1 pipeline is proven)
       -> Epic 9 (Rating Netijen, needs auth from Epic 0 + entities from Epic 1)
Epic 2, Epic 8, Epic 9, Epic 12 -> Epic 10 (UX/SEO, needs real content to not ship thin pages)
Epic 4, Epic 5/6 running -> Epic 11 (operations, needs live crawl jobs to diagnose)
```

Epic 12 runs in parallel with Epic 8 — both read the same Epic 7 relevant-opinion output through
independent branches (`docs/09`), so neither blocks the other.

Epic 6 intentionally starts after Epic 5 produces observations end-to-end — it validates the
pipeline on lower-compliance-risk adapters first (`docs/09`, reason repeated below).

## Epic 0 - Repository foundation

- Laravel 13 project, PHP per `composer.json` (`^8.3`, installed 8.5).
- Inertia + Vue 3 + Tailwind wiring (already present in the starter kit).
- PostgreSQL connection replacing the starter's SQLite default (`docs/05`).
- Redis connection for queue/cache/locks/rate limits replacing the starter's `database` driver.
- Horizon installed and configured with supervisors `supervisor-critical`,
  `supervisor-crawl`, `supervisor-analysis`, `supervisor-maintenance` (`docs/16`).
- Base admin auth: gate an `/admin` route group behind an authenticated + authorized admin user
  (Fortify is already installed for the public side).

**Definition of done:** `composer dev` runs web + queue worker + Vite against Postgres/Redis; an
authenticated non-admin user gets 403 on `/admin`; local quality gates pass.

**Implementation status (verified 2 September 2026):** complete locally. `composer dev` registers
the web server, Horizon, logs, and Vite; all four documented Horizon supervisors start against an
isolated Redis database; the full 60-test suite passes against PostgreSQL/Redis and via
`composer test`; and the admin access matrix passes.

## Epic 1 - Entity catalog

- Migrations for `entities`, `entity_aliases`, `categories` with the fields listed in `docs/06`
  (`type`, `slug`, `parent_id` nullable, `searchable`, `rankable`, `status`, etc.).
- Admin CRUD for entities, categories, and aliases, including disable-without-delete
  (`status`) per `docs/02` admin requirements.
- Seed importer that reads the CSV shape produced by the `docs/23` selection workflow (name,
  type, category, parent, aliases, description) and upserts entities/aliases/categories
  idempotently.
- Public entity route `/e/{slug}` rendering name, type, category — no sentiment yet (Epic 8 adds
  that data later without changing this route's shape).

**Definition of done:** the ~200-entity CSV from `docs/23` imports cleanly twice in a row with no
duplicates; `/e/{slug}` resolves for every imported entity.

## Epic 2 - Search

- Text normalization (case, whitespace, punctuation) shared by search and entity matching
  (Epic 7 reuses this, do not fork it).
- `pg_trgm` indexes on `entities.name`, `entity_aliases.normalized_alias`.
- Autocomplete API implementing the match priority from `docs/13`: exact name, exact alias,
  prefix, trigram similarity, category context.
- Search results page consuming the same API.
- `search_queries` logging (query text, result count, timestamp) — this is the primary input to
  `docs/23`'s zero-result growth loop, so log it even though nothing reads it yet.

**Definition of done:** acceptance criteria 1-2 from `docs/02` PRD pass (`samsng a57` finds
Samsung Galaxy A57; `vps biznet` finds VPS Biznet Gio and Biznet Gio).

## Epic 3 - Sentiment data model

- `sources`, `source_documents`, `source_items` migrations (`docs/06`).
- `sentiment_observations` with the unique constraint `(entity_id, source_item_id)` — this is
  the idempotency guarantee every later job depends on.
- `sentiment_daily` and `sentiment_snapshots` (periods `30d|90d|365d|all`).
- Ranking query: `score desc, opinion_count desc, name asc` (`docs/11`), built and tested against
  fixture rows before any adapter produces real data.

**Definition of done:** given hand-seeded rows in `sentiment_observations`, the aggregate and
ranking queries return the exact numbers the `docs/11` worked example predicts (60/20/20 -> 70).

**Implementation status (verified 2 September 2026):** complete locally against real PostgreSQL.
`sentiment_observations` rejects a duplicate `(entity_id, source_item_id)` at the database level
(verified against live Postgres, not just SQLite); `SentimentAggregator` reproduces the 60/20/20
-> 70.0 worked example on daily and snapshot aggregates; `SentimentRankingService` sorts
score desc, opinion_count desc, name asc. Public/ranking thresholds and the formula version now
read from `config/scoring.php` (mirrors `examples/score-config.yaml`) instead of hard-coded
constants — a gap found and fixed during this verification pass.

## Epic 4 - Adapter framework

- `SourceAdapter` contract: `preflight()`, `discover(cursor)`, `fetch(ref)`, `extract(doc)`
  (`docs/08`).
- Cursor/state persistence (`crawl_states`) so discovery is incremental, not a full re-scan.
- Per-source Redis token-bucket rate limiting (`docs/09`).
- Temporary raw payload storage with per-adapter TTL (`raw_ttl_hours` from
  `examples/source-registry.yaml`) and an `ExpireRawPayloadJob`.
- Source health state machine: `healthy`, `degraded`, `blocked`, `policy_disabled`,
  `parser_broken`, `quota_limited` (`docs/08`), each independently disable-able.

**Definition of done:** a fake/no-op adapter implementing the contract runs through
discover -> fetch -> extract -> (temp storage) -> expiry entirely via queued jobs, with one
adapter's simulated failure not affecting a second adapter running in parallel.

**Implementation status (verified 2 September 2026):** complete locally, including against real
Postgres and Redis. `FakeSourceAdapter` runs discover -> fetch -> extract -> temp storage ->
expiry end-to-end through `DiscoverSourceDocumentsJob`/`ExpireRawPayloadJob`; a simulated fetch
failure on one source leaves a second source's run unaffected; `SourceRateLimiter` resolves
through `RedisStore` at runtime (confirmed live, not the array test double); `crawl_states` cursor
persistence and the six-state health machine (`docs/08`) are implemented as specified. No adapter
beyond the fake one exists yet — that is Epic 5/6, not this epic.

## Epic 5 - Source adapters wave 1

1. `DiskusiWebHostingAdapter` — testimonial/complaint/hosting/VPS/ISP forums only, exclude
   offers/WTS.
2. `SerayaMotorAdapter` — Review Corner, Suggestion Corner, Our Voices, selected Common Topics.
3. `IndoForumAdapter` — allowlisted forum IDs only.
4. `BlueskyAdapter` — Jetstream consumer, filtered by normalized entity aliases.

Reason for this order: HTML/community and firehose adapters validate the full pipeline without
first depending on YouTube quota approval or KASKUS's stricter runtime preflight (`docs/07`,
`docs/24`).

**Definition of done:** all four adapters reach `healthy` in a preflight check; each produces at
least one `CandidateOpinion` against a live fixture entity within a scheduled backfill run.

## Epic 6 - Source adapters wave 2

5. `KaskusAdapter` — public thread pages only; runtime preflight is authoritative, no
   assumptions from `docs/24` carried into code as fact.
6. `YouTubeAdapter` — official Data API; derived-sentiment storage follows the 36-month
   retention/refresh rule in `docs/24`.
7. `LowEndTalkAdapter` — Reviews/Providers/Outages categories and targeted discovered threads.

**Definition of done:** same bar as Epic 5, plus a documented preflight result per adapter (pass
or explicit `policy_disabled` reason) before it is turned on for backfill.

## Epic 7 - Entity matching, relevance, sentiment

- Alias matching stages: normalize -> exact alias -> token/phrase -> context disambiguation ->
  optional LLM fallback for ambiguous candidates only (`docs/10`).
- Parent/service rule enforced in code, not just convention: a brand match never auto-creates a
  service-level observation.
- Opinion relevance filter separating "mentions entity" from "evaluates entity" (`docs/10`
  examples).
- Sentiment classifier (positive/neutral/negative) evaluated against the Indonesian test set from
  `docs/22` (formal, slang, typo, mixed English, emoji, negation, sarcasm) before it processes
  live traffic.
- Idempotent `UpsertSentimentObservationJob`: a retry on the same `source_item_id` must not
  create a second observation.
- Ambiguous or unmatched candidates go to `unmatched_mentions`, never a best-guess entity.

**Definition of done:** classifier precision/recall on the curated set meets a documented
threshold (record the number when first measured — this doc does not fix one); a job replay
against the same fixture batch produces zero duplicate observations.

## Epic 8 - Public score

- Formula v1 (`docs/11`): `score = 100 * (positive + 0.5 * neutral) / opinion_count`.
- Thresholds from `examples/score-config.yaml`: public at >= 30 opinions, ranking-eligible at
  >= 100.
- `30d/90d/365d/all` snapshot refresh, default period `365d` with `all` fallback for new
  entities.
- Category ranking query surfaced on `/top/{slug}` reusing the Epic 3 ranking query unchanged.
- `sentiment_model_version` and `score_formula_version` stamped on every snapshot.

**Definition of done:** acceptance criteria 3-4 and 8 from `docs/02` PRD pass (score only shown
above threshold; score is deterministically recomputable from aggregate counts; ranking uses only
score + eligibility, no popularity bonus).

**Implementation status (verified 2 September 2026):** complete locally against real PostgreSQL.
PRD acceptance criteria 3-4 and 8 pass (`PublicScoreThresholdTest`, `CategoryRankingEndpointTest`):
score is only surfaced at >= 30 opinions, score is deterministically recomputable from aggregate
counts (60/20/20 -> 70.0), and ranking on `/top/{slug}` and `GET /api/categories/{slug}/ranking`
uses score desc, opinion_count desc, name asc without popularity bonus, excluding entities below 100 opinions.

## Epic 12 - Top Suara Netijen (theme index)

Full spec: `docs/25`. Runs in parallel with Epic 8 — both branch off Epic 7's relevant-opinion
output independently.

- `themes`, `theme_aliases`, `theme_observations`, `entity_theme_daily`,
  `entity_theme_snapshots` migrations (`docs/06`).
- Theme extraction reading the same relevant-opinion stream Epic 7 already classifies for
  sentiment — one opinion can yield multiple theme+sentiment pairs.
- Canonical theme normalization/clustering (keyword normalization + Indonesian synonym
  dictionary at minimum; embeddings/LLM fallback for ambiguous cases only, same posture as Epic 7
  entity-matching fallback).
- Frequency aggregation and Top 5/10 ranking query (`SUM(observation_count) ... ORDER BY total
  DESC`), default window 12 months falling back to lifetime for sparse entities.
- Minimum threshold: entity >= 30 qualified opinions AND theme >= 3 occurrences to display; below
  that, "Belum cukup opini untuk merangkum Suara Netijen".
- Positive/negative theme grouping, reusing the same theme+sentiment observations (no separate
  pipeline).
- Explicitly out of Epic 12: no numeric per-theme score, no per-category aspect taxonomy, no
  Top-10 toggle UI, no theme-based search (`docs/18` post-MVP).

**Definition of done:** acceptance criteria 11-12 from `docs/02` PRD pass (below-threshold entity
shows the empty-state copy, not a padded/empty list; a duplicated/templated opinion does not
inflate `observation_count`); Top 5 for a fixture entity matches a hand-computed frequency count
exactly.

**Implementation status (verified 2 September 2026):** complete locally against real PostgreSQL.
PRD acceptance criteria 11-12 pass (`TopThemesThresholdTest`, `ThemeDataModelTest`):
`theme_observations` unique constraint `(entity_id, theme_id, source_item_id)` prevents duplicate observation
inflation; below-threshold entities (< 30 opinions or < 3 occurrences per theme) show the empty-state
copy "Belum cukup opini untuk merangkum Suara Netijen" rather than a padded or empty list;
Top 5 themes and positive/negative groups ("Netijen Paling Suka" and "Paling Sering Dikeluhkan")
display frequency observation counts without numeric per-theme scores.

## Epic 9 - Rating Netijen

- Auth: Google OAuth or email magic link (`docs/12`) — separate concern from the admin auth in
  Epic 0.
- 1-5 star upsert, unique `(user_id, entity_id)`, delete removes the contribution.
- `rating_snapshots` recomputation (sync or async refresh — either is acceptable per `docs/12`).
- Rate limiting, CSRF, burst/anomaly logging (`docs/15` minimum anti-abuse list).

**Definition of done:** acceptance criterion 7 from `docs/02` PRD passes (star rating is an
upsert, not an insert-only log); rating never influences `sentiment_snapshots` in any code path.

## Epic 10 - UX / SEO

- Homepage: search input, trending searches, popular categories, top-sentiment entities,
  recently updated entities (`docs/04`).
- Entity page: all eleven elements listed in `docs/04` (score, opinion count, distribution, Top
  Suara Netijen, rating, period selector, trend chart, related entities, rating CTA, methodology
  link).
- Category and top-list pages, including the mobile stacked-card ranking table.
- `/methodology`, `/sources`, `/about`, `/terms`, `/privacy`.
- Sitemap indexing only active searchable entities; `AggregateRating` schema used only for the
  first-party rating, never for Sentimen Netijen (`docs/13`).
- Entities below the public-score threshold are `noindex` until they clear it.

**Definition of done:** acceptance criterion 9 from `docs/02` PRD passes (usable at 360px
viewport); Lighthouse/SEO smoke check confirms no thin/noindex entity page is in the sitemap.

## Epic 11 - Operations

- Admin views over `crawl_states`, `ingestion_failures`, `unmatched_mentions` — this is what
  makes Epic 5/6 adapters debuggable in production.
- Failed item replay from the admin panel (re-queue a specific `source_item_id`).
- Source kill switch: toggling `sources.enabled` stops discovery/fetch for that source within
  one scheduler tick, with no code deploy.
- Encrypted daily Postgres backup, 7 daily + 4 weekly retention, monthly restore test
  (`docs/16`).
- Alerting on the metrics list in `docs/16` (queue depth/age, job failure rate, crawl success
  rate, parser failure rate).

**Definition of done:** acceptance criterion 10 from `docs/02` PRD passes (every queue/crawler
failure is visible in Horizon or the admin panel); a kill-switch toggle is verified to stop a
running adapter without a deploy.

## Definition of Done (applies to every epic)

1. Feature tests cover the epic's acceptance criteria and pass (`composer test`).
2. `vendor/bin/pint --dirty --format agent` and `phpstan analyse` are clean.
3. Adapter epics (4, 5, 6) additionally have fixture tests per `docs/22` (normal page,
   pagination, empty page, changed markup, quoted post, promo filtering) with no live network
   calls in CI.
4. Any deviation from a `docs/` decision is either not shipped, or the relevant doc is updated in
   the same change — code and docs must not silently diverge.

## Launch gate

| Criterion | Covered by | Measured via (`docs/19`) |
|---|---|---|
| >=150 entities searchable | Epic 1, 2 | index metrics: searchable entity count |
| >=100 entities with public sentiment score | Epic 7, 8 | index metrics: entities >=30 opinions |
| >=4 source adapters healthy | Epic 5, 6 | source metrics: per-source health state |
| At least 2 broad/niche source groups represented | Epic 5, 6 | source registry roles enabled |
| Score recomputation deterministic | Epic 3, 8 | Epic 8 DoD replay check |
| Top Suara Netijen shown for entities past threshold, correct empty-state below it | Epic 12 | Epic 12 DoD checks |
| Mobile and SEO QA pass | Epic 10 | Epic 10 DoD checks |

All seven must hold simultaneously, not cumulatively over time — re-verify all of them right
before launch, since later epics can regress earlier ones.
