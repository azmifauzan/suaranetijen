3:    image: azmifauzan/suaranetijen:flare-fix-2d054d5
17:    image: ghcr.io/flaresolverr/flaresolverr:latest

WORKER='set -e; cd "$(docker inspect suaranetijen-horizon-worker --format ...)"; docker compose pull suaranetijen-horizon-worker 2>&1 | tail -2; docker exec suaranetijen-horizon-worker php artisan horizon:terminate; ...'

[w3]  suaranetijen-horizon-worker Error manifest for azmifauzan/suaranetijen:flare-fix-2d054d5 not found: manifest unknown: manifest unknown
[w3] PHP 8.5.10 (cli) (built: Aug 31 2026 19:13:23) (NTS)