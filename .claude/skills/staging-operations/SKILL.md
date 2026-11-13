---
name: staging-operations
description: Use when redeploying staging, triaging a Cloudflare 521, or adding a domain vhost or Let's Encrypt cert to nginx-proxy.
category: deployment
---
# Staging operations: redeploy, origin outage, SSR wiring

Redeploy from the committed tree and verify the live result on the box. A 502, a Dead container, or a cached SSR shell can each return a clean-looking status.

## Redeploy (references/redeploy-runbook.md)
- Build and push through the container deploy safety gates first.
- Build from the committed tree; uncommitted edits are not deployed and must be named as such.
- Get an explicit go-ahead right before the first recreate. Build and push are not production actions, but recreating live containers is: a general "lanjut" given for the build does not cover it. Post the exact command sequence (SSR first, then app and scheduler, proxy reload, cache clear) in one message and wait for a clear "deploy" from the user. If the permission classifier denies the recreate as "Production Deploy", stop and present the sequence; do not retry through another route.
- Hold while a background enrich or import batch runs. Check for it by listing every running artisan process (`docker exec suaranetijen-app sh -c "ps -eo args | grep '[a]rtisan'"`), not only the command names you expect. An empty result from a name-filtered grep proves nothing. Drain and recreate workers one host at a time. When the change touches only web services, leaving worker hosts on the previous image is allowed; name each such host in the deploy note as still on the old image.
- Drain a worker before recreating it: `docker exec WORKER php artisan horizon:terminate`, poll `docker inspect -f '{{.State.StartedAt}}' WORKER` until it changes (in-flight jobs finished, the restart policy brought the container back), then `docker compose stop -t 120 WORKER` before `up -d --force-recreate`. A bare recreate kills in-flight jobs and requeues them. If the worker's FlareSolverr is recreated too, follow the flaresolverr-sidecar-recovery skill afterward.
- After each worker recreate, prove the new code is running, not just that the container is up: compare `docker inspect -f '{{.Image}}' WORKER` with `docker image inspect -f '{{.Id}}' IMAGE:latest`, run `class_exists` on a class this release added (`docker exec WORKER php -r 'require "vendor/autoload.php"; echo class_exists("App\\...") ? "yes" : "no";'`), and confirm `horizon:status` reports running. Recreate a single worker with `docker compose up -d --force-recreate --no-deps WORKER` so its dependencies are not recreated too.
- Compose service names are the full container names: `suaranetijen-app`, `suaranetijen-ssr`, `suaranetijen-scheduler`, `suaranetijen-redis`. The short name `app` fails with `no such service: app`. Run `docker compose config --services` on the host before the first pull or recreate and use the names it prints.
- After recreate, check the resource limits with `docker inspect -f '{{.Name}} cpus={{.HostConfig.NanoCpus}} mem={{.HostConfig.Memory}}' suaranetijen-app suaranetijen-ssr suaranetijen-scheduler`. A `docker update` hot-patch is lost on recreate, so the value must also be in the compose file before the recreate.
- Confirm the compose image is the staging image tag and that each service runs the new image, not an old local one. After `docker compose pull`, compare `docker image inspect -f '{{index .RepoDigests 0}}' IMAGE:latest` with the `digest: sha256:` line from the push log; a mismatch means the pull is stale.
- Migrate, reload the proxy, and flush the page cache once SSR is healthy. Flush with `docker exec suaranetijen-app php artisan tinker --execute 'App\Http\Middleware\CachePublicPages::flush();'`; the call bumps an epoch key, so every cached public page misses at once. Judge the flush by its exit code, not its output: run it without a pipe, check `$?`, then re-fetch one public page and grep its `<h1>`. A pipe such as `| tail -1` hides the exit code, and an empty tail line proves nothing.
- Purge the Cloudflare edge cache after every deploy that changes public HTML (`purge_everything` on the zone purge API). `CachePublicPages::flush()` does not reach the edge. See the `cloudflare-edge-cache` skill for the cache rule and verification.
- Flush the same way after any direct DB edit to public copy (entity or theme names, labels, topics), not only after a deploy. Score changes from the pipeline do not need it; they wait out the TTL.
- Credentials come from `.env` at run time. Never write them into a skill, log, or commit.
- Never feed a `.env` password into `sudo -S` or any other privilege prompt. When a remote step needs root and the account has no passwordless sudo, stop and give the user the exact command to run with the `!` prefix, then verify its output here. For remote commands, use one helper script in the session scratch dir that reads host, user and password from `.env` into `SSHPASS` and calls `sshpass -e ssh -o PubkeyAuthentication=no ...`. The password then stays out of argv and logs.
- Verify: the home page and the health route return 200 on the public domain, then grep the changed markup on one page with curl. A cached shell still returns 200. Sweep the changed URLs one request at a time: a parallel burst made SSR fail on some pages and the empty shells were stored. Pass a page only when its raw HTML contains its `<h1>`; re-fetch an empty page before calling it a regression, and report the retry.

## Shared-host changes beyond the app
- A go-ahead for listed steps covers only those steps. Shared infrastructure (another app's proxy, the shared Postgres, a runner config, an SSH key added to `authorized_keys`) gets its own explicit go-ahead.
- When the permission classifier denies a step, do not retry it through another route. Keep the approved steps that still run, write the remaining ones as a script in the scratch dir with `set -euo pipefail` and a backup step, list the `!` run order for the user, and verify the output once they run it.
- Verify each shared change on the live host: container health, `/up` 200, restart count, and the failed-jobs window.

## Origin outage (Cloudflare 521)
Prove the origin is down before touching DNS or Cloudflare.
1. Probe the origin directly, bypassing Cloudflare: `curl -sI -m 15 --resolve DOMAIN:443:$STAGING_SERVER_IP https://DOMAIN/up`. Read the IP from `.env`.
2. `nc -zv $STAGING_SERVER_IP 22 80 443`. SSH open with 80 and 443 refused means the proxy container is down, not the host.
3. Over SSH, `docker ps -a` and look for the proxy in Exited. Record its exit code, FinishedAt time, and the docker journal for the last 30 minutes.
4. `docker start` the proxy container; confirm the home page and health route return 200 through the public domain.
5. If the app was recreated too, reload the proxy so its stale upstream IP is dropped.
Do not change code or config for a 521 that a container start fixes. Report the cause as unconfirmed.

## Certificate renewal check
- Before reporting a new domain's cert done, read its renewal config (`renewal/DOMAIN.conf`, not the live symlinks). Compare every SAN on the cert with the `webroot_map` keys and the default `webroot_path`. A map that lists only `www.DOMAIN` while the cert covers the apex is a renewal risk to test, not a fact to assume.
- Confirm with `certbot certificates -d DOMAIN` and `certbot renew --dry-run --cert-name DOMAIN` before the renewal window, and report the dry-run result.
- A wildcard SAN (`*.DOMAIN`) cannot renew over webroot. Its renew dry-run fails "does not support any combination of challenges". Re-issue it as a DNS-01 lineage and repoint the vhosts; follow the `letsencrypt-wildcard-dns01` skill.

## Inertia SSR service
- A named SSR service runs the Inertia SSR start command with a healthcheck (30s interval, 10s timeout, 3 retries, 20s start period). The SSR URL env var points at its service name and port.
- Start SSR first and wait for healthy. It can stay starting for about 3 minutes. Then recreate app and scheduler. Confirm the "Inertia SSR server started" log line. Never put SSR and app in one `docker compose up` command: the app starts while SSR is still starting, and requests in that window get the empty client shell.
- Any change to the shared `.env` recreates every service that uses that env file, including Redis. Afterward check the restart count and the RDB load line. Splitting Redis into its own env file is the safer fix (not yet done).
- Verify raw HTML: unique title, absolute canonical, server-rendered h1, correct robots directives.
