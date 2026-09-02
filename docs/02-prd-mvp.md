# 02 - PRD MVP

## Objective

Membuktikan bahwa user memperoleh value dari flow **search entity -> melihat Sentimen Netijen -> membandingkan dengan entity sejenis -> memberi Rating Netijen**.

## Target user

- Konsumen yang ingin mengetahui persepsi publik sebelum membeli/memilih layanan.
- Pengguna teknologi yang membandingkan provider/produk.
- Pengguna yang ingin melihat brand/service mana yang paling positif dibicarakan.

## Core user stories

### Search
- Sebagai user, saya dapat mengetik nama brand/product/service dan menemukan entity yang sesuai meski ada typo ringan.
- Saya dapat melihat skor, jumlah opini, dan rating manual langsung dari search result.

### Entity page
- Saya dapat melihat Sentimen Netijen 0-100.
- Saya dapat melihat distribusi positif/netral/negatif.
- Saya dapat melihat jumlah opini dalam periode yang dihitung.
- Saya dapat melihat Top 5 Suara Netijen: tema yang paling sering dibicarakan netijen tentang
  entity ini, dengan jumlah opini per tema (`docs/25`) — bukan aspect score.
- Saya dapat melihat Rating Netijen 1-5 dari pengguna platform.
- Saya dapat melihat entity related/parent/child.

### Category and ranking
- Saya dapat membuka kategori seperti Smartphone, Mobil, Motor, Cloud & Hosting, ISP, E-commerce.
- Saya dapat melihat top list berdasarkan Sentimen Netijen.
- Entity hanya masuk ranking jika memenuhi minimum observation threshold.

### Rating
- User login dapat memberikan satu star rating aktif per entity.
- User dapat mengubah rating kapan saja.
- Sistem menghitung ulang Rating Netijen.

### Admin
- Admin dapat membuat/edit entity dan aliases.
- Admin dapat enable/disable source.
- Admin dapat melihat crawl jobs, failed jobs, sentiment counts, entity unmatched queue.
- Admin dapat menonaktifkan entity/source tanpa menghapus history.

## MVP public pages

- `/` homepage/search.
- `/search?q=` search results.
- `/e/{slug}` entity detail.
- `/category/{slug}` category browse.
- `/top/{slug}` category ranking.
- `/methodology` metodologi.
- `/sources` daftar source dan status coverage.
- `/about`, `/terms`, `/privacy`.

## Admin pages

- Entities.
- Aliases.
- Categories.
- Sources.
- Crawl jobs.
- Unmatched mentions.
- Sentiment aggregate diagnostics.
- User ratings/moderation.

## In scope

- ±200 seed entities.
- Seven third-party source adapters plus first-party rating.
- Daily/periodic sentiment aggregates.
- Top Suara Netijen: theme extraction, normalization, and frequency ranking (`docs/25`).
- Search autocomplete/fuzzy matching.
- Ranking categories.
- Basic moderation and rate limiting.
- SEO metadata, sitemap, schema markup.

## Out of scope

- Specifications, prices, merchant availability.
- Text review first-party yang panjang; MVP cukup star rating + optional short comment disabled by default.
- Verified purchase/ownership.
- Aspect sentiment (a numeric per-theme/per-aspect score). Top Suara Netijen (`docs/25`) shows
  theme *frequency*, never a theme *score* — it does not reopen this exclusion.
- Influencer/user reputation weighting.
- Demographic inference.
- Political entities.
- B2B dashboard/API.
- Native mobile apps.

## Acceptance criteria

1. Search typo `samsng a57` menemukan Samsung Galaxy A57.
2. Search `vps biznet` menemukan VPS Biznet Gio dan Biznet Gio.
3. Entity dengan >= configured minimum opinions menampilkan Sentimen Netijen.
4. Score dapat dihitung ulang deterministically dari aggregate counts.
5. Crawl source berjalan incremental tanpa mengambil ulang seluruh source.
6. Duplicate source item tidak membuat observation ganda.
7. Star rating satu user/entity bersifat upsert.
8. Ranking hanya menggunakan Sentimen Netijen dan eligibility rules.
9. Website usable di viewport 360px.
10. Semua queue/crawler failures terlihat di Horizon/admin.
11. Entity di bawah Top Suara Netijen threshold menampilkan "Belum cukup opini", bukan tema kosong
    atau tema dengan <3 occurrences (`docs/25`).
12. Duplicate/templated opinion text tidak menaikkan theme observation count secara berulang.
