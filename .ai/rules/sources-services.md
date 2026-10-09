---
paths:
  - app/Domains/Sources/Services/SearchQueryPlanner.php
---

# Sources Services

## Focus mode narrows YouTube/Kaskus search to in-play entities of chosen types
config sources.search_priority.focus_types (env SEARCH_PRIORITY_FOCUS_TYPES, comma list, empty = off). When set, SearchQueryPlanner only considers those entity types (falls back to all if none exist) and ranks in-play entities first: an opinion within focus_active_days (90), or added within focus_new_days (60) and never searched. Dormant entities only get a turn when no in-play entity is left, ignoring their due time. The env var must be set on EVERY host that runs Horizon (workers run DiscoverSourceDocumentsJob), not only the main host. Used for "this month crawl products first"; unset it to return to type-agnostic rotation.
