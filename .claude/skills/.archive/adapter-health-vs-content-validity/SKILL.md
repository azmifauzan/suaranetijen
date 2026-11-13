---
name: adapter-health-vs-content-validity
description: Use when: adapters stuck on one page despite HTTP 200.
category: adapter
level: project
---

A source's preflight can report `health_state = healthy` (HTTP 200) while being functionally non-working: pagination ignored, CSR content not loaded by FlareSolverr, bot-detection wall not actually solved.

If available, preflight should include a secondary content-validity check beyond HTTP reachability: Does the page contain expected items? Are item IDs different across pages? For RSS, is the feed parseable?

For pagination in particular, if a source's first and last pages return identical content, pagination is broken but preflight (HTTP reachability) passes. Document sources with known content issues (Kaskus pagination ignored) so debugging starts from the correct assumption.

**Evidence:** Kaskus all subforums return same 5 thread IDs on pages 1, 300, 412, 414, 900. Probably a trending widget; real thread list loads client-side. Cursor pagination stuck. Recorded in .ai/rules/adapters.md.