# 17 - Implementation Backlog

## Epic 0 - Repository foundation
- Laravel 13 project.
- Inertia/Vue/Tailwind.
- PostgreSQL + Redis.
- Horizon.
- CI lint/test.
- base admin auth.

## Epic 1 - Entity catalog
- categories/entities/aliases migrations.
- admin CRUD.
- seed importer CSV.
- public entity route.

## Epic 2 - Search
- normalization.
- pg_trgm indexes.
- autocomplete API.
- search page.
- search query logging.

## Epic 3 - Sentiment data model
- sources/source_documents/source_items.
- sentiment observations.
- daily/snapshot aggregates.
- ranking query.

## Epic 4 - Adapter framework
- SourceAdapter contract.
- preflight.
- cursor/state.
- rate limiting.
- temporary payload storage.

## Epic 5 - Source adapters wave 1
1. DiskusiWebHosting.
2. SerayaMotor.
3. IndoForum selective.
4. Bluesky.

Reason: HTML/community adapters validate pipeline without depending first on YouTube quota/compliance.

## Epic 6 - Source adapters wave 2
5. KASKUS.
6. YouTube.
7. LowEndTalk.

## Epic 7 - Entity/relevance/sentiment
- alias match.
- context disambiguation.
- opinion relevance classifier.
- sentiment classifier.
- observation idempotency.

## Epic 8 - Public score
- formula v1.
- minimum threshold.
- 30d/90d/365d/all snapshots.
- category ranking.

## Epic 9 - Rating Netijen
- auth.
- 1-5 stars.
- rating snapshot.
- rate limiting.

## Epic 10 - UX/SEO
- homepage.
- entity page.
- category/top pages.
- methodology/sources.
- sitemap/canonical/meta.

## Epic 11 - Operations
- admin crawl diagnostics.
- failed item replay.
- source kill switch.
- backup/alerts.

## Launch gate
- >=150 entities searchable.
- >=100 entities mempunyai public sentiment score.
- >=4 source adapters healthy.
- at least 2 broad/niche source groups represented.
- score recomputation deterministic.
- mobile and SEO QA pass.
