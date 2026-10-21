# 16 - Observability & Deployment

## MVP infrastructure

Single VPS baseline:
- 4 vCPU
- 8 GB RAM
- SSD 100GB+ (sesuaikan raw TTL/log volume)

Processes:
- Nginx
- PHP-FPM
- PostgreSQL
- Redis
- Horizon
- scheduler cron

Boleh memisahkan PostgreSQL kemudian; tidak wajib launch.

## Horizon supervisors

Example:
- `supervisor-critical`: critical, aggregate
- `supervisor-crawl`: discovery, crawl
- `supervisor-analysis`: analysis
- `supervisor-maintenance`: maintenance

## Metrics

- queue depth/age;
- job failure rate;
- crawl success rate per source;
- parser failure rate;
- opinions/day;
- unmatched candidate rate;
- sentiment classifier latency/error;
- aggregate freshness;
- search latency;
- page p95 latency.

## Logs

Structured logs with:
- source_key
- document external ID/hash
- entity_id where applicable
- job ID
- model version

Do not log full raw content by default.

## Backup

- PostgreSQL daily encrypted backup.
- 7 daily + 4 weekly retention minimum.
- restore test monthly.
- FlareSolverr in-flight markers in Redis are safety state; do not discard them while its sidecar can still be running.

## FlareSolverr lifecycle

Set `FLARESOLVERR_INSTANCE_ID` to a stable, unique identity for each sidecar (for example, `dev2-suaranetijen`). All callers of that sidecar must use the same identity, including after caller container replacement. If unset, keys retain the legacy hostname scope.

An unconfirmed request or session command leaves its slot quarantined indefinitely. The marker prevents new work; it does not terminate the existing browser. HTTP timeouts and expired slot locks are not evidence that browser work has stopped.

To recover a quarantined instance:
1. Drain and stop **all callers of that sidecar**.
2. Stop the sidecar and verify its container has exited; no old browser process may survive.
3. In the application's configured cache store, clear only this instance's `flaresolverr:in-flight:<instance>:<slot>` and `flaresolverr:session:<instance>:src-slot<slot>` keys, plus `flaresolverr:cooldown:<instance>`.
4. Start the sidecar, verify readiness, and resume its callers.

Never reopen a slot on elapsed time, a health response, or an empty session list alone. Cache flush, eviction, or Redis loss can erase this safeguard: stop/reset the sidecar with callers drained before resuming after any such event.
