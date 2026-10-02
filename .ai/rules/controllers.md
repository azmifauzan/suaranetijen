---
paths:
  - 'app/Domains/**/Controllers/**'
---

# Controllers

## Never name a page prop `seo`
HandleInertiaRequests shares a global `seo` prop (site_name, site_url) that PublicSeo.vue reads to build canonical and og:image URLs. A controller prop also named `seo` replaces it, and canonical/og:image silently become relative paths. Entity metadata lives under `entitySeo` for this reason. Verify SEO changes against real SSR output (php artisan inertia:start-ssr, then curl or a test hitting it), not only Inertia prop assertions.
