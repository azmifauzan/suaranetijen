docker exec suaranetijen-app php artisan tinker --execute \"App\\Http\\Middleware\\CachePublicPages::flush();\" 2>&1 | tail -1; docker logs --tail 3 suaranetijen-ssr 2>&1

nginx: configuration file /etc/nginx/nginx.conf test is successful

Starting SSR server on port 13714...
Inertia SSR server started.