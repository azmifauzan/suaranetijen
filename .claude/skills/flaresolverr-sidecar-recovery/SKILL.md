---
name: flaresolverr-sidecar-recovery
description: Use when FlareSolverr quarantines a slot or sources stall.
category: sources
---
# FlareSolverr sidecar: slot quarantine and recovery

A FlareSolverr reply with `status: error` is a reply, not an unknown state. Destroy its session first, reopen the slot only after `sessions.destroy` returns `ok`, then wait out the crash cooldown.

- Keep the slot quarantined on any unconfirmed outcome: a failed destroy, a dropped connection, a malformed reply, or an `ok` without `solution`.
- Never quarantine on every error reply. Routine Cloudflare challenge timeouts then quarantine both slots within 30 minutes of a reset, and every FlareSolverr source on that host goes silent for days.
- A manual reset helps only after the re-quarantining code path is fixed and deployed to every worker host.
- FlareSolverr's `status` is its own reply, not the target's. A 200 can wrap an upstream `504 Gateway Time-out`, which parses as an empty listing. Check the solved page's own status and title before trusting an empty result.
- A "Verification required" title or a captcha is a human check, not a retryable interstitial. Do not rotate sessions or re-probe to pass it. Stop the source, set `sources.enabled = false` and the seeder in the same commit, and report to the operator. A single retry is right only for a "Validating browser…" botguard page.
- Each live probe can escalate the challenge. Keep manual probes few and spaced; see source-adapter-cursor-debugging for the probe script.

## Diagnose
- Grep the app log for `FlareSolverr slot quarantined`. Its context names instance, slot, and exception class.
- Check every FlareSolverr-routed source for zero items in 24h. One quarantine silences all of them.

## Reset (after the fix is deployed)
1. On that host, recreate the sidecar and wait for `Serving on` in its logs.
2. Only then clear the Redis cache keys `flaresolverr:in-flight:<instance>:<slot>`, `flaresolverr:session:<instance>:<slot>`, and `flaresolverr:cooldown:<instance>`. The log says: restart the sidecar before clearing its in-flight marker.
3. Confirm with one request per source, then check the next discovery cycle.

Per-source reliability observed so far: `references/field-results.md`.

Rule source: `.ai/rules/adapters.md` (FlareSolverr error replies).
