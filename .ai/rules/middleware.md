---
paths:
  - app/Http/Middleware/CachePublicPages.php
---

# Middleware

## Never cache a page whose SSR render failed
TimeoutHttpGateway sets CachePublicPages::SKIP_ATTRIBUTE whenever an attempted SSR render fails, so the empty client-side shell is not served to crawlers for the 5-minute TTL (found 26 Oct 2026: 38 sitemap pages cached with 0 <h1>). Keep that link if either class changes. Tests run with INERTIA_SSR_ENABLED=false (phpunit.xml); SSR tests enable it explicitly and must call $this->app->forgetScopedInstances() between requests, because SsrState is request-scoped and only renders once per app instance. Check a live SSR sweep with `curl ... | grep -c '<h1'` sequentially, not with a parallel burst.
