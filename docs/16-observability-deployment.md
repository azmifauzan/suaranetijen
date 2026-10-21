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

Staging identities configured on 21 October 2026: `suaranetijen-worker1`,
`suaranetijen-worker2`, and `suaranetijen-worker3`. Pass the value through the Compose service's
environment as well as its `.env`; an explicit Compose value overrides the env file.

Browser preflight uses a 75-second solve limit per request and a 90-second HTTP timeout.
The preflight job has a 150-second overall limit; the discovery/crawl supervisor has 170 seconds,
below Redis `retry_after` (180 seconds). Ordinary discovery/fetch jobs retain their 90-second job
limit and configured browser solve limit (45 seconds by default). Slot locks account for the
chosen solve limit and a possible missing-session retry. The persistent in-flight marker remains
the safeguard if a caller dies or a request does not confirm completion. A Kaskus preflight checks
two URLs, so its overall job deadline can still expire before two slow renders both finish.

An in-flight marker alone does not show quarantine: it is also present during a normal render.
Check whether the slot lock is still held and correlate with recent caller/renderer logs before
diagnosing a stale marker. A successful CLI exit with an unchanged `last_preflight_at` is not a
fresh preflight success: rate-limit releases deliberately preserve the previous health state.

For an unstable sidecar on a light worker, set `HORIZON_LIGHT_CRAWL_MAX_PROCESSES=0` on that
node and pass it through Compose. Keep `minProcesses=1`: Horizon validates it for every
environment, while `maxProcesses=0` prevents that supervisor from being provisioned.
Drain the caller, stop and verify the sidecar has exited, then clear its markers before resuming
the caller. Discovery/crawl then run on the remaining nodes; analysis, aggregate, themes,
maintenance, and the separate YouTube queue remain available on the isolated node.
To restore crawl, first recover and verify the sidecar, then set the limit back to `1` and
recreate the caller. Staging worker3 was isolated this way after a SerayaMotor request timed out
and quarantined its renderer following the successful Kaskus preflight; worker2 retains crawl.

An unconfirmed request or session command leaves its slot quarantined indefinitely. The marker prevents new work; it does not terminate the existing browser. HTTP timeouts and expired slot locks are not evidence that browser work has stopped.

To recover a quarantined instance:
1. Drain and stop **all callers of that sidecar**.
2. Stop the sidecar and verify its container has exited; no old browser process may survive.
3. In the application's configured cache store, clear only this instance's `flaresolverr:in-flight:<instance>:<slot>` and `flaresolverr:session:<instance>:src-slot<slot>` keys, plus `flaresolverr:cooldown:<instance>`.
4. Start the sidecar, verify readiness, and resume its callers.

Never reopen a slot on elapsed time, a health response, or an empty session list alone. Cache flush, eviction, or Redis loss can erase this safeguard: stop/reset the sidecar with callers drained before resuming after any such event.
