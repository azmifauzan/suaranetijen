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

## ADR-009 - Source adapters isolated
**Decision:** each external source has adapter + preflight + feature flag.  
**Why:** external access changes must not destabilize system.

## ADR-010 - Politics deferred
**Decision:** no political entity module in MVP/near roadmap.  
**Why:** not needed to validate current product and creates separate compliance surface.
