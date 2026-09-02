# 14 - API, Jobs & Scheduler

## Public endpoints

- `GET /api/search?q=`
- `GET /api/entities/{slug}`
- `GET /api/categories/{slug}/ranking`
- `PUT /api/entities/{id}/rating`
- `DELETE /api/entities/{id}/rating`

API ini internal web API terlebih dahulu; bukan public developer API.

## Queue names

- `critical` - rating snapshot/search maintenance.
- `discovery` - source discovery.
- `crawl` - fetch/parse.
- `analysis` - entity/relevance/sentiment.
- `aggregate` - daily/snapshot/ranking.
- `maintenance` - retention/cleanup.

## Core jobs

- `PreflightSourceJob`
- `DiscoverSourceDocumentsJob`
- `FetchSourceDocumentJob`
- `ExtractCandidateOpinionsJob`
- `MatchEntitiesJob`
- `ClassifySentimentJob`
- `UpsertSentimentObservationJob`
- `AggregateDailySentimentJob`
- `RefreshSentimentSnapshotJob`
- `RefreshCategoryRankingJob`
- `ExpireRawPayloadJob`

## Scheduling baseline

- Source preflight: daily.
- Bluesky stream: persistent worker/restart supervised.
- Active source discovery: 15m-6h depending source.
- Backfill: continuous low-priority queue.
- Daily aggregate: hourly incremental + nightly reconciliation.
- Ranking snapshot: after aggregate or hourly.
- Retention cleanup: daily.

## Locks

Gunakan Redis locks agar satu cursor/source tidak diproses paralel secara tidak sengaja.
