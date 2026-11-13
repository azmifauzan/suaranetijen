---
name: seo-verification-ssr-deployment
category: seo
description: Use when verifying Inertia SSR deployment crawlability.
---

After deploying Inertia SSR, curl raw HTML for each page type (entity, category, static) and verify without rendering JavaScript:

1. **Title**: Unique per page, correct, present in `<title>` tag before JS runs.
2. **Canonical**: Absolute URL (e.g., `https://domain.com/path`), not relative.
3. **H1**: Rendered in initial HTML, not deferred to JavaScript.
4. **Robots directives**: `<meta name="robots">` and `X-Robots-Tag` header match intent (index/follow for public, noindex/follow for thin pages and search results).
5. **robots.txt**: Lists `Sitemap:` URL correctly.
6. **Sitemap**: Returns 200, contains expected entity URLs, cache warms on first hit (~0.6s) and serves from cache on second (~0.2s).
7. **TTFB**: Entity pages return server-rendered content within 0.4–1.0s (adjust based on rendering complexity).

Example:
```bash
curl -s https://domain.com/e/entity-slug | grep -oE '<title[^>]*>[^<]*</title>'
curl -sI https://domain.com/e/entity-slug | grep -i x-robots-tag
```
