# 16 - Observability & Deployment

## MVP infrastructure

Single VPS baseline:
- 4 vCPU
- 8 GB RAM
- SSD 100GB+ (sesuaikan raw TTL/log volume)

Processes:
- Nginx
- PHP-FPM
- PostgreSQL
- Redis
- Horizon
- scheduler cron

Boleh memisahkan PostgreSQL kemudian; tidak wajib launch.

## Horizon supervisors

Example:
- `supervisor-critical`: critical, aggregate
- `supervisor-crawl`: discovery, crawl
- `supervisor-analysis`: analysis
- `supervisor-maintenance`: maintenance

## Metrics

- queue depth/age;
- job failure rate;
- crawl success rate per source;
- parser failure rate;
- opinions/day;
- unmatched candidate rate;
- sentiment classifier latency/error;
- aggregate freshness;
- search latency;
- page p95 latency.

## Logs

Structured logs with:
- source_key
- document external ID/hash
- entity_id where applicable
- job ID
- model version

Do not log full raw content by default.

## Backup

- PostgreSQL daily encrypted backup.
- 7 daily + 4 weekly retention minimum.
- restore test monthly.
- Redis treated as disposable operational state except streams if explicitly designed otherwise.
