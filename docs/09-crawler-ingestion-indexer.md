# 09 - Crawler, Ingestion & Indexer

## Pipeline

```text
source discovery
 -> document fetch
 -> content extraction
 -> normalize
 -> deduplicate
 -> candidate entity matching
 -> opinion relevance
 -> sentiment classification
 -> sentiment observation
 -> daily aggregate
 -> public snapshot/ranking
```

## Discovery vs fetch

Pisahkan URL/item discovery dari fetch agar source dapat dipoll ringan. Simpan cursor per source/category/thread.

## Backfill

- Seed entity aliases.
- Discover historical thread/video candidates.
- Crawl bertahap dengan queue rate limit.
- Tidak ada kebutuhan menyelesaikan semua backfill sebelum launch; score ditampilkan setelah threshold.

## Incremental

- Track `last_external_id`, page cursor, publish timestamp atau source-specific cursor.
- Revisit active documents, bukan seluruh archive.
- Use conditional requests/ETag/Last-Modified jika source mendukung.

## Deduplication

Minimum:
- unique source external ID;
- normalized content hash;
- remove quoted prior text before hash pada forum.

Near-duplicate filtering boleh dipakai untuk syndicated/repeated text, tetapi jangan menilai user authenticity.

## Rate limiting

Per-source Redis token bucket / Laravel rate limiter. Defaults konservatif dan configurable.

## Temporary raw storage

Raw payload digunakan untuk parser debugging/retry dan NLP, kemudian expired. Hash dan external ID dipertahankan untuk deduplication.

## Reprocessing

Jika model sentiment berubah:
- observations dapat direbuild selama raw content masih ada;
- untuk historical data yang raw-nya sudah expired, jangan mengarang ulang; tandai model_version pada observation/snapshot.

## Idempotency

Semua jobs harus idempotent. Retries tidak boleh menambah observation kedua untuk source item/entity yang sama.
