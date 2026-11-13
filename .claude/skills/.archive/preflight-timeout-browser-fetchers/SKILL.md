---
name: preflight-timeout-browser-fetchers
description: Use when FlareSolverr preflight times out: increase to 90s.
category: adapter
level: project
---

Sources using async browser rendering (FlareSolverr, Browsershot) can take 20–60s per request to solve bot challenges. A 60s preflight timeout will abort before the check completes and mark the source as health-unknown/degraded.

Set preflight job timeout to 90+ seconds for browser-rendering sources. Preflight is just a health check (reachability); let it finish before timing out.

Do not conflate preflight timeout with crawl job timeout. Preflight failure marks a source as blocked; give it the headroom it needs.

**Evidence:** Preflight timing out at ~60s during FlareSolverr challenge solving. Changed to 90s on 2026-09-30 01:33; no preflight timeouts in subsequent crawl cycles.