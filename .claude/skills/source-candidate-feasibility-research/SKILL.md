---
name: source-candidate-feasibility-research
description: Use when vetting a third-party API as an opinion source.
category: sources
---

# Source candidate feasibility research

Gate a candidate source on live evidence from its real API docs, never its landing page. Answer in order, then stop:

1. **Does an endpoint return other people's public content** for a keyword, entity, or account we do not own? Own-account comment, reply, or insight APIs fail this gate.
2. **What gates it?** Auth scheme, plan tier, and platform app-review permission. A listed endpoint may return only the user's own data until review passes. Mark that unverified and prescribe one test call.
3. **Limits and ToS:** per-user or per-day quotas, sensitive-keyword behavior, robots.txt and ToS for crawl candidates.
4. **Data minimization:** any adapter drops usernames and stores only hashes and external IDs (CLAUDE.md standing constraints).

Reading the sources:
- WebFetch on client-rendered marketing pages returns only the `<title>`. Fall back to `curl -sL` on the docs host (`docs.<host>`, `/api/...html`, `llms.txt`), stripped with `sed 's/<[^>]*>/ /g'`.
- Third-party listings (ClawHub, GitBook mirrors, gumroad) are not vendor docs. Label them as such and verify.
- Client-rendered pricing is often unreadable. Report the tier as unconfirmed; never guess a price.

Output: go/no-go plus one test call the user runs. Do not write an adapter in a research pass. If it passes later, build it to the adapter contract (`docs/08`): `preflight()`, feature flag, seeded `enabled: false`.

Log every verdict in `references/candidate-log.md` so the next session does not re-research it.
