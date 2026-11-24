# SEO baseline: suaranetijen.id, 24 Nov 2026

Source: RankMySEO MCP (`seo-existing-site` audit), read 24 Nov 2026. Read only; nothing was changed by the audit itself.

## Headline numbers

| Source | Reading |
| --- | --- |
| Search Console, 28 days (27 Oct to 23 Nov) | 23 clicks, 3,826 impressions, CTR 0.6% |
| Search Console, weekly (from the daily series) | clicks 5, 6, 7, 5; impressions 1,505, 1,053, 644, 624; average position 7.9, 8.3, 10.7, 9.7 (weeks starting 27 Oct, 3 Nov, 10 Nov, 17 Nov) |

Correction (24 Nov): the first version of this file read the tool's `kpis` block (clicks 20 to 5, impressions 2,480 to 1,091) as a click drop. The tool does not state that block's windows, and the daily series shows clicks flat at 5 to 7 a week. What fell is impressions, 59% over three weeks.
| RankMySEO tracked rank | 10 keywords, 9 outside top 20, 1 in 11 to 20, none in top 10 |
| On-page score (crawl 21 Nov, 940 pages) | 79 |
| Backlinks / domain authority | 41 / 4 |

Search Console average position (8.7) is Google's figure, not the tracked rank.

## Findings

1. Clicks come almost only from `/banding` pairs: position 5 to 9, 1 to 3 clicks each. `/banding/realme-vs-vivo`: 252 impressions, position 5.4, 2 clicks (0.8%) against about 2.6% expected.
2. `/e/*` barely shows in Search Console; only `/e/infinix-gt-20-pro` (283 impressions, 1 click). Queries are "masih worth it di 2026" and "X dan Y bagus mana".
3. `page_allowed`: 183 pages blocked from search. Likely intentional noindex (below-threshold entities, noindexed `/banding` pairs). Reconcile against our own noindex rules before acting.
4. Duplicate meta descriptions on 3 pages, 9 unfriendly URLs, 1 title too short. Detail via `issue_pages`.
5. `/category/*` (27 pages): 0 to 3 impressions each, no detected keywords.
6. Impression loss is not explained by the 4 Nov pair pruning (checked 24 Nov). The three pages that lost most, `/banding/infinix-vs-samsung` (-351), `/banding/oppo-vs-tecno` (-150) and `/banding/realme-vs-samsung` (-103), are all still in `config/comparisons.php`, return 200 with `index, follow`, have a self canonical, and are in the sitemap. Commit `d2ee242` only removed pairs that had zero impressions, so it cannot account for lost impressions. Cause unknown; next step is `seo-traffic-drop` (query-level split per page, then Google update dates).
7. Competitor gap: `media konsumen` (5,400), `top brand` (1,900), `top brand index` (1,300), `top brand award` (1,000). The first is also one of our sources, so not automatically worth chasing.

## Impression drop analysis (24 Nov 2026)

Source: RankMySEO `search_console`, daily series and page list, 27 Oct to 23 Nov (23 Nov is incomplete).

- Where: `/banding` -2,383 impressions across 60 pages, `/e` -1,074 across 115 pages; every other section moved by under 30. Losses concentrate on Infinix and Tecno pairs: `infinix-vs-oppo` -692, `infinix-vs-samsung` -351, `infinix-vs-vivo` -326, `infinix-vs-tecno` -301, `/e/tecno` -169. Poco pairs gained (`poco-vs-tecno` +81, `oppo-vs-poco` +70, `poco-vs-samsung` +64).
- When, in two phases: impressions slid from about 230 a day (27 to 30 Oct) to about 100 a day by 10 Nov while average position held near 8. Then position worsened to 11 to 14 between 12 and 19 Nov, and came back to 7 to 8 on 20 to 22 Nov with impressions at 120 to 148.
- Google: the November 2026 core update started 10 Nov and was expected to finish around 24 Nov (Search Status Dashboard, per press reports). The 12 to 19 Nov position dip sits inside it. The first phase (27 Oct to 10 Nov) started before it.
- Own changes in the window: 26 Oct answer-first banding meta and SSR cache fix (`06b46c4`), 3 Nov Cloudflare edge cache (`a209633`), 4 Nov banding content and pair pruning (`d2ee242`). Pruning ruled out above. None is linked to the drop by evidence.
- Rivals: `rank_compare` has no positions for the saved rivals on our tracked keywords, so it cannot show who passed us.
- Not checked: country, device and search-type splits, per-page query split, Page indexing / Manual actions reports (all need Search Console itself), and year-on-year (the pages launched mid-October).

Search Console itself, last 28 days vs previous 28 (read 24 Nov in the browser; Google's own window, so totals differ slightly from RankMySEO):

- Site: clicks 47 to 28, impressions 6.65K to 4.22K, position 7.7 to 8.7. So across windows clicks did fall; the flat 5 to 7 a week holds only inside the last 28 days. The previous window includes the mid-October launch weeks of the `/banding` pairs.
- Device: mobile impressions 5,867 to 3,634 at a flat position (6.8 to 7.0); desktop position 15 to 19.8, clicks 15 to 4.
- `/banding/infinix-vs-*` (page filter): impressions 2.31K to 658, position 5.6 to 7.9, clicks 16 to 2. The same queries lost 4 to 5 places: "infinix dan oppo bagus mana" 5.1 to 9.2, "oppo dan infinix bagus mana" 4.4 to 8.7, "infinix dan xiaomi bagus mana" 5.1 to 9.8, "oppo sama infinix bagusan mana" 5.1 to 9.9. Their impressions fell with the position, so this is lost ranking, not lost demand.
- Pages matching `tecno`: impressions 1.43K to 887, position 6.4 to 6.8 overall, but single queries slid from the top: "oppo dan tecno bagus mana" 1.3 to 6.4, "bagusan oppo atau tecno" 1.6 to 7.3.

Updated cause (24 Nov): the Infinix and Tecno comparison pages lost ranking on their own "bagus mana" queries, dropping from about position 5 to about 9 (and from top 2 to 6 or 7 for some Tecno queries). Demand did not fall. Who took those places is not known yet; that needs a look at the live results for two or three of these queries.

Live results, 24 Nov (one signed-in browser, hl=id gl=id; personalised, one sample per query):

| Query | Top organic results | AI Overview | suaranetijen.id on page 1 |
| --- | --- | --- | --- |
| infinix dan oppo bagus mana | id.quora.com, YouTube, quora.com, TikTok, versus.com (x2), YouTube, TikTok, Facebook group | yes | no |
| infinix dan xiaomi bagus mana | id.quora.com, YouTube, versus.com, id.quora.com, hallo.id, versus.com, Facebook group, erafone.com | yes | no |
| oppo dan tecno bagus mana | Facebook answers, versus.com, TikTok, YouTube Shorts, versus.com, Instagram reel, viva.co.id, TikTok | yes | no |

What took our places: forum and video answers (Quora, Facebook, TikTok, YouTube, Instagram) plus versus.com model-vs-model pages, under an AI Overview. No direct rival in our niche (no other sentiment index) passed us. This fits the November core update window and a results page that now favours first-hand discussion and video for "bagus mana" queries.

Decision (24 Nov): no page change now. The pages stay indexable and the cause is outside the page. Do not add filler text or chase the AI Overview. Read again on 8 Dec: compare 1 to 7 Dec with 3 to 9 Nov in Search Console for the `/banding/infinix-vs-` filter. If position stays at 9 or worse after the update has finished, the next lever is model-level pairs (versus.com wins with model-vs-model, our pairs are brand-vs-brand), decided with `seo-keyword-selection`, not a rewrite of the brand pairs.

Earlier hypothesis, superseded: the first phase looks like Infinix/Tecno query demand or new-page settling after launch, not a site fault (pages stayed indexable, position held). The second phase matches the core update window and is already recovering. Next read: compare 1 to 7 Dec with 3 to 9 Nov, a week after the update finishes.

## Decisions by group (owner approved the three `/banding` rows on 24 Nov)

| Group | Decision | Reason |
| --- | --- | --- |
| `/banding/realme-vs-vivo` | Refresh (tune) | 252 impressions, CTR 0.8% |
| `/banding/samsung-vs-xiaomi` | Refresh (rewrite) | position 39 for "samsung vs xiaomi", volume 260 |
| `/banding/poco-x7-pro-vs-poco-x8-pro` | Refresh (expand) | position 11, 52 impressions |
| Other `/banding/*` | Keep, no change | no evidence yet; noindexed pairs reviewed 2 Dec |
| `/e/*` | Keep | core pages; below-threshold entities are noindex by design |
| `/category/*` | Keep, review later | no data yet |
| `/blog/*` (8) | Keep | no click data yet |

Redirects: none planned.

## Limits of this baseline

- `site_pages` listed 150 of 563 crawled pages, from a crawl dated 4 Nov. The per-URL inventory is incomplete.
- Sitemap and navigation were not read.

## Change log

| Date | Change | Reason | Check on |
| --- | --- | --- | --- |
| 24 Nov 2026 | `EntityComparison::metaDescription()` keeps the praised theme when praise plus complaint would pass 160 characters | `realme-vs-vivo` and `samsung-vs-xiaomi` descriptions were 121 and 128 characters with no theme, because a side was added only when both parts fit. Template-level, so it reaches every `/banding` pair. Held, not deployed: deploy after the 8 Dec read so its effect is not mixed with the core update. | Two weekly checks after deploy |
