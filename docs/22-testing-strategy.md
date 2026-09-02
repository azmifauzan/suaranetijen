# 22 - Testing Strategy

## Unit

- score formula.
- normalization.
- alias matching.
- rating upsert.
- threshold eligibility.
- URL/cursor parsing.

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

## E2E

- search -> entity.
- rating login -> submit -> update.
- category -> ranking -> entity.
- admin source disable.

## Crawl smoke tests

Live scheduled small probes, not full crawl, to confirm parser/preflight health.
