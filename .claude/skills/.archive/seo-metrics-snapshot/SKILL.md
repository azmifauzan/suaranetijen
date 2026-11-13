---
name: seo-metrics-snapshot
description: Use when pulling SEO numbers from GSC, GA4, RankMySEO, Ubersuggest, or Neil Patel.
category: seo
---

Record every SEO metric as a new dated snapshot with its source, window, and review date. Compare it to the baseline and target columns. Call something a trend only after that comparison.

- Append a new dated section to the plan doc. Never overwrite an earlier snapshot. Use absolute paths when writing the doc.
- Label each number with source and window. GSC lags about 3 days, so its 28-day window ends before today. GA4 may use a different window. Do not put two windows in one column unlabeled.
- When GSC and GA4 windows shift by days between snapshots, state the overlap in the section. The delta then compares windows, not identical periods.
- RankMySEO: take the date from the issues page ("500 pages reviewed · <date>"), not the homepage "Last check". Starter scores only the first 250 pages of the crawl. Note the page count.
- A RankMySEO or Ubersuggest delta counts as progress only if its review or crawl date moved. Same date means no new crawl; say so. Rows marked fixed by hand (filter "Marked fixed (N)") lower issue counts with no re-crawl: report the count as hand-marked, and name rows only after reading them in the list.
- GA4: read the organic search channel alone. Campaign referral spikes (social or Threads links) are not SEO signal.
- Ubersuggest (Neil Patel; app.neilpatel.com redirects to ubersuggest.com): take last crawl date, pages crawled, score, and issue count from Site Audit. Its backlink and domain-authority index is its own: never add it to RankMySEO's backlink count. Scores from different tools are not comparable; compare each tool only with its own earlier snapshot. The first Ubersuggest snapshot is a baseline, not a trend.
- Rising "Excluded by noindex" is expected when below-threshold entity pages are intentionally noindex. Track indexed count and "Discovered / Crawled – not indexed" instead.
- Mark targets reached explicitly. Re-check any audit row against live HTML before listing it as open (see `seo-audit-spa-methodology`, step 8).
- If a baseline label is wrong, correct it in the doc and say so in the section.
- The GSC "Page indexing" report shows its own "Last update" date, which can trail the Performance report by days. Label indexing counts with that date, not the pull date. Read the date before taking any number from it.
- Browser reads of GSC and GA4: right after navigate, `get_page_text` can return the loading shell. Wait about 4 seconds and read again. Confirm the update date or content changed before recording numbers.
- RankMySEO caps tracked keywords by plan (10 on the current plan). A batch add can skip the overflow silently ("2 added, 1 skipped"). After adding, count the rows and name any keyword that was skipped.
- GA4 custom events appear in the key-event list only after they have fired at least once. Order the work: deploy, wait for the first event, then mark it as a key event.
