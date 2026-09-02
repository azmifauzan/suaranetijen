# 05 - Technical Architecture

## Baseline stack

- Laravel 13.
- PHP 8.4+ sesuai compatibility Laravel project saat implementasi.
- Inertia + Vue 3.
- Tailwind CSS.
- PostgreSQL.
- Redis + PhpRedis.
- Laravel Queue + Horizon.
- Laravel Scheduler.
- Nginx + PHP-FPM.
- S3-compatible object storage optional; tidak wajib launch.

## Architecture

```text
Browser
  |
Nginx
  |
Laravel modular monolith
  |-- Web / Inertia
  |-- Admin
  |-- Search
  |-- Entity Catalog
  |-- Ratings
  |-- Source Adapters
  |-- Ingestion
  |-- Entity Matching
  |-- Sentiment
  |-- Aggregation / Ranking
  |
  +-- PostgreSQL
  +-- Redis
       +-- queues
       +-- cache
       +-- locks
       +-- rate limits
```

## Process separation

Satu codebase, banyak process:

- PHP-FPM web.
- Horizon supervisors `critical`, `crawl`, `analysis`, `aggregate`.
- Scheduler cron.

## Domain modules

```text
app/Domains/
  Entities/
  Search/
  Sources/
  Ingestion/
  Sentiment/
  Rankings/
  Ratings/
  Moderation/
  Admin/
```

## Why monolith

- Deployment sederhana.
- Queue sudah memisahkan workload berat dari request web.
- Entity/scoring transaction consistency mudah.
- Tidak ada alasan teknis MVP yang mewajibkan service terpisah.

## Extraction trigger

Pisahkan komponen hanya jika:
- sentiment inference butuh GPU/local Python models;
- crawler volume membutuhkan browser cluster;
- search index terlalu besar untuk PostgreSQL;
- analytical queries mengganggu OLTP.
