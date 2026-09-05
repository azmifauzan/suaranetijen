# 13 - Search & SEO

## Search MVP

PostgreSQL:
- normalized exact match;
- `pg_trgm` similarity;
- aliases;
- FTS untuk name/category/description.

Ranking search priority:
1. exact entity name;
2. exact alias;
3. prefix;
4. trigram similarity;
5. category context.

Sentiment score boleh menjadi secondary tie-breaker kecil hanya setelah textual relevance, atau tidak dipakai sama sekali di MVP search.

## Post-MVP: theme-based search

Once the Theme Index (`docs/25`) exists, search can answer natural-language demand like "VPS
yang banyak dibilang murah" by ranking entities on theme observation count for a given theme —
still PostgreSQL, still no dedicated search engine or product spec data. This is explicitly
post-MVP (`docs/18`), listed here only so MVP search is not designed in a way that blocks it.

## SEO page model

Entity URL:
`/e/samsung-galaxy-a57`

Category:
`/category/smartphone`

Top:
`/top/smartphone`

Titles:
- `Samsung Galaxy A57: Sentimen & Rating Netizen | SuaraNetijen`
- `VPS dengan Sentimen Netizen Tertinggi | SuaraNetijen`

Gunakan ejaan baku **netizen** pada title, description, dan copy SEO. **Netijen** tetap dipakai
hanya sebagai bagian dari nama metrik resmi seperti Sentimen Netijen, Rating Netijen, dan Top
Suara Netijen.

## Content uniqueness

Entity page mempunyai data unik:
- score;
- distribution;
- opinion count;
- top themes (`docs/25`);
- historical trend;
- rating count;
- related entities.

Jangan membuat thin pages untuk entity tanpa data. Entity di bawah threshold boleh noindex sampai data cukup.

## Structured data

Gunakan schema yang memang sesuai halaman; jangan memalsukan `AggregateRating` untuk Sentimen Netijen. `AggregateRating` hanya untuk first-party rating bila implementasi dan eligibility Google memang sesuai. Sentiment score ditampilkan sebagai metric custom biasa.

## Sitemap

- index hanya active searchable entities;
- separate category/top sitemaps bila volume tumbuh;
- update `lastmod` ketika public snapshot berubah meaningful.
