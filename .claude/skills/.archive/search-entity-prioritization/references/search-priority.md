# Search Entity Prioritization

## State & Ranking

`SearchQueryPlanner` reads entities from the database on every discovery cycle and ranks them by due time = `last_searched + clamp(idle_days × hours_per_idle_day, min_interval_hours, max_interval_days)`, where idle_days = days since the entity's newest `observed_at` (or since it was created). Never-searched entities go first.

Entities never searched appear first (sorted by opinion count, fewest first). State lives in `crawl_states.metadata.searched_at`: entity_id → unix timestamp. Never frozen at startup.

## Config

Set in `config/sources.php` via `config('sources.search_priority')`:
- `hours_per_idle_day` (default 4): wait hours per day of silence
- `min_interval_hours` (default 24) / `max_interval_days` (default 90): clamp

Example: entity with 30 opinions delayed `min(30 × 2 hours, 60 days) = 60 hours` from last search.

## Single-Page Discovery

For paginated sources (YouTube, Kaskus), set `single_page: true` in cursor metadata so one search call per cycle doesn't chain through all pages:

```php
$nextCursor->metadata['single_page'] = true;
```

This keeps API quota constant while crawling thousands of entities.

## The Gotcha

A frozen entity list (built once at startup) stops growing. When the list was frozen into `crawl_states.metadata['queries']`, only 216 seeded entities out of 5358 total were ever searched. This pattern reads the DB every cycle, so new entities are picked up next cycle with zero manual steps.