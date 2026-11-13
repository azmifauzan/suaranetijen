---
name: generated-content-site-discovery
description: Use when making comparison pages discoverable from nav.
category: seo
---

When you have dynamically generated content pages (comparison pages, rankings, dynamic results), make them reachable from your site structure, not just search crawlers.

Pattern: service method groups indexable items by category → controller renders listing page → Vue/template with JSON-LD BreadcrumbList/ItemList → footer navigation link → conditional sitemap entry (only when items exist) → page cache integration → noindex when empty.

The listing page (`/banding` in this codebase) surfaces generated pages that would otherwise be invisible to users browsing the site, instead of only discoverable via search results. Include both breadcrumb and itemlist JSON-LD for crawler clarity. Conditionally include in sitemap only if there are indexable items to list. Use page cache (Laravel's ResponseCache or equivalent) since the list changes infrequently. Set noindex when the listing is empty to avoid thin-page penalties.
