---
name: entity-spec-data-curation
description: Use when filling car, motor, or phone spec data.
category: data-curation
---
# Entity spec data curation

Spec rows are static reference data on a product or person entity, never sentiment. Fill only fields you can source, leave the rest null, and say where each value came from.

## Rules
- Source order follows the user's ask: official manufacturer site or wiki first. Use press or marketplace pages only for fields those cannot give, and name each field that fell back.
- Gate by entity before writing: car, motorcycle, and smartphone specs apply to `type = product` only; `person` entities take `person_profiles`. A brand entity gets no spec row. Check the type and category slug first.
- When sources disagree, keep the official figure or the majority, and state any unit conversion (kW to hp, PS to hp).
- Range (km) only means something with its test standard (NEDC, WLTP, EPA). Record the standard with the figure. If the schema has no place for it, say so in the report and that the public page shows a bare number.
- Enter values through the admin entity form when you can. A tinker `updateOrCreate` skips the form's validation and type guard.
- A value written only to one environment's DB is lost on reseed or a fresh environment. Persist it where a reseed keeps it (seed CSV or seeder) in the same session, or report it as staging-only.
- Fields with no column are not stored, and never go in another column. A field the user explicitly asks for that is core to its category gets a column in the same change: migration, `UpdateEntityRequest` rule, admin form input, and a public label with its unit (EV: `car_specs.battery_kwh`, `car_specs.range_km`). Minor fields stay unsupported and are reported.
- Verify on the public page: clear the cache, then grep one written value in the raw HTML.

Tables, columns, and the worked example: `references/spec-fields.md`.