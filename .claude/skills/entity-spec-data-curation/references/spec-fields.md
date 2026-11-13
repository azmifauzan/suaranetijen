# Spec tables and worked example

## Columns
- `car_specs` (unique `entity_id`, one row per product): body_type, fuel_type, power_hp, torque_nm, transmission, drivetrain, seating_capacity, dimensions_mm, release_year. Engine cc is documented for car and motorcycle specs; confirm the exact column name in the migration before writing.
- `smartphone_specs`: chipset, RAM, storage, screen, camera, battery, OS.
- `motorcycle_specs`: same pattern as car specs; read its migration.
- `person_profiles` (for `type = person`): birth date and place, occupation, affiliation, active since, website.
- All fields nullable. Confirm exact names in the migrations; do not guess.

## Worked example: `jaecoo-j5-ev` (product, category mobil)
Written values: body SUV; fuel "Listrik (EV)"; power 208 hp (from 155 kW; sources read 207 and 210 PS); torque 288 Nm; transmission "Otomatis (reduksi 1 percepatan)"; drivetrain FWD; seats 5; dimensions "4380 x 1860 x 1650"; release year 2025.
Sources used: CNN Indonesia and Moladin. Neither is the official site or wiki, so the official-first rule was not met for this entity.
Not stored: battery 60.9 kWh and range 461 km NEDC (no columns).
Written by tinker on staging only; it was not added to the seed data.
