---
paths:
  - config/horizon.php
---

# Config

## Redeploy Horizon workers gracefully: wait for the container to exit, don't sleep a fixed time
When rolling a new image to a Horizon host: pull, run `php artisan horizon:terminate` in the worker container, then poll `docker inspect` (State.StartedAt changes or State.Running is false, cap ~180s) BEFORE `docker compose up -d --force-recreate`. A fixed `sleep 20` force-kills in-flight jobs: every such deploy left ~7 MatchEntitiesJob as MaxAttemptsExceeded (items stuck pending) on 29 Sep 2026. Roll workers one host at a time; the main host (app + scheduler) first, then `nginx -s reload` on the proxy. Hostnames/paths stay in the operator's local deploy config, not here.
