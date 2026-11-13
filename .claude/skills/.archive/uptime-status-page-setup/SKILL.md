---
name: uptime-status-page-setup
description: Use when setting up a Better Stack status page.
category: monitoring
---

# Better Stack status page and custom domain

The dashboard message "The CNAME is configured correctly" does not mean the certificate exists. Verify the domain from outside (DNS, TLS, HTTP) before reporting the status page live.

- CNAME in Cloudflare: `status` -> `statuspage.betteruptime.com`, DNS only (grey cloud).
- Check DNS: `dig +short status.<domain> @1.1.1.1`. Check CAA: `dig +short CAA <domain> @1.1.1.1` (empty means no restriction).
- Check TLS and HTTP: `openssl s_client -connect status.<domain>:443 -servername status.<domain>` and `curl -s -m 10 -o /dev/null -w '%{http_code}' https://status.<domain>/`. Certificate issuance can take over an hour after the CNAME resolves. Wait with a bounded poll (see background-wait-for-condition); do not state a cause you have not confirmed.
- Verify from a second vantage before blaming the cert or Better Stack. If local TCP 443 connects but local TLS fails, run the same `curl` from the staging host over SSH. On 16 Oct 2026 the staging host got HTTP 200 with a valid cert while the local machine failed TLS, which pointed to a local network or SNI filter, not the certificate. Report it as local-only and ask the user to check from another network.
- Navigate the Better Stack admin through its own links (Settings tab on the structure page), not guessed paths.
- Monitors: one per public surface (homepage, a JSON search endpoint, the health route), type "URL becomes unavailable", 5-minute interval, email alert. Free plan allows 10 monitor/heartbeat slots total.
- Scheduler heartbeat (every 5 min from the scheduler container) proves scheduler and Horizon are alive, not that every worker host is up. A dead worker with the others running raises no alert. Say so when reporting coverage.
- A container recreate during deploy can open a short `Status 502` incident (about 25 s) that auto-closes. Expected, not a regression.
- Remove default placeholder resources (for example google.com) before saving the structure page.
- A health route that only returns 200 proves the app boots, not that Redis or Postgres work. A Redis OOM crash-loop left `/up` at 200 while requests returned 500. Add a dependency check before treating the monitor as full coverage.
