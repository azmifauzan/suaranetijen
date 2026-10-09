---
paths:
  - app/Domains/Ingestion/Jobs/DiscoverSourceDocumentsJob.php
---

# Jobs

## Search-driven sources (youtube, kaskus) pick one entity per cycle via SearchQueryPlanner
Never freeze an entity-term list into crawl_states.metadata: the old `empty($queries)` check built it once (4 Sep 2026) and only 216 of 5358 entities were ever searched, so ranks stopped growing.
SearchQueryPlanner reads entities from the DB every cycle, so a new entity is eligible next cycle; never-searched first (most recently active first); after that due time = last search + clamp(days since newest observed_at, or since entity created, * hours_per_idle_day, min_interval_hours, max_interval_days) (config sources.search_priority, 9 Oct 2026). Fading products slow down to the 90-day cap but never stop; a fresh opinion speeds them back up. Do not go back to an all-time opinion_count penalty: it searched hot new products least. One search call per cycle, YouTube page 1 only (single_page), so API quota is unchanged. State lives in crawl_states.metadata.searched_at (entity id => unix time).
