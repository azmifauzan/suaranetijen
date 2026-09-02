# 22 - Testing Strategy

## Unit

- score formula.
- normalization.
- alias matching.
- rating upsert.
- threshold eligibility.
- URL/cursor parsing.
- theme normalization/clustering (canonical mapping for known alias sets, `docs/25`).
- theme frequency ranking and dedup (a repeated templated opinion must not inflate the count).

## Adapter fixture tests

Simpan sanitized HTML/JSON fixtures untuk setiap source adapter. Parser tests tidak melakukan live network.

Fixtures:
- normal page;
- pagination;
- empty page;
- changed markup fallback;
- quoted forum post;
- promo/signature filtering.

## Integration

- Redis queue dispatch/retry.
- observation uniqueness.
- aggregate refresh.
- rating snapshot.
- PostgreSQL search.

## NLP evaluation

Curated Indonesian test set across:
- formal;
- slang;
- typo;
- mixed English;
- emoji;
- negation;
- sarcasm examples.

Measure per-class precision/recall and entity relevance precision. Prioritize precision over recall for entity matching.

Theme extraction/clustering evaluated against the same curated set: does a known alias set
("murah", "terjangkau", "harga oke", ...) collapse to the correct canonical theme, and does an
ambiguous phrase route to the LLM fallback rather than silently misclassify.

## E2E

- search -> entity.
- rating login -> submit -> update.
- category -> ranking -> entity.
- admin source disable.
- entity above threshold shows Top Suara Netijen; entity below threshold shows the empty-state
  copy, never an empty or padded theme list.

## Crawl smoke tests

Live scheduled small probes, not full crawl, to confirm parser/preflight health.
