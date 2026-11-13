---
name: staging-origin-521-triage
description: Use when Cloudflare 521 or origin refuses ports 80/443.
category: deployment
---

# Staging origin 521 triage

Cloudflare 521 means the origin refused the connection. Prove the origin is down before touching DNS or Cloudflare, then start the dead proxy container.

1. Probe the origin direct, bypassing Cloudflare: `curl -sI -m 15 --resolve suaranetijen.id:443:$STAGING_SERVER_IP https://suaranetijen.id/up`. Read `STAGING_SERVER_IP` from `.env`, never hardcode it.
2. Port check: `nc -zv $STAGING_SERVER_IP 22 80 443`. SSH open plus 80/443 refused means the proxy is down, not the host.
3. SSH in (`dev@$STAGING_SERVER_IP`) and run `docker ps -a --format "{{.Names}}\t{{.Status}}"`. Look for `nginx-proxy` in `Exited`.
4. Start it: `docker start nginx-proxy`. Then confirm `/` and `/up` return 200 through the public domain.
5. Record the exit code and time before closing: `docker inspect nginx-proxy --format '{{.State.ExitCode}} {{.State.FinishedAt}}'`, plus `journalctl -u docker --since "-30min"`. A `unless-stopped` container that stays exited after a daemon restart is an open question. Say so; do not assume the cause.
6. If `suaranetijen-app` was recreated too, reload nginx-proxy (`docker exec nginx-proxy nginx -t && docker exec nginx-proxy nginx -s reload`) so the stale upstream IP is dropped.

Do not change code or config for a 521 that a container start fixes. Report the cause as unconfirmed if it is.
