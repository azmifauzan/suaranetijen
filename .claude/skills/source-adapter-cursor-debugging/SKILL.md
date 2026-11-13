---
name: source-adapter-cursor-debugging
description: Use when a source cursor stalls or re-fetches page 1.
category: sources
---
# Source adapter cursor debugging

Prove a cursor fix against the live persisted cursor before calling it done. Fixture tests and mocked HTTP missed every cursor bug found so far, including ones that skip a forum, category, or query, or leave live discovery with zero new documents.

## Live probe
- Run `.claude/skills/source-adapter-cursor-debugging/scripts/probe-source-cursor.php` through tinker in the container that runs Horizon: `docker exec -i -e PROBE_SOURCE_KEY=serayamotor suaranetijen-horizon-worker php artisan tinker < .claude/skills/source-adapter-cursor-debugging/scripts/probe-source-cursor.php | grep -E "^(> )?V "`. Set `PROBE_SOURCE_KEY` to the source key.
- It loads the `crawl_states` cursor, calls `discover()` once, and prints `docs`, `new` (not yet in `source_documents`), and `next`. It writes nothing, but the live fetch still counts against the source's rate limit and any FlareSolverr slot.
- Space probes a few seconds apart and keep them few. Repeated probes can escalate a bot wall (see flaresolverr-sidecar-recovery).
- Read the result: `docs=0` on a page that should have content points at the parser or the page. `docs` above zero with `new=0` on every cycle means the cursor is re-reading old content.

## Bug shapes seen
- **Pagination end.** phpBB re-renders the last page for `start=` past the end, so an empty page never arrives. Read the "Page X of Y" counter and rotate at Y.
- **Fixed index.** A list read only at index 0 (`forum_ids[0]`, `queries[0]`) never rotates. Rotate on an empty page and wrap back to the first entry.
- **Cold-start cursor.** When the first cycle resolves its list from config, write the full resolved list back into the next cursor's metadata. A singular key written alone leaves later cycles with an empty list and a null next cursor, and no exception is raised.
- **Poisoned legacy row.** A code fix stops new poisoning but not old rows. Once, reset that row's `crawl_states.metadata` to `{}` and clear `cursor_value` and `last_external_id`, so the next cycle reads config like a first run.
- **Dropped query string.** Pass `$query` to `Http::get()` only when it is non-empty (rule in `.ai/rules/adapters.md`).

## Regression test
- Reproduce the exact cursor shape the bug left behind, then assert that a second discovery cycle still finds documents.
