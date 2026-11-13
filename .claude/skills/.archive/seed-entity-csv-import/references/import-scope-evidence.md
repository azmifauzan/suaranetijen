# Why a full seed import is unsafe on live

`SeedEntityImporter` pass 1 uses `Entity::updateOrCreate(['slug' => ...], [...])` with `'status' => EntityStatus::Active, 'searchable' => true, 'rankable' => true`. Every CSV row therefore overwrites those flags on the live row. The category is also rewritten from the CSV.

The 2026-10 household-appliance import was scoped to 24 rows (header + `Peralatan Rumah Tangga` rows) for this reason, after a pre-check found zero slug and alias collisions.
