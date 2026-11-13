# Cloudflare Cache Rule: working expression and pitfalls

Working expression (set 3 Nov 2026 on the suaranetijen.id zone, phase `http_request_cache_settings`):

```
(http.host eq "suaranetijen.id" and http.request.method eq "GET" and http.cookie eq "" and not any(lower(http.request.headers.names[*])[*] eq "x-inertia") and not starts_with(http.request.uri.path, "/admin") and not starts_with(http.request.uri.path, "/api"))
```

Action: `set_cache_settings` with `cache: true`, `edge_ttl: {mode: respect_origin}`, `browser_ttl: {mode: respect_origin}`.

Pitfalls:
- `len(http.request.headers["x-inertia"])` is false-negative when the header is absent: the PATCH returns `success: true`, but plain requests stayed `DYNAMIC`. Use the `names[*]` form above.
- Any request cookie (including analytics cookies set by gtag) bypasses the rule. Check a real browser cookie jar before trusting the hit rate.
- The rule only helps when the origin sends `public, s-maxage`. A `private` origin response keeps `BYPASS` no matter what the rule says.
- The Account request-tracer endpoint (`/accounts/{id}/request-tracer/trace`) returned `Authentication error` (code 10000) with the same token that could PATCH rulesets. Verify with curl, not the tracer.
- Load `CLOUDFLARE_TOKEN` from `.env` at run time (`set -a && . ./.env; set +a`). Never paste it into a command log.

Open: `CachePublicPages` purges only the origin epoch key. Cloudflare needs its own purge after deploys (see the main skill).
