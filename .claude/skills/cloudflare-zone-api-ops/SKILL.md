---
name: cloudflare-zone-api-ops
description: Use when editing Cloudflare DNS, Page Rules, or ACME certs.
category: deployment
---
# Cloudflare zone changes via API

Read the zone first, back it up, change only the named records, and leave dashboard-owned redirect rules to the user. An edge redirect answers before the origin, so a clean origin does not prove the domain works.

- DNS: GET the A record and assert exactly one match. Save `id`, `content`, `proxied` to a backup JSON, then PATCH only `content` and `proxied`. Report old -> new per zone. Read current content from the API, never from notes: the records pointed at several different hosts before this change.
- Before any DNS change, list `pagerules` and `rulesets` for each zone. A `forwarding_url` rule on `DOMAIN/*` swallows requests before the origin sees them.
- Before cert issuance, run `scripts/acme-edge-probe.sh DOMAIN...`. The origin replies 404 or the token body. A 301 whose redirect target is another host means the edge redirect wins, and certbot then fails with `reader size limit exceeded` on the Cloudflare IP.
- Page Rules on a shared zone are dashboard-owned. Do not delete or edit them through the API; the auto-mode classifier denies that as "Modify Shared Resources". Save the rule list (id, target, action), name the rules to remove, and ask the user to act in the dashboard.
- After the user says they edited, re-read every zone. One zone's edit does not clear the others. Do not then issue the API delete that was already denied.
- Re-run the probe on every domain, then continue with cert issuance per `staging-operations`.

Script: `scripts/acme-edge-probe.sh DOMAIN [DOMAIN...]` prints status and redirect target per domain.
