---
name: cloudflare-edge-cache
description: Use when Cloudflare serves stale or uncached public pages.
category: deployment
---
# Cloudflare edge cache for public pages

Edge caching of public HTML needs three pieces to agree: origin `Cache-Control`, the zone Cache Rule, and an edge purge after each deploy that changes public HTML. Change one and re-verify all three on the live domain.

- Origin: only a page-cache HIT may send `public, s-maxage`. A MISS stays `private` because it sets the session cookie, and Cloudflare then bypasses. Never send `public` on a response that varies by cookie or `X-Inertia`; the edge ignores `Vary`.
- Zone Cache Rule (phase `http_request_cache_settings`, one entry in the entrypoint ruleset): it matches only cookie-free GET requests without `x-inertia`, outside `/admin` and `/api`. Edge and browser TTL follow `respect_origin`. Expression and pitfalls: `references/cache-rule-expression.md`.
- Purge: after every deploy that changes public HTML, and after publishing or editing a public blog post (`/blog`, `/blog/{slug}`, sitemap), call `purge_everything` on the zone purge API. Read `CLOUDFLARE_TOKEN` from the repo `.env`, look up the zone id by name, and never echo the token. `CachePublicPages::flush()` bumps only the origin epoch key and does not reach the edge.
- Verify: `scripts/verify-edge-cache.sh https://DOMAIN / /e/SLUG /top/CATEGORY` (one request at a time). Pass when a plain path shows `HIT` with a rising `age` on the second request, and the cookie and X-Inertia variants show `DYNAMIC` or `BYPASS`.
- Open risk: any cookie bypasses the edge, including analytics cookies, so real-visitor hit rate can be far below the probe result. Measure it before claiming the cache works.
