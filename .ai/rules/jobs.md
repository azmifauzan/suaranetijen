---
paths:
  - app/Domains/Ingestion/Jobs/DiscoverSourceDocumentsJob.php
---

# Jobs

## Search-driven sources (youtube, kaskus) pick one entity per cycle via SearchQueryPlanner
Never freeze an entity-term list into crawl_states.metadata: the old `empty($queries)` check built it once (4 Sep 2026) and only 216 of 5358 entities were ever searched, so ranks stopped growing.
SearchQueryPlanner reads entities from the DB every cycle, so a new entity is eligible next cycle; due time = last search + opinion_count * penalty (config sources.search_priority), never-searched first. One search call per cycle, YouTube page 1 only (single_page), so API quota is unchanged. State lives in crawl_states.metadata.searched_at (entity id => unix time).
