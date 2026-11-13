---
name: wikidata-entity-enrichment
description: Use when running entities:enrich or Wikidata images.
category: entities
---

Run `entities:enrich` on 1-3 known entities through `WikidataDetailFinder::find()` before any bulk run. The batch writes a per-entity Redis marker (keys matching `enrich:wikidata:*`) as it goes, so a buggy pass marks entities done and later runs skip them.

- Stop a bad batch: `pkill -f entities:enrich` in the app container. Clear the `enrich:wikidata:*` markers it wrote, then re-run.
- After any fix to the guard or label logic, clear the markers from the buggy run before re-running. Otherwise the fixed code skips the entities it already marked.
- Same-name, different-type matches are the main failure. A scooter entry was matched to a kei-car photo (Honda Beat). For Motor, Mobil, and Smartphone, the Wikidata description must name the item type, and the guard must reject items that don't.
- The batch also writes spec and profile tables (`smartphone_specs`, `motorcycle_specs`, `car_specs`, `person_profiles`). A bad pass overwrote 8 existing smartphone spec rows that were not wrong. Snapshot those tables before a batch, or dry-run it on the sample.
- Commons `imageinfo.thumburl` is on `thumb.wikimedia.org` with `?utm_*` query params. The host guard must accept both `upload.` and `thumb.` and strip the query. Probe the real URL with `curl -sI` before trusting a host assumption.
- Zero image on a sample is not automatically a bug (Toyota Avanza returned none). Check the raw Commons response for one known-good entity before changing the parser.
- Brand and service logos: take Wikidata P154 (logo image), not P18 (photo). P18 on a company item is often the HQ building (Apple got Apple Park, Xiaomi got its headquarters). Use P18 only when no P154 exists, and accept that knowingly.
- Dry-run 5 known brands first and print old vs new filename. A wrong pick shows up there before any write.
- Do not judge an image by its filename. `Registered_trademark_img_06.png` is the real Toyota logo. Open the rendered image or the thumb URL.
- Same-name collisions still slip through after a logo fix. Check every changed row by eye: a genus name (Vespa) matched a wasp photo, and a brand alias (DFSK) matched its parent brand's logo (Seres). Clear those rows by hand, after asking.
- Back up old `image_url` and `image_credit` to a host path, not container `/tmp`. Recreating the app container wipes `/tmp`.
- Lookups like `$labels[$this->itemId(...)]` need `?? ''`. A missing label otherwise raises a null-offset deprecation and silently drops the field.
- Send a descriptive User-Agent to Wikimedia APIs.
- Regression test: `tests/Feature/Entities/WikidataEnrichmentTest.php`. Fake Http by host pattern (`*commons.wikimedia.org*`, `*wikidata.org*`). Add a case for each thumbnail host the guard accepts.
