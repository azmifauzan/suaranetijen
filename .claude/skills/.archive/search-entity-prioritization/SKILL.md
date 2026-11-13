---
name: search-entity-prioritization
description: Use when entity search lists are frozen in memory, or when focusing YouTube/Kaskus crawling on product entities.
category: sources
---

Never freeze the entity search list at startup. Use `SearchQueryPlanner` to read entities each cycle — new entities become searchable next cycle, and opinions-based penalties defer well-covered entities.

To focus crawling on one entity type (e.g. products), set `SEARCH_PRIORITY_FOCUS_TYPES` (comma list). The planner then orders focus-type entities first: opinions in the last 90 days, then never-searched; other types only get searched when no focus entity needs a search. Config is cached at container boot, so after changing it recreate app, horizon and scheduler on every host (main and workers) and confirm the value with `config:show` on each.

For configuration and the gotcha (why this matters), see `references/search-priority.md`.