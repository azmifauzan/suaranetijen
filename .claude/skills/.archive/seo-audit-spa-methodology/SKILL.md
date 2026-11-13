---
name: seo-audit-spa-methodology
description: Use when diagnosing low SEO scores or audit findings (RankMySEO, blocked from indexing, thin pages, long titles, email obfuscation) on SPA/Inertia apps.
category: seo
---

Diagnose low on-page SEO scores for Inertia or SPA apps via five-point triangulation:

**1. Raw HTML audit** (`curl` without JavaScript)
- Crawlers see only this before rendering. Check title, meta description, H1, body text.
- If empty or generic, rendering layer is broken.
- Command: `curl -s https://example.com | grep -E '<title>|name="description"'`

**2. Rendered output** (props/data from JavaScript)
- Extract Inertia props from `<script data-page>` or inspect DOM after render.
- Compare against raw HTML to confirm rendering gap.
- If props have real data but raw HTML is generic, rendering is the issue.

**3. Competitor structure**
- `curl` rivals (not browser) to see what crawlers see.
- Check title length, description pattern, heading hierarchy, word count.
- Typical: 2,000–5,000 words per page, FAQ/Breadcrumb schema.

**4. Search Console signals** (28 days minimum)
- Impressions (appearing?), clicks (clicked?), position (rank).
- Pages with content but zero clicks = title/description issue.
- Pages with zero impressions = indexation or relevance problem.

**5. Intent validation** (Google Autocomplete)
- How do users actually phrase the search? Set `hl` and `gl` for target region.
- Example: `https://suggestqueries.google.com/complete/search?hl=id&gl=id&q=samsung`
- Compare against your ranking keywords—if off-intent, update copy.

**6. Excluded-by-noindex spike** (GSC Pages report, "Excluded by 'noindex' tag")
- A rising noindex count usually means Google is discovering thin pages through links, not that noindex is wrong.
- Check the noindex decision first (`curl -s URL | grep robots`), then find which indexable pages link to the thin URLs: `curl -s https://example.com/indexable | grep -o 'href="/e/[^"]*"' | wc -l` on listing/browse/related blocks.
- Fix the link source: indexable pages list only pages that pass the same eligibility rule as their robots meta. Do not remove noindex from thin pages, and do not block them in robots.txt (Google then cannot read the noindex tag).
- Match the detail page's exact data selection, not just the aggregate. A listing that falls back to a wider period (all-time) can pick entities whose detail page reads a narrower period (365d) that is still thin. Filter the fallback rows against the detail page's own snapshot before linking.
- Verify a link fix with a sweep, not one spot check: collect every `/e/*` href from each non-entity sitemap URL, subtract the indexable entity URLs, and curl the remainder for `content="noindex`. Category and top-list pages can still leak a few targets after the first fix (e.g. `/top/*` with no ranking, root categories with no direct entities).
- Expect GSC counts to fall slowly, over weeks, after recrawl. Track "Discovered – currently not indexed" and "Indexed" instead of the noindex count.
- Before calling a fix "live", confirm it is pushed and deployed: `git log --oneline origin/main..main` (unpushed commits) and `git branch --contains <sha> -a`, then curl the live URL. A local fix commit explains nothing about what Google saw.

**7. Third-party SEO scores lag** (RankMySEO-style audit tools)
- Read the audit's own review date and page count before trusting its score. A score dated before the last SSR/meta deploy describes the old site.
- Such tools often have no re-run button on lower tiers. Say so and fall back to GSC + GA4 + live curl as ground truth. Do not tell the user the site got worse because the score did not move.
- Compare the audit page count with `sitemap.xml` URL count. A gap means the audit crawled a stale set.
- Sort both sides before `comm` (`sort -u` each file), or it prints "not in sorted order" and the diff is wrong.

**8. Fixing findings from an audit tool on a live Inertia site** (RankMySEO-style)
- Starter-tier audits score only the first 250 pages of the crawl. Check which URLs the audit actually saw before fixing sitewide.
- "Blocked from indexing" can fire on a link to a noindex page, not only on the noindex page itself. Render below-threshold entities as plain text in listing blocks (topic, top, category), and keep the `Link` only when the entity is indexable.
- Title over 60 characters: drop the " | Brand" suffix. Apply the rule in both the global title helper and the per-page SEO component so SSR and client output match.
- Cloudflare email obfuscation breaks visible `mailto:` text. Wrap the address in `<!--email_off-->...<!--/email_off-->`. Vue strips template comments, so emit that markup through `v-html`.
- Thin page (under about 300 words): add copy drawn only from the product's own docs, methodology, or code (what is measured, data source, thresholds, what is deliberately not done). Add no new numbers. Do not name a real brand as an example in methodology or about copy; describe the rule generically.
- Count words on the live page after stripping script, style, nav, header, footer: `curl -s URL | python3 -I -c "..."`. Counting the source template gives the wrong number.
- Short clean paths (`/about`, `/terms`) flagged as "unfriendly URL" are usually a false positive. Do not rename them to pass.
- Scope to sitemap-listed URLs first: an audit's thin-page list can include noindex-by-design URLs (e.g. `/top/*` absent from `sitemap.xml`, `/top/atlet`). Only sitemap-indexed thin pages get new copy; check `sitemap.xml` and the robots meta before writing anything.
- Before fixing an audit row, curl the example URLs it lists. Audit rows lag the deploy: a "Title too long (168)" row described titles already 45-60 characters live. Re-check, then record the row as stale, not open.
- A "Blocked from indexing" row can be intentional. Below-threshold `/e/*` pages and `/top/*` pages with no ranking are `noindex, follow` by design. Confirm the robots meta on one URL, record the row as accepted in the plan doc, and do not remove noindex just to clear the count.
- A GSC 404 on a URL with spaces or a purged entity slug (e.g. `/e/gac aion s`): curl both the raw and slug forms. If both 404 and the entity was purged, the 404 is correct. Do not redirect and do not hunt for a link source in code when `/e/` links are always built from `slug`; Google drops the URL on its own.

**9. Verify SSR output locally before claiming a page renders server-side**
- Start both processes: `php artisan inertia:start-ssr` and `php artisan serve --port=8123`. Wait for the SSR `started` line before the first request.
- Clear the page cache after SSR is up (`php artisan cache:clear`). `CachePublicPages` stores whatever the first request produced, so a request made while SSR was down freezes the client-side fallback for every later request.
- Confirm the body, not the status code: `curl -s URL | grep -c '<h1'` must be 1. `0` with a 200 means CSR fallback. Cross-check `storage/logs/laravel.log` for `[InertiaSsr] Render failed, falling back to client-side rendering`.
- Before curling DB-backed pages (entity, top, topic), run `php artisan migrate:status | grep Pending`. Pending migrations surface as `SQLSTATE[42P01] relation ... does not exist` (500), which is not an SSR fault. If Postgres is down (`connection refused`), ask the owner to start it instead of retrying.
- New Inertia page missing from the Vite manifest (`Unable to locate file in Vite manifest`): run `npm run build` before feature tests that hit it.
- Stop the servers afterwards (`ps -eo pid,args | grep -E 'port=8123|start-ssr'`); a leftover SSR process holds port 13714 for the next run.
- Sweeping production `<h1>` across the sitemap: run the curls one at a time. A parallel burst makes SSR time out, and `CachePublicPages` then stores the client-side shell for 5 minutes. Read `X-Page-Cache: HIT` with `0` `<h1>` as a failed render, not thin content. Re-fetch with a session cookie and confirm `<h1>` is present before reporting the URL. The fix that stops the cache from storing failed renders lives in `TimeoutHttpGateway` (see `.ai/rules/middleware.md`).

**Trust pages (`/about`, `/contact`, `/methodology`):** name the publisher as the owner chose it (e.g. "Tim SuaraNetijen"), not a personal name the owner did not pick. State the reply deadline the owner confirmed. Keep `/llms.txt` thresholds read from `config/scoring.php`, not typed as literals. A new trust page (`/contact`) needs to be in the sitemap and linked from the footer before requesting indexing, or Google finds it with no path in.

**10. Ask Google to recrawl after a live SEO fix** (GSC URL Inspection, Request indexing)
- Only after the fix is deployed and curl-verified (step 6 last rule). Inspect each changed or new URL in GSC; do not bulk-request.
- Read the inspection verdict first: "URL is unknown to Google" (new page, e.g. `/contact`) and "indexed but content changed" (e.g. `/about`) call for different follow-up, but both justify one Request indexing click.
- Skip URLs that are not search pages: `/llms.txt` is read by AI crawlers directly; entity pages under threshold stay noindex. Sitemap already covers the rest.
- Request indexing has a small daily quota. Spend it on the highest-value trust or money pages, leave the rest to the sitemap, and say the quota limit in the report.
- Confirm only "request accepted / queued". Crawl results show days later; do not claim a result in the same turn.

**11. GSC "Discovered / Crawled – currently not indexed" triage**
- Check each URL first: 200, `index, follow`, listed in sitemap, and "Last crawled N/A" means Google has never fetched it. Do this before blaming links or changing code.
- Compare inbound internal-link counts for the Discovered set against the sitemap-wide median. Similar counts (about 5 vs 6 on this site) mean the URLs are not orphans. The usual cause is low crawl priority on a low-authority domain. Record it as accepted; add no code change.
- A Crawled-not-indexed URL with `0` `<h1>` and `X-Page-Cache: HIT` is the failed-SSR cache case in step 9. Fix that first.

**Triangulation:** Raw HTML empty + props full = rendering issue (1 vs 2). Raw HTML content + zero clicks = title/description issue (2 vs 4). Noindex count spike + thin links on indexable pages = link-source issue (6 vs 1). Score flat while GSC impressions/position improve = stale third-party audit, not a regression (7 vs 4).