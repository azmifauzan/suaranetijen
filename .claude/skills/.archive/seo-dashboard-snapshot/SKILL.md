---
name: seo-dashboard-snapshot
description: Use when pulling SEO numbers into docs/31-seo-plan.md.
category: seo
---

Record each dashboard read as a new dated section appended to `docs/31-seo-plan.md`. Never rewrite earlier snapshots. Use the layout in `templates/seo-snapshot-section.md`.

- Date the section by the tool's own review date, and mark any figure the tool labels as an estimate or "Outdated".
- Compare against the previous snapshot in one table (metric | previous | now). Take the previous values from the doc, not memory.
- RankMySEO Starter scores only the first 250 of N pages. Count changes such as "Blocked from indexing" can come from the sample, not the site. Write that caveat next to the number; never report it as a fix.
- Affected-URL lists often load late. If still missing, log "belum termuat" as a follow-up. Do not guess URLs.
- Tool suggestions ("Best structure", "Next actions") are inputs, not decisions. Check them against `docs/26` (sponsor stays separate from the organic score), ADR-007/011 (metrics never merged), and `docs/28`. Record them as "saran, belum diputuskan".
- When the user names an extra menu mid-task, cover it in the same section.
- Log the deploy date of every title or snippet change in the section. A CTR verdict on a changed snippet needs about 28 days of GSC data after the change; compare the same query set before and after, and do not call an early dip a failure.
- Leave the doc uncommitted unless asked. Report the path and what changed.

The tool, review date, plan tier, and caveats travel with the numbers so the next snapshot can compare.
