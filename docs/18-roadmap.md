# 18 - Roadmap

## MVP phases

Detailed tasks and Definition of Done for every epic below live in `docs/17`. This section is
the execution-order view: what has to be true before the next phase can start.

| Phase | Epics | Unlocks |
|---|---|---|
| 0. Foundation | 0, 1 | Entities exist and are addressable; admin auth works. |
| 1. Findability | 2 | Seed entities are searchable, even with no sentiment yet. |
| 2. Sentiment substrate | 3, 4 | Schema and adapter framework are ready; still zero live data. |
| 3. First observations | 5, 7 (partial) | Wave-1 adapters produce real, matched, classified opinions. |
| 4. Public score | 8, 12 | Sentimen Netijen and Top Suara Netijen are computable and visible above threshold. |
| 5. Coverage expansion | 6 | YouTube, KASKUS, LowEndTalk added once the pipeline is proven. |
| 6. First-party rating | 9 | Rating Netijen is live and independently tracked. |
| 7. Public launch readiness | 10, 11 | UX/SEO complete; admin diagnostics and backups in place. |

Phase 3 intentionally runs before phase 5: community/forum/firehose adapters have lower
compliance and quota risk than YouTube or KASKUS, so they validate discovery -> matching ->
sentiment -> aggregate end-to-end first (`docs/07`, `docs/09`, `docs/24`).

Phase 4 (public score) can only go live once phase 3 has pushed at least some entities past the
30-opinion public threshold — do not enable the entity-page score UI before that is true for a
meaningful slice of seed entities, or the launch will show mostly empty scores.

Every phase ends at the corresponding epic's Definition of Done in `docs/17`; the MVP is
shippable once every launch gate criterion in `docs/17` holds at the same time.

## Post-MVP 1 - Coverage & freshness

**Trigger:** launch gate met and `docs/19` freshness/coverage metrics show a specific gap (e.g.
median sentiment freshness lagging a category, or source diversity concentrated in one adapter).

- X API if economics justify.
- more niche Indonesian forums.
- improved entity discovery from search demand (built on the `search_queries` log from Epic 2).
- faster historical trend updates.
- source coverage page per entity.

## Post-MVP 2 - Theme Index depth

**Trigger:** Epic 12 (`docs/17`, `docs/25`) is live and stable at MVP scope; expand once theme
data volume is enough to make these worthwhile, not before.

- natural-language theme-based search ("VPS yang banyak dibilang murah") — `docs/13`.
- Top 10 toggle beyond the MVP Top 5 default.
- theme trend chart (theme frequency over time).
- theme comparison across entities in the same category.
- personalized theme recommendation.
- advanced taxonomy editor / category-specific theme ontology, if the fully data-driven approach
  in `docs/25` ever proves insufficient.

## Post-MVP 3 - First-party flywheel

**Trigger:** rating conversion or repeat-visit metrics from `docs/19` show first-party
engagement is the binding constraint, not third-party coverage.

- optional short user reviews.
- helpful/report controls.
- entity suggestion by users.
- user follow/watch list.

## Post-MVP 4 - Data partnerships

**Trigger:** evaluated opportunistically, not on a fixed schedule — pursue when a partner's terms
and cost are compatible with `docs/15` data-minimization and provenance rules.

Targets where useful:
- MediaKonsumen.
- Female Daily.
- Try & Review.
- Trustpilot Data Solutions.
- app review data.

## Post-MVP 5 - New categories

**Trigger:** current categories (`docs/03`) show healthy coverage and score stability; expanding
category count before that dilutes crawl/adapter effort across too many low-density entities.

- Beauty/FMCG.
- Banks/e-wallets.
- Airlines/travel.
- SaaS/software.
- Institutions/public services where appropriate.

## Scale architecture

Only when measured (`docs/05` extraction trigger), not speculatively:
- dedicated search engine;
- Python/GPU sentiment service;
- crawler workers on separate nodes;
- analytical database for very large history.

## Long horizon

Political/candidate sentiment is intentionally not part of MVP or near roadmap. If revisited near
an election cycle, treat it as a separately reviewed product/compliance module rather than
silently enabling existing generic entity functionality.
