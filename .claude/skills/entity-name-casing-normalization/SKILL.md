---
name: entity-name-casing-normalization
description: Use when bulk-fixing entity names (casing like 5g to 5G), backfilling entity parent_id (product to brand) on production, or creating brand rows.
category: data-curation
---
# Entity name casing normalization

Let the LLM propose letter-case fixes only. Keep a proposal only when it changes nothing but case, review the merged file, then apply that file deterministically. The model never adds, drops, or reorders characters.

## Rules
- Root cause first. Find where the bad casing is written before bulk-fixing it. In this project candidate names come from `CarisinyalCandidateSource::slugToName`, which `ucwords` a slug, so `5g` and `zte` stay wrong and the enricher echoes them. Fix the derivation and the enricher prompt (which asks for the manufacturer's capitalization), then bulk-fix the existing rows.
- Pick candidates by pattern before any model call: digit-then-letter model tokens (`14t`, `5g`), suffixes such as `Se`, `Fe`, `Nfc`, and acronym brands (ZTE, TCL, LG, HMD, BMW, BYD, MG, iQOO). Limit to `status = active`.
- Call the model through `LlmClient` (configured model only) in chunks of about 60 names with a strict JSON schema of `{input, output}` pairs. Instruct it to keep brand words as written unless the brand is an acronym, change only letter case, and return the name unchanged when unsure.
- Keep a proposal only when `output !== input` and `mb_strtolower(output) === mb_strtolower(input)`. Drop everything else and count the drops.
- Curated overrides beat LLM output. Log every override that flips an LLM answer, then review the merged JSON, sampling random rows, before any write.
- Apply by exact `name` match. Rename only when exactly one row matches the old name and the change is case-only. Print renamed and skipped counts, with the skipped names.
- A staging-only rename is lost on reseed. In the same change, fix the source of truth (enricher prompt or seed CSV), or report the rename as staging-only.
- A DB write on a shared environment needs the go-ahead that staging-operations requires. If the permission classifier blocks it, hand the user the script and run order instead of retrying another route.
- Flush the public page cache after renaming; names render on public pages.
- Product-to-brand backfill (`parent_id`): dry-run first and print the plan. Match only orphans (`type = product`, `parent_id IS NULL`) against a longest-prefix rule list (`redmi ` to Xiaomi, `iphone ` to Apple), sorted by rule length descending so the most specific prefix wins. Stay read-only until the user approves the plan.
- Apply in one `DB::transaction`: create missing brand rows, update with `whereNull('parent_id')` so re-runs are idempotent, and throw when the updated count differs from the planned count so the whole write rolls back. Keep the printed `PLAN=` JSON in the log as the rollback record.
- New brand rows: take `category_id` from a sibling brand in the same family instead of hardcoding it. Set `type = brand`, `status = active`, `searchable` and `rankable` true, and one Primary alias. Check that the slug and normalized alias are unused first.
- A new brand with no opinions stays `noindex` below the public threshold, so its page will not rank yet. Confirm whether past opinions re-match before promising coverage.
- Run the tinker script over ssh as one flattened line (`tr '\n' ' '`), as this session did for both dry-run and apply.
- After a write: flush the page cache and sitemap cache, purge Cloudflare, then curl the parent, child, and new brand pages for 200 and the expected section.
- Verify the write, not the intent. Count new names present and old names left (`old_left=0`), then curl two or three renamed entity pages and check that the raw HTML `<h1>` carries the new name.
- Before saying a rename survives reseed, check the row's origin. Seed CSV rows need the CSV edit. Candidate-pipeline rows are not re-created from the seed CSV; a fresh candidate scan regenerates them through the fixed enricher prompt. Say which applies.
- Drop any proposal whose official casing you cannot confirm (an uncertain model name, for example). List it as skipped instead of guessing.
