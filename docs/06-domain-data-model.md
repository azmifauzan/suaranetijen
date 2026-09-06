# 06 - Domain & Data Model

## Core tables

### `entities`
- id
- category_id
- parent_id nullable
- type
- name
- slug
- description nullable
- status
- searchable
- rankable
- created_at / updated_at

### `entity_aliases`
- id
- entity_id
- alias
- normalized_alias
- alias_type

### `categories`
- id
- parent_id nullable
- name
- slug
- status

### `smartphone_specs` / `car_specs` / `motorcycle_specs` / `person_profiles`
One-to-one reference tables (unique `entity_id`, `hasOne` on `Entity`), added 6 September 2026.
Manually curated by admin, static — chipset/RAM/camera for `smartphone_specs`; cc/tenaga/torsi for
`car_specs` and `motorcycle_specs`; birth date/occupation/affiliation for `person_profiles`. Gated
by the entity's category slug (`smartphone`/`mobil`/`motor`) or `type = person`. Never touched by
the sentiment/scoring/matching pipeline — see the ADR-008 scope clarification in `docs/21`.

### `sources`
- id
- key
- name
- adapter
- source_type
- enabled
- priority
- crawl_policy JSONB
- retention_policy JSONB
- last_preflight_at

### `source_documents`
Satu thread/video/post container atau item yang menjadi unit discovery.
- id
- source_id
- external_id
- canonical_url nullable
- title_hash nullable
- published_at nullable
- discovered_at
- last_seen_at
- state
- content_hash nullable

### `source_items`
Unit opini kandidat: comment/forum post/social post.
- id
- source_id
- source_document_id nullable
- external_id
- published_at nullable
- raw_payload_ref nullable
- content_hash
- processing_state
- expires_at nullable

### `sentiment_observations`
- id
- entity_id
- source_id
- source_item_id
- sentiment enum `positive|neutral|negative`
- model_confidence nullable
- observed_at
- created_at

Unique `(entity_id, source_item_id)`.

### `sentiment_daily`
- entity_id
- date
- positive_count
- neutral_count
- negative_count
- opinion_count
- score

### `sentiment_snapshots`
Precomputed public periods.
- entity_id
- period `30d|90d|365d|all`
- positive_count
- neutral_count
- negative_count
- opinion_count
- score
- calculated_at

### `themes`
Canonical theme dictionary (`docs/25`).
- id
- slug
- display_label
- canonical_key
- created_at / updated_at

### `theme_aliases`
- id
- theme_id
- alias
- normalized_alias

### `theme_observations`
One theme+sentiment pair extracted from one relevant opinion. Same dedup posture as
`sentiment_observations` — no duplicate counting per source item.
- id
- entity_id
- theme_id
- source_id
- source_document_hash
- sentiment enum `positive|neutral|negative`
- confidence nullable
- published_at nullable
- created_at

### `entity_theme_daily`
- entity_id
- theme_id
- date
- positive_count
- neutral_count
- negative_count
- observation_count

### `entity_theme_snapshots`
Precomputed Top 5/10 query speedup, optional.
- entity_id
- theme_id
- window
- observation_count
- positive_count
- neutral_count
- negative_count
- rank
- calculated_at

### `user_ratings`
- user_id
- entity_id
- rating smallint 1..5
- created_at / updated_at
Unique `(user_id, entity_id)`.

### `rating_snapshots`
- entity_id
- rating_count
- rating_average
- calculated_at

### operational
- crawl_states
- ingestion_failures
- unmatched_mentions
- search_queries
- source_preflight_logs

## Raw content policy

Raw text/payload tidak menjadi core permanent table. Gunakan temporary encrypted storage/database table dengan TTL sesuai source adapter. Setelah entity+sentiment berhasil dihasilkan, pertahankan hash/external ID untuk deduplication dan hapus payload saat TTL berakhir.
