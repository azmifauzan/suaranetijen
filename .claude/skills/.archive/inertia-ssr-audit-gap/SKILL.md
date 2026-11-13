---
name: inertia-ssr-audit-gap
description: Diagnose Inertia SSR fallback when duplicate titles appear.
category: inertia
---

When Inertia's SSR adapter fails to reach the SSR server, it silently falls back to client-side rendering. Googlebot sees correct content (renders JS), but non-Google crawlers (RankMySEO, social preview, Bing) see only raw HTML with empty body and generic Blade title.

**Diagnose by comparing what crawlers see vs. what the backend has:**

Raw HTML: `curl -s https://example.com/page | grep '<title>'` should show actual page title. If generic or identical across pages, SSR fell back.

Rendered: Extract Inertia props from `<script data-page>`. If props have real data but `<title>` is generic, SSR silently failed.

**Root cause check:**
- Verify `package.json` has `build:ssr` and Dockerfile runs it
- Verify `php artisan inertia:start-ssr` runs before app server  
- Check SSR server is reachable

**Note:** Robots meta in Vue props only works after JS execution. Non-Google crawlers don't run JS, so they ignore it. Set robots in Blade or HTTP headers instead.