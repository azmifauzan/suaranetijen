---
name: seo-performance-snapshot
description: Use when SEO numbers from GSC, GA4, RankMySEO, Ubersuggest.
category: seo
---

# SEO performance snapshot

Every number in a snapshot comes from a source read in that session, with its date and window. A metric that could not be read is written as "tidak terbaca". Never carry a number forward silently.

## Sources

- Search Console performance: state the 28-day window and the lag (about 2 days). Record the actual date range.
- Search Console page indexing is a separate report from performance. Record counts per reason (noindex, 404, redirect, discovered, canonical, errors).
- Read the page indexing report's "Last update" date before comparing with an earlier snapshot. Identical counts across two snapshots mean the report has not refreshed, not a flat trend: record "tidak berubah, report terakhir diperbarui <tanggal>" and do not read it as a trend.
- Before "Validate fix" on a 404 group, open a sample URL live. If the 404 is correct (the entity or page does not exist), leave it unvalidated and record it as expected; validating a correct 404 only fails again.
- GA4: confirm the open property is SuaraNetijen before reading. The default property on the account may be another one. After changing the date range, wait and re-read until the table rows render. If a window still shows no rows after one retry, mark it "tidak terbaca". Do not substitute another window in the same row without a label.
- GA4 referral sources: the Traffic acquisition report opens on "Session primary channel group (Default Channel Group)", where referrals fall into "Referral" with no domain detail. To read referrers per site, switch the table's dimension to "Session source / medium". If the picker is not in the accessibility tree, scroll the table into view and read it again with `get_page_text` before concluding it is missing.
- Quote the table's own percentage cell (for example `36.00 (60%)` for key events). Do not recompute it. Do not label a referral row as Organic Search: a referrer such as `l.threads.com / referral` is not organic, and "Organic Search" can show 0 key events while referral rows carry most of them.
- RankMySEO takes its search clicks and impressions from Search Console. Do not count it as an independent confirmation of GSC numbers. If the review shows "could not finish", record the saved results and the retry time.
- RankMySEO review date: read "Last review" on the issue detail page. The Home card's "Last check" can be a later date; record the review date the score uses.
- RankMySEO issue triage before any fix: map each flagged URL to a deliberate rule first. Entity pages below the public threshold are `noindex` by design (`docs/13`), so "Blocked from indexing" on `/e/{slug}` is expected. Pairs set to `noindex` in a programmatic release are expected too. Hosts other than the main site (for example `status.suaranetijen.id`, a separate app) are outside the SEO sample. Record each as "by design" or "out of sample" with its count, and count only what is left as a finding. Sample size changes the score, so note the page count with every score.
- Ubersuggest free plan: mark locked features (competitors, trend chart, AI visibility) as locked, not as zero.
- On-page scores from Ubersuggest and RankMySEO use different samples and rules. Do not compare them directly.
- Tool "Next Actions" are suggestions. Mark them "belum diputuskan".

## Programmatic pages

- A templated page pair (comparison pairs, combo hubs) with zero GSC impressions over its release window: set `noindex`, drop it from the sitemap, keep the route live. Do not delete it. The step is reversible.
- Measure text similarity across sibling pages with trigram before release. Ship pair-specific facts first. Sibling pairs at about 76% overlap are the reason.
- Record the pair list and the window in docs/31.

## Writing

- Append a dated section to docs/31-seo-plan.md. Do not rewrite older sections.
- Table columns: Metrik | Sebelumnya | tanggal baca. "Sebelumnya" comes only from an earlier written snapshot.
- Do not commit unless the user asks.
- Follow-up: name what to re-read and when (for example, after a review window closes). Do not leave open questions.
