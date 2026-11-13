=== w2
restarts=0 oom=true started=2026-10-12T07:55:05.175196107Z exit=0
      1   2026-10-12 08:14:56 Worker STOPPED Job timed out
      1   2026-10-12 08:07:35 Worker STOPPED Job timed out
  RSS COMMAND
101440 /usr/local/bin/php artisan horizon:work redis --name=default --supervisor=c3a953978159-dN0H:supervisor-
89136 /usr/local/bin/php artisan horizon:work redis --name=default --supervisor=c3a953978159-dN0H:supervisor-c
=== w3
restarts=0 oom=true started=2026-10-12T07:57:02.368631053Z exit=0
=== w1 (103.123.66.99) suaranetijen-horizon-worker cpu=18.12% mem=779.9MiB / 1GiB ... load average: 0.42, 0.59, 0.69
suaranetijen-horizon-worker cpu=51.43% mem=715.5MiB / 768MiB  (w3)
suaranetijen-horizon-worker cpu=17.05% mem=709.3MiB / 768MiB  (w2)
Assistant: "Masalahnya bukan CPU. ... tiap worker-light jalan dengan 8-9 proses PHP (sekitar 85MB per proses). Itu tidak muat di batas 768MB."
After patch: suaranetijen-horizon-worker cpu=3.31% mem=590.4MiB / 768MiB oom=false