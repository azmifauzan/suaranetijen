---
name: seed-entity-csv-import
description: Use when importing seed_entities.csv brands or models.
category: entities
---

Never run the full seed CSV against a live database. `SeedEntityImporter` upserts every row by slug and forces `status=Active`, `searchable=true`, `rankable=true`, so a full run re-activates entities an admin disabled and rewrites categories on hundreds of live rows. Import only the rows you added, and only after a rolled-back dry run.

- Research first: each new row must be a product actually sold in Indonesia, in the category it will land in (Smartphone, Mobil, Motor). Do not rely on a post-import audit to catch foreign or unsold products.
- Write a temp CSV with the header plus just the new rows (`grep ",<Category Name>," database/data/seed_entities.csv`).
- Collision check, read-only on the live DB: slug and normalized-alias hits mean the row updates an existing entity. Also look for near-duplicates under a different slug (`BYD Atto 1` vs `Atto 1`, `GAC Aion Y Plus` vs `Aion Y Plus`). A new row duplicating a zero-opinion entity gets disabled after import; one with opinions is flagged for review, never disabled blind.
- Dry run: inside `DB::beginTransaction()`, run the importer on the temp CSV, clear `Cache::forget('entity-matcher:candidates')`, push target sentences and off-topic traps through `EntityMatcher::matchWithTerm()`, print alias collisions, then `DB::rollBack()`. Traps must include smartphone and everyday sentences that mention the same words.
- Bare model names collide. Words and numbers such as `Air`, `Seal`, `J5`, `G3`, `Dolphin`, `Cloud`, `Leaf` must match only with their brand. Never add a bare model as a standalone alias.
- Brands that are also an everyday word, a first name, or a chemistry term (sharp, cosmos, gea, kirin, alva, volta, smoot) need a `context_required_aliases` entry in `config/entity_matching.php` and a case in `EntityMatcherAliasPolicyTest`. For vehicle brands the context words are vehicle words (motor, skuter, dealer, test ride).
- Deploy the config before the import. Every worker runs the matcher, so `context_required_aliases` must reach the app and all Horizon worker hosts first, or the new rows classify against the old policy.
- Import: `docker cp` the temp CSV into the app container, then `php artisan app:import-seed-entities --file=/tmp/<name>.csv --no-interaction`. Then run `php artisan search:rebuild-documents`, or the new entities are missing from search.
- Moving an existing brand to a new category: add the category mapping in `SeedEntityImporter`, then cover it in `SeedEntityImporterTest`.
- Imported rows inherit the category of their source list, so a whole wave can be wrong at once. After any wave, run a read-only LLM audit of category and "sold in Indonesia". Apply category moves and disables only where the entity has zero opinions; flag the rest.
- Mixed brand rows: if a brand's existing opinions are mostly about one product line in another category, add that line as a sub-brand (its own category, products as children). Plain brand name must still resolve to the parent (`TV Polytron` -> Polytron). Cover both in `EntityMatcherAliasPolicyTest`.
- CSV-only edits (no config change) need no deploy. Still `Cache::forget('entity-matcher:candidates')` on the app after the import, then `search:rebuild-documents`.
- Name casing: entities created without a CSV row take their name from the slug, so model names come out wrong (`Iphone 17`, `Ipad Air 11`, `Infinix Gt 30 Pro`). Set the casing in the CSV `name` column (`iPhone`, `iPad`, `Infinix GT`). A live-only rename survives a re-import only when the CSV has no row for that slug; if it has one, fix the CSV in the same session or the next import overwrites the name.
- Run `tests/Feature/Entities` before commit, then deploy when code or config changed.

Reference: `references/import-scope-evidence.md` (why a full run is unsafe).
