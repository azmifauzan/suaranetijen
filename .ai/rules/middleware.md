---
paths:
  - app/Http/Middleware/CachePublicPages.php
---

# Middleware

## Never cache a page whose SSR render failed
TimeoutHttpGateway sets CachePublicPages::SKIP_ATTRIBUTE whenever an attempted SSR render fails, so the empty client-side shell is not served to crawlers for the 5-minute TTL (found 26 Oct 2026: 38 sitemap pages cached with 0 <h1>). Keep that link if either class changes. Tests run with INERTIA_SSR_ENABLED=false (phpunit.xml); SSR tests enable it explicitly and must call $this->app->forgetScopedInstances() between requests, because SsrState is request-scoped and only renders once per app instance. Check a live SSR sweep with `curl ... | grep -c '<h1'` sequentially, not with a parallel burst.

## Cloudflare edge cache depends on the bypass rule
Only a page-cache HIT sends `public, max-age=0, s-maxage=300`; the MISS stays private (it sets the session cookie). The Cloudflare zone has one Cache Rule (set 3 Nov 2026) that makes GET requests eligible for cache only when `http.cookie eq ""`, there is no `x-inertia` header, and the path is not under /admin or /api. Edge and browser TTL follow the origin. The edge ignores Vary, so never send `public` on a response that differs by cookie or X-Inertia. In rule expressions, `len(http.request.headers["x-inertia"])` fails whenever the header is missing; use `any(lower(http.request.headers.names[*])[*] eq "x-inertia")`. CachePublicPages::flush() does not purge Cloudflare: after a deploy, run purge_everything through the zone API.
