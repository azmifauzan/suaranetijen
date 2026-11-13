---
name: seo-execution-plan-inertia-spa
description: Use when executing five-phase SEO plan for Inertia SPA.
category: seo
---

Audit with RankMySEO on-page, GSC indexing, GA4 intent, curl raw HTML. Fase 0 (prerequisite): enable SSR, server X-Robots-Tag noindex for <30 opinion entities, cache sitemap/pages, robots.txt. Fases 1-5: on-page template, links, comparisons, backlinks, measurement. Keyword clusters (gap-validated): A = "X bagus atau tidak" (KD 12-31), B = "X review/keluhan" (KD 12-28). Publish only 30+ opinion entities (365d).

**Fase 0**: SSR, server noindex, cache (sitemap <300ms, entities 5-15min), robots.txt Sitemap.

**Fase 1**: on-page per entity (title ≤60, meta desc, H1, summary + sentiment %).

**Fase 2**: internal structure (parent/sibling/theme links, breadcrumb JSON-LD).

**Fase 3**: comparison pages (A vs B sentiment/themes).

**Fase 4**: backlinks, PR, guest posts.

**Fase 5**: tracked keywords, GSC queries, GA4 events. Mark events as key only after first occurrence (GA4 blocks marking until the event has fired once). Re-run RankMySEO and other audit tools after major code changes (SSR deployment, noindex server headers, a11y fixes) since point-in-time audit scores become stale; record audit timestamp vs. deployment date when documenting metrics.