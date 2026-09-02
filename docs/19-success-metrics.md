# 19 - Success Metrics

Two different questions, kept separate: **launch gate** (`docs/17`) asks "can we ship";
this document's **MVP validation targets** ask "is it working after we ship." A metric belongs
here if it needs live usage or live crawl data to mean anything — thresholds that can be checked
before any traffic exists belong in the launch gate instead.

## Product metrics

| Metric | Definition | Data source | Cadence |
|---|---|---|---|
| Search success rate | query resulting in an entity-page click, same session | `search_queries` + a click event | daily |
| Entity page engagement | scroll depth or interaction (rating CTA, related-entity click, period selector) | entity page event log | daily |
| Repeat visitors 7/30 day | distinct visitor identifier returning within window | visitor session log | weekly |
| Rating conversion | entity page views -> rating submitted | entity page views vs `user_ratings` inserts | weekly |
| Category/top-list CTR | list impression -> entity click | category/top page event log | daily |
| Organic search impressions/clicks | Search Console data for indexed entity/category/top pages | Search Console | weekly |

**Instrumentation gap:** `docs/06` only specifies `search_queries` as a first-party log table.
Search success rate, entity engagement, repeat visitors, rating conversion, and list CTR all need
a lightweight page-view/click event log that does not exist in the current schema. Add this as an
explicit task under Epic 10 or 11 (`docs/17`) before relying on these numbers — do not assume the
event log exists just because it is listed here.

## Index metrics

| Metric | Definition | Data source |
|---|---|---|
| Active entities with >=30 opinions | count of entities where `sentiment_snapshots.opinion_count >= 30` for the default period | `sentiment_snapshots` |
| Ranking-eligible entities >=100 opinions | same, threshold 100 | `sentiment_snapshots` |
| Opinions ingested/day | count of `sentiment_observations` by `observed_at` date | `sentiment_observations` |
| Source diversity per entity | distinct `source_id` per `entity_id` in period | `sentiment_observations` |
| Median sentiment freshness | median across entities of `now() - max(observed_at)` | `sentiment_observations` |
| Duplicate suppression rate | rejected-as-duplicate / total candidate opinions | ingestion pipeline counters |
| Unmatched mention rate | `unmatched_mentions` count / total candidate opinions | `unmatched_mentions` vs candidate count |
| Classifier error rate | disagreement rate on the curated QA sample (`docs/22`) | manual QA review |

Thresholds for "healthy" on each row are set once real data exists in the validation window
below — this document does not fix them in advance because pre-launch estimates would be
guesses.

## Source metrics

Per source (`docs/07` roles: `broad`, `niche_high_density`, `supporting`, `first_party`):

| Metric | Definition |
|---|---|
| Discovery yield | documents discovered per discovery run |
| Fetch success | fetched / attempted |
| Candidate opinion yield | candidate opinions / fetched document |
| Relevant opinion yield | relevant opinions / candidate opinions (Epic 7 relevance filter) |
| Parser failures | count of `parser_broken` health transitions |
| Block/rate-limit events | count of `blocked` or `quota_limited` health transitions |
| Cost per 1k processed opinions | only for paid/quota-metered sources (YouTube) |

Source health states and their meaning are defined in `docs/08`; this table measures how often
each state occurs, not what the states are.

## Dashboards and alerting

Operational visibility (queue depth/age, job failure rate, crawl success rate per source, parser
failure rate, opinions/day, unmatched candidate rate, classifier latency/error, aggregate
freshness, search latency, page p95) is Horizon + the admin panel per `docs/16`, built in
Epic 11 (`docs/17`). Product metrics in this document are a separate, user-facing concern and are
not expected to live in Horizon.

## MVP validation targets

Indicative, adjust after real data — these describe a healthy first validation window, not a
launch requirement:

- 100+ entities scored.
- 4+ healthy third-party adapters.
- 10k+ sentiment observations.
- search p95 <300ms server-side target.
- entity page LCP target <2.5s on reasonable mobile connection.
- measurable repeat usage or organic query growth within first validation window.

## How this maps back to the backlog

| Validation target | Requires |
|---|---|
| 100+ entities scored | Epic 7, 8 producing observations past threshold for most seed entities |
| 4+ healthy third-party adapters | Epic 5 and at least one Epic 6 adapter reaching `healthy` |
| 10k+ sentiment observations | sustained backfill + incremental crawl, Epic 4-6 |
| search p95 <300ms | Epic 2 index/query tuning under real seed-entity volume |
| entity page LCP <2.5s | Epic 10 page implementation, image/CLS discipline from `docs/04` |
| repeat usage / organic growth | Epic 10 SEO correctness + enough elapsed time post-launch |
