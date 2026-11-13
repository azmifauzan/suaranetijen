---
name: horizon-worker-oom-triage
description: Use when Horizon workers overload or log Job timed out.
category: async-patterns
---

Check container memory and restart state (`OOMKilled`, `RestartCount`) before CPU or load. A worker container at its `mem_limit` looks like "overloaded" but is OOM-killed and restarted, and each restart drops in-flight jobs as "Job timed out".

Triage order per host:

1. `docker inspect <horizon-container> --format 'restarts={{.RestartCount}} oom={{.State.OOMKilled}} started={{.State.StartedAt}}'`
2. `docker logs --since 40m <container> | grep -iE "timed out|killed|memory"` (look for `Worker STOPPED Job timed out` bursts)
3. `docker stats --no-stream <container>` against the `mem_limit`, and `ps -eo rss,args` inside the container for per-process RSS.
4. Only if memory is clean, look at CPU (`docker stats` cpus cap) and queue depth.

Sizing rule: each `horizon:work` PHP process sits around 70–100 MB RSS. Total processes = sum of `maxProcesses` across the environment's supervisors on that host. Keep `mem_limit` above that sum times ~100 MB plus headroom. Shrinking `maxProcesses` on the light host is cheaper than raising the limit on a shared box.

Fix, in order: lower `maxProcesses` on the affected supervisors in `config/horizon.php`, commit and push the change in the same session (see `horizon-graceful-restart` for drain and hot-patch durability), then re-run step 1 after a few minutes. Raise `mem_limit` only on hosts with spare RAM, and update the compose file too, not just `docker update`, or the next `--force-recreate` reverts it.

Recheck after every change: `RestartCount` stays flat, `OOMKilled=false`, and `horizon:supervisors` still lists every queue on that host.

A `Job timed out` with `RestartCount` flat and `OOMKilled=false` is not a memory kill. It is one slow job under database load hitting the supervisor `timeout` (60s here). Fix that job, not the whole queue: if it is idempotent, give it its own `$tries` and `backoff()` so the retry is safe, and leave the global `timeout` alone.