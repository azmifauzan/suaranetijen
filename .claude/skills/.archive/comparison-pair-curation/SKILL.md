---
name: comparison-pair-curation
description: Use when adding X vs Y pairs to /banding or changing pair-page meta, title, or related-pair links.
category: seo
---

# Comparison pair curation

A `/banding` pair ships only when it has demand and both sides are rateable. Curate pairs by hand; never generate them from the entity list.

- **Eligibility:** both sides clear the public threshold on production, using the 365d snapshot when present, else `all` (same as `eligibleSnapshot()`). Same category required.
- **Type is not a hard gate.** Reject brand-vs-its-own-product (Samsung vs Galaxy A57) and same-family siblings. Do not reject a pair only because `type` differs: IndiHome (service) vs MyRepublic (brand) are both home ISPs and are valid. Type drives JSON-LD, so never retype an entity to make a pair fit.
- **Demand:** a Google Autocomplete suggestion for "X vs Y" must exist. Empty `search_queries` for "vs" is expected, not disqualifying. Prefer the head-to-head form "X vs Y bagus mana" (or "which is better"): pairs with it were added on 19 Oct (oppo-vs-poco, poco-vs-samsung, poco-vs-tecno, poco-vs-vivo); apple-vs-poco and apple-vs-tecno were skipped because Autocomplete suggested no "bagus mana". Probe: `curl -s "https://suggestqueries.google.com/complete/search?client=firefox&hl=id&gl=id&q=X%20vs%20Y"`.
- **Keys:** canonical `a-vs-b` with `a < b`. A non-canonical key never matches a page; the test suite guards this.
- **Titles come from entity names.** After any live entity rename, flush the public page cache and the sitemap cache (`App\Http\Middleware\CachePublicPages::flush()`, `App\Http\Controllers\SitemapController::clearCache()`), then curl each affected pair's `<title>`. Casing (`iPhone`, `Infinix GT`) is only fixed in the name data, never in the title template.
- **Verify before commit:** every new pair returns 200 on production. Non-curated pairs render `noindex`, so for curated pairs also check the robots meta is `index, follow`.
- **Challenged exclusion:** when the user pushes back on an exclusion, re-check the rule's intent against that pair before defending it.
- **Pair-page meta and links:** the meta description answers the head-to-head query with verdict and sample size: `{A} atau {B} bagus mana? {verdict}, dari {n} opini.` Add the theme line only if it fits in 160 characters. This replaces the older rule of hiding the score from meta to keep clicks: at position ~8 with the score hidden, pairs earned 0.5% CTR. Related pairs: up to 6, sharing an entity first, then the category.

Probe queries and the eligibility check: `references/pair-selection-probe.md`.
