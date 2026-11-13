---
name: topic-landing-page-publishing
description: Use when publishing topics with complaint-subject filter.
category: search
---

Topics like "Penanganan keluhan baik" (good complaint handling) are fundamentally complaint-focused and mislead searchers, even with positive aggregate sentiment. Filter complaint-subject candidates during selection and detach such themes before publishing.

**Gate:**
1. **Select**: Filter candidates against `config('landing_pages.complaint_terms')` — words like keluhan, komplain, lambat, masalah, refund, penipuan indicate complaint-subject topics and should be rejected.
2. **Validate**: Detach themes with negation markers (`hasNegationMarker()`) or complaint-subject terms. Apply copy guard (title ≤60, meta ≤155, intro ≤1000). Require ≥3 qualifying entities per theme.
3. **Verify**: Live — `/topik/{slug}` is 200, in sitemap, displays on homepage.

Implementation: `CandidateScannerService::hasComplaintSubject()` checks theme labels during publishing and candidate scanning.

**Choosing which drafts to publish:**
- Skip a draft whose slug contains a superlative (e.g. `terbaik`) or whose title/meta frames a complaint. Slug is locked after publish, so fix it before publishing or leave the draft.
- Rank the remaining drafts by theme signal count and publish only those that clearly read as neutral discussion topics. Report skipped drafts with the reason.
- Publish through `AdminTopicsController::publish` (same validator and copy guard as the admin button). Tinker calls to the controller method are acceptable on staging when the user has approved publishing; do not hand-write status updates.
- Verify after publish: `/topik/{slug}` returns 200 with `index, follow`, the slug is in `/sitemap.xml`, and the homepage "Topik Hangat" block links it. Allow ~20s for caches to clear.