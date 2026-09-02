# 25 - Top Suara Netijen (Theme Index)

## Purpose

**Top Suara Netijen** surfaces the themes/words netizens most often use when talking about an
entity. It is a derived layer on top of the same opinion data already collected for Sentimen
Netijen — it does not add a new crawl target.

It is **not aspect scoring**. It never outputs `Performance 91/100`, `Support 73/100`, or any
numeric per-theme score. It answers one question:

> **Apa yang paling sering dikatakan netijen tentang entitas ini?**

Example for `VPS Biznet Gio`: Cepat, Handal, Murah, Network bagus, Support lambat.

## Position among the three core metrics

| Metric | Question it answers | Example |
|---|---|---|
| Sentimen Netijen | Bagaimana kecenderungan opini netijen? | `82/100`, 71% positive |
| **Top Suara Netijen** | **Apa yang paling sering dikatakan netijen?** | Cepat, Handal, Murah, ... |
| Rating Netijen | Bagaimana rating langsung pengguna SuaraNetijen? | `4.3/5`, 318 rating |

All three stay separate metrics with separate provenance — same rule as ADR-007 for
Sentimen/Rating, extended here to a third component.

## Pipeline

Sentiment pipeline (`docs/09`) is unchanged. Theme extraction is a second derived branch reading
the same relevant-opinion output as the sentiment classifier — one opinion produces two
observation types:

```text
Public Opinion -> Entity Matching -> Relevant Opinion?
                                          |
                    +---------------------+---------------------+
                    v                                           v
        Sentiment Classification                    Keyphrase / Theme Extraction
                    v                                           v
        Sentiment Observation                       Theme Normalization -> Clustering
                    v                                           v
        (docs/09 aggregate/snapshot)              Frequency Aggregation -> Theme Index
```

```text
Opinion
  |-- Sentiment Observation
  `-- Theme Observation
```

## Theme extraction

Finds the core subject of an opinion. One opinion can yield multiple theme+sentiment pairs:

> "Servernya ngebut, tapi support ticket saya kemarin lama dibalas."

```text
themes: cepat, support lambat
sentiments: cepat -> positive, support lambat -> negative
```

The pipeline may link each theme observation to a sentiment, but the product **never displays a
numeric score per theme** in MVP — only frequency.

## Normalization and clustering

Netizens phrase the same idea many ways: `murah`, `terjangkau`, `harga oke`, `worth it`, `ramah
kantong`, `value-nya bagus` must collapse into one canonical theme, not five separate rows.

```yaml
theme_id: price_affordable
display_label: Murah
aliases: [murah, terjangkau, harga oke, ramah kantong, worth it]
```

Clustering may combine keyword normalization, an Indonesian synonym dictionary, embeddings,
semantic similarity, and an LLM fallback for ambiguous cases — same "optional model fallback only
for ambiguous candidates" posture as entity matching (`docs/10`). The canonical dictionary grows
over time; it is not a fixed per-category taxonomy (see below).

## No category taxonomy required

Themes are discovered from data, not assigned from a manual per-category aspect list (no
`Smartphone: battery/camera/display/performance`, no `Cloud: uptime/network/support/billing`).
This keeps the theme engine entity-type-agnostic, same posture as the sentiment engine
(`docs/03` ADR-001) — one mechanism works across smartphones, cars, ISPs, and couriers.

## Data model

Additions to `docs/06`:

### `themes`
- id, slug, display_label, canonical_key, created_at, updated_at

### `theme_aliases`
- id, theme_id, alias, normalized_alias

### `theme_observations`
- id, entity_id, theme_id, source_id, source_document_hash, sentiment, confidence, published_at,
  created_at

### `entity_theme_daily`
- entity_id, theme_id, date, positive_count, neutral_count, negative_count, observation_count

### `entity_theme_snapshots` (optional, for query speed)
- entity_id, theme_id, window, observation_count, positive_count, neutral_count, negative_count,
  rank, calculated_at

Same raw-content policy as `docs/06`: theme observations persist, raw text does not outlive its
adapter TTL.

## Deduplication

Theme frequency must never count raw posts directly — a 5,000x-repeated promo template is one
observation, not 5,000. Minimum: exact text hash, normalized text hash, near-duplicate
similarity, repeated template detection, repeated source-document detection — reuses the same
dedup posture as `docs/09`. This protects index quality, it is not a judgment on opinion
truthfulness.

## Ranking (Top 5 / Top 10)

Frequency only, no 0-100 theme score:

```sql
SELECT theme_id, SUM(observation_count) AS total
FROM entity_theme_daily
WHERE entity_id = :entity_id AND date >= :start_date
GROUP BY theme_id
ORDER BY total DESC
LIMIT 5; -- 10 for the "see more" toggle
```

Window default for MVP: **12 months**, falling back to **lifetime** while data is sparse — same
new-entity fallback pattern as `docs/11`'s `365d -> all`.

Positive/negative theme groups ("Netijen Paling Suka" / "Paling Sering Dikeluhkan") are the same
theme+sentiment observations, just split by sentiment sign — not a separate pipeline, and not a
fact-check of which complaints are valid.

## Minimum data threshold

Do not render Top Suara Netijen on thin data:

```text
Entity sentiment score: minimum 30 qualified opinions   (docs/11, existing)
Top Suara Netijen:      minimum 30 qualified opinions
                         minimum 3 occurrences per displayed theme
```

Below threshold: **"Belum cukup opini untuk merangkum Suara Netijen."** Thresholds are
configuration, calibrated after real data exists — same posture as `docs/11`'s thresholds.

## Copy rules

Never state an unqualified percentage claim ("73% netijen mengatakan ... murah") unless the
denominator and methodology are actually defined and shown. Prefer:

> "Murah disebut dalam 284 opini yang dianalisis."

or:

> "'Murah' termasuk salah satu tema yang paling sering muncul."

## UI (entity page)

Inserted between the sentiment distribution and Rating Netijen block in `docs/04`'s entity page
layout:

```text
Top 5 Suara Netijen
1. Cepat             428 opini
2. Handal            316 opini
3. Murah             284 opini
4. Network bagus      192 opini
5. Support lambat    148 opini

Netijen Paling Suka
Cepat · Handal · Murah

Paling Sering Dikeluhkan
Support lambat · Downtime
```

Mobile: chips/tags or a compact ranked list, same density rules as `docs/04`.

## Future: theme-based search (post-MVP)

Once the Theme Index exists, search can answer "VPS yang banyak dibilang murah" or "HP yang
banyak dibilang kameranya bagus" by ranking entities on `theme = X` observation count/proportion,
without any product specification data. Consistent with `docs/13`'s MVP search staying
PostgreSQL-only — this is a post-MVP addition layered on the same search subsystem, not a new
engine. See `docs/18` Post-MVP list.

## MVP scope

**In MVP:**
- theme extraction, canonical normalization, theme observation storage;
- frequency aggregation; Top 5 themes on the entity page;
- positive/negative theme groups; minimum observation threshold.

**Deferred (post-MVP, `docs/18`):**
- Top 10 toggle ("Lihat lebih banyak");
- natural-language theme-based search;
- theme trend chart; theme comparison across entities;
- personalized theme recommendation; advanced taxonomy editor; category-specific ontology.

Top 5 is the MVP default — faster to read, cleaner on mobile than Top 10.

## Product principles

1. Never verifies whether an opinion is true or false.
2. Never turns an opinion into a claimed fact.
3. Never gives a theme a numeric/objective score.
4. Never requires product specification data.
5. Never requires a manual per-category aspect taxonomy.
6. Themes come only from analyzed netizen conversation.
7. Semantically identical themes are normalized together.
8. Obvious duplicate/spam content is not counted repeatedly.
9. Observation count is shown wherever a theme is shown.
10. Sentimen Netijen, Top Suara Netijen, and Rating Netijen stay three separate metrics/features.

This is the same "opinion, not truth" and "simple public metrics" posture as `docs/01`'s product
principles, applied to a third derived index rather than a new one.
