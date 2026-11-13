---
name: inertia-ssr-docker-production
category: inertia
description: Use when deploying Inertia SSR to Docker/Compose production.
---

Add a named SSR service (e.g., `suaranetijen-ssr`) to docker-compose.yml running `php artisan inertia:start-ssr` with healthcheck enabled (30s interval, 10s timeout, 3 retries, 20s start period). Wire `INERTIA_SSR_URL=http://service-name:port` (e.g., `http://suaranetijen-ssr:13714`) into `.env`. Start the SSR service first, confirm `healthy`, then recreate app and scheduler containers. The SSR healthcheck can stay `starting` for well over 60s after recreate before turning `healthy`; poll for up to ~3 minutes before treating it as failed, and check `docker logs` for `Inertia SSR server started.` in the meantime. Before recreation, run `php artisan horizon:terminate` inside the running app/worker to drain in-flight jobs, wait ~10s, then `docker compose up -d --force-recreate`. Immediately reload reverse proxy: `docker exec nginx-proxy nginx -s reload` — container recreation changes Docker network IPs, and proxy caches will route to dead addresses without reload.

Any change to the shared `.env` recreates every service using that `env_file`, including the stateful Redis container. Redis data survived only through the anonymous volume, so after such a recreate check `RestartCount`, the `Done loading RDB, keys loaded` log line, and `dbsize`. Splitting Redis into its own env_file or compose file is the safer fix (suggested, not yet done).

Verify: curl raw HTML (not through JS), check `<title>` is unique, `<link rel="canonical">` is absolute, `<h1>` renders server-side, `X-Robots-Tag` and meta directives are correct.
