---
name: source-concentration-monitoring
description: Source concentration alert: when one entity dominates >60%.
category: monitoring
---

Triggers when a single entity accounts for >60% of a source's last-24h observations.

Signals either a real bug (overly broad fallback, missing entity guard, malformed selector), misconfigured scope (adapter harvesting beyond its intended category), or legitimate data distribution skew. Most healthy sources spread observations across multiple entities; 60%+ concentration warrants review.

**Action:** Tighten the source's `extract()` logic, add entity-boundary guards, or verify the adapter's intended scope. IndoForum's `//main | //body` fallback with no entity check collapsed to 96% GitHub observations — fixing the post selector prevents recurrence.

Configured via `CheckSystemMetricsCommand.php`.
