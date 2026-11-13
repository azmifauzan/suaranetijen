docker exec suaranetijen-horizon-worker php artisan horizon:terminate >/dev/null
for i in $(seq 1 72); do now=$(docker inspect -f '{{.State.StartedAt}}' suaranetijen-horizon-worker 2>/dev/null); [ "$now" != "$before" ] && break; sleep 5; done
echo "horizon drained+restarted after $((i*5))s"
docker compose stop -t 120 suaranetijen-horizon-worker 2>&1 | tail -1