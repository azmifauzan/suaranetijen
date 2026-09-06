# 21 - Architecture Decision Records

## ADR-001 - Entity-centric, not product-centric
**Decision:** `entities` supports brand/product/service.  
**Why:** same sentiment engine works across categories including cloud brands and generic services.

## ADR-002 - Laravel modular monolith
**Decision:** one Laravel codebase with asynchronous workers.  
**Why:** lowest operational complexity; enough for MVP.

## ADR-003 - Redis from day one
**Decision:** Redis + Horizon included from MVP.  
**Why:** crawler/analysis are queue-heavy and Redis cost/resource overhead is small.

## ADR-004 - PostgreSQL search first
**Decision:** pg_trgm + FTS, no dedicated search engine.  
**Why:** ~200 seed entities and moderate growth do not justify extra service.

## ADR-005 - Derived sentiment is core asset
**Decision:** raw third-party content temporary; observations/aggregates persistent.  
**Why:** matches product value and reduces storage/privacy coupling.

## ADR-006 - Simple score
**Decision:** positive=100, neutral=50, negative=0 aggregate.  
**Why:** transparent and aligned to sentiment-only product.

## ADR-007 - Manual rating stays separate
**Decision:** Rating Netijen never merged into Sentimen Netijen.  
**Why:** provenance must remain obvious.

## ADR-008 - No aspect scoring/specs
**Decision:** no camera/support/performance subscore in MVP.  
**Why:** unnecessary complexity and drifts from product thesis.

**Scope clarified 6 September 2026:** this ADR bans *aspect scoring* — a numeric subscore derived
from sentiment/opinion data (e.g. a "camera score" computed from netizen opinions). It does not
cover static reference specs manually entered by an admin (chipset/RAM/camera for Smartphone;
cc/tenaga/torsi for Mobil/Motor; birth date/occupation/affiliation for Tokoh Publik). These live in
dedicated per-category tables (`smartphone_specs`, `car_specs`, `motorcycle_specs`,
`person_profiles`), are never derived from or fed back into the sentiment/scoring/matching
pipeline, and are displayed on the entity page in a separate card from Sentimen/Rating Netijen.
This is a scope clarification, not an override — no subscore or aspect taxonomy was added.

## ADR-009 - Source adapters isolated
**Decision:** each external source has adapter + preflight + feature flag.  
**Why:** external access changes must not destabilize system.

## ADR-010 - Politics deferred
**Decision:** no political entity module in MVP/near roadmap.  
**Why:** not needed to validate current product and creates separate compliance surface.

**Overridden 5 September 2026:** politisi are now in scope as part of the `person` entity type
and the Tokoh Publik category, at explicit operator request. The generic entity-centric pipeline
(matching, sentiment, scoring, ranking) applies unchanged - no separate political-entity module was
built, and no compliance review was performed as part of this override. Same standing constraints
(precision-over-recall matching, no aspect scoring, data minimization - rate the public figure, not
private individuals) apply to `person` entities as to any other type.

## ADR-011 - Theme Index is a second derived layer, not aspect scoring
**Decision:** Top Suara Netijen (`docs/25`) adds a theme-frequency index alongside Sentimen
Netijen, sourced from the same relevant-opinion stream. It shows theme + observation count only —
never a numeric per-theme score, and never a manual per-category aspect taxonomy.
**Why:** answers "what do netizens say most" without reopening ADR-008's exclusion of aspect
scoring/subscores; keeps the engine entity-type-agnostic (no per-category taxonomy to maintain),
consistent with ADR-001's one-engine-across-types decision.
