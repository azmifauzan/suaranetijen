---
name: ops-monitoring
description: Use when setting up Better Stack or a concentration alert.
category: monitoring
---
# Monitoring: status page setup and concentration alerts

Verify monitors from outside the host, and state what each signal does not cover. A green health route or scheduler heartbeat does not prove every dependency or worker is up.

## Status page
- "The CNAME is configured correctly" does not mean a certificate exists. Verify from outside: DNS, CAA records, the TLS handshake, and the HTTP status.
- The status subdomain's CNAME points at the Better Stack host and must be DNS only (no proxy). Certificate issuance can take over an hour after the CNAME resolves; poll with a bound, and do not state a cause you have not confirmed.
- If the local machine fails TLS while the server gets a valid certificate, the fault is local (network or SNI filter). Report it as local-only and ask the user to check from another network.
- One monitor per public surface (home page, a JSON search endpoint, the health route): "URL becomes unavailable", 5-minute interval, email alert. The free plan allows 10 slots.
- A scheduler heartbeat proves the scheduler and Horizon are alive, not each worker host. A health route that returns 200 proves boot, not Redis or Postgres. Say which coverage is missing.
- A job that exits 0 but writes nothing is invisible to heartbeats and health routes. Alert on output age instead: a backup file older than a day, or a source with zero new items for 48h. A daily DB backup that never succeeded went unnoticed until a manual audit, and a FlareSolverr-routed source produced zero documents for 24h with no alert.
- A container recreate during deploy can open a short 502 incident that closes itself. Expected, not a regression.
- Remove placeholder resources before saving the structure page.

## Source concentration alert
- Fires when one entity holds more than 60% of a source's last-24h observations. Configured in the system metrics command.
- Treat it as one of three causes: a real bug (broad fallback, missing entity guard, bad selector), an adapter scoped beyond its intended category, or legitimate skew. Investigate before concluding.
- Fix at the source: tighten extract logic, add entity-boundary guards, verify scope. Example: an unscoped main-or-body fallback had collapsed one forum source to 96% a single entity; fixing the post selector prevented recurrence.
