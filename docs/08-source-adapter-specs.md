# 08 - Source Adapter Specifications

Semua adapters implement interface konseptual:

```php
interface SourceAdapter {
    public function preflight(): SourceHealth;
    public function discover(CrawlCursor $cursor): DiscoveryBatch;
    public function fetch(SourceDocumentRef $ref): FetchedDocument;
    public function extract(FetchedDocument $doc): iterable; // CandidateOpinion
}
```

## Common CandidateOpinion

```text
source_key
external_item_id
external_document_id
canonical_url? 
published_at?
text (temporary)
content_hash
metadata (temporary/minimal)
```

## YouTubeAdapter

Discovery:
- search tracked entity aliases periodically;
- maintain known video IDs;
- avoid repeated search for stable entities.

Fetch:
- comment threads via official API;
- pagination;
- replies optional after launch if quota allows.

Rules:
- raw comment text follows YouTube retention/refresh requirements;
- sentiment aggregate provenance must identify YouTube as a source group when disclosed.

## KaskusAdapter

Discovery:
- category/search/thread index pages yang public;
- tracked thread URLs;
- optional external discovery from search engine only as URL discovery, not source data.

Fetch:
- public thread pages;
- pagination.

Extract:
- strip quotes/signatures/navigation;
- one forum post = candidate opinion unit.

### KASKUS Berita dan Politik (`kaskus_politik`)

- Adapter: `KaskusAdapter`, tanpa pipeline atau formula skor khusus politik.
- Listing: `https://www.kaskus.co.id/komunitas/10/berita-dan-politik`, forum utama Berita dan Politik.
- `crawl_policy.single_page: true`: abaikan cursor halaman lama, ambil halaman pertama setiap
  siklus, simpan kembali `page_1`, dan kembalikan `hasMore: false`. Dedup berdasarkan ID thread
  dan item tetap berlaku saat thread yang sama ditemukan lagi.
- FlareSolverr memakai opsi native `waitInSeconds`: 8 detik untuk halaman komunitas, 3 detik
  untuk pencarian/thread, dan 0 untuk root/robots preflight. Uji 3 detik kadang menemukan 20
  thread tetapi kadang masih kosong; uji 8 detik memuat daftar utama. Render yang tetap kosong
  tidak diganti dengan widget sebagai fallback.
- Hanya tautan daftar thread yang tampil di HTML publik; widget `med=hot_thread` diabaikan sebelum
  dedup, termasuk jika widget menautkan ID yang juga ada pada daftar utama. Tidak menjamin seluruh thread komunitas,
  pagination komentar, atau arsip historis tercakup. Jangan mengganti ini dengan `/api/` yang
  dilarang robots.txt. Perubahan akses tetap diperiksa lewat preflight.
- Ekstraksi politik hanya node isi balasan (`html-content` dengan token kelas `w-full` dan `py-2`),
  tanpa starter berita, nama penulis, atau fallback seluruh halaman. Halaman yang
  belum selesai dirender menghasilkan nol kandidat, bukan opini dari navigasi/byline.
- Batas crawl 10 request/menit per sumber, raw payload TTL 72 jam, tanpa data identitas penulis.
- Seed `enabled: false` menjaga pemeriksaan langsung per lingkungan sebelum aktivasi.

Verifikasi 21 Oktober 2026: preflight halaman publik dan robots.txt berhasil (HTTP 200),
discovery menemukan lima tautan thread, dan satu thread publik menghasilkan empat kandidat
opini. Verifikasi ini belum membuktikan kandidat relevan terhadap entitas atau lolos klasifikasi.

Aktivasi staging 21 Oktober 2026, 10:30 WIB: `enabled: true`, `health_state: healthy`, preflight
tercatat ulang, `single_page: true`, dan cursor lama `page_18` direset ke `page_1`. Discovery
awal diantrekan lewat `sources:backfill kaskus_politik`. Konfigurasi dan cursor sebelum aktivasi
dibackup pada host staging di `/home/dev/compose/suaranetijen/kaskus-politik-before-20261021.json`.
Ketiga worker menerima perbaikan polling; hotfix FlareSolverr khusus worker ketiga dipertahankan.

Hasil sesudah aktivasi (21 Oktober 2026, sekitar 10:33 WIB): dokumen sumber bertambah 40 → 56,
item bertambah 11 → 16; lima item baru terdiri dari dua `processed` dan tiga `skipped`, tanpa
kegagalan ingestion baru pada saat pemeriksaan. Cursor tetap `page_1` dengan waktu crawl baru.
Sebagian fetch masih berjalan; angka ini adalah hasil pemeriksaan awal, bukan klaim seluruh
antrean selesai atau jaminan tambahan skor publik.

Koreksi saat pemantauan lanjutan: konfigurasi lama ternyata menunjuk komunitas `1167` (sekitar
170 thread), bukan forum utama `10` (sekitar 696 ribu thread saat diperiksa). Sampel awal juga
menemukan starter berita yang salah terbaca sebagai sentimen, misalnya kata umum "talenta"
diatribusikan ke produk Talenta dan penyebutan sponsor BNI dianggap evaluasi bank. Aktivasi
ditahan sementara untuk mengganti komunitas, mengabaikan widget, dan membatasi ekstraksi ke
balasan. Angka observasi awal tersebut tidak boleh dipakai sebagai bukti akurasi klasifikasi.
Enam observasi berita yang telah diperiksa dibackup pada host, dihapus melalui
`OffTopicOpinionRemover`, dan agregat lima entitas terdampak dihitung ulang. Empat observasi
lama sebelum aktivasi tidak dihapus. Uji thread forum utama menghasilkan tujuh kandidat balasan.

Pengaktifan ulang setelah koreksi berlangsung 21 Oktober 2026, 10:56 WIB. Policy sumber **dan**
metadata cursor menunjuk forum `10`; metadata cursor lama harus ikut diperbarui karena discovery
mengutamakannya atas policy. Siklus otomatis 11:00 WIB dan siklus ulang 11:11 WIB selesai:
20 dokumen berstatus `fetched`, 127 kandidat balasan mencapai status akhir `skipped`, terdiri dari
118 `entity_not_resolved` dan 9 `not_an_evaluation`. Pengulangan tidak menambah item atau observasi
duplikat dan tidak mencatat kegagalan ingestion baru. Jumlah observasi sumber tetap empat dari
sebelum aktivasi: belum ada balasan baru yang lolos pencocokan entitas dan relevansi opini.
Angka nol ini bukan kegagalan fetch dan tidak boleh diatasi dengan melonggarkan atribusi entitas.
Siklus otomatis 11:30 WIB memperbarui cursor lagi dan menemukan thread tambahan.

Pemantauan juga menemukan preflight renderer worker ketiga melewati batas browser 45 detik.
Setelah pemulihan sesuai runbook `docs/16`, uji terisolasi dengan batas 75 detik berhasil
memeriksa halaman publik dan robots.txt (keduanya HTTP 200), total sekitar 73 detik. Preflight
browser kini mendapat batas 75 detik per request dengan HTTP timeout 90 detik; job dibatasi
150 detik, supervisor discovery/crawl 170 detik, dan Redis `retry_after` 180 detik. Batas browser
untuk discovery/fetch biasa tetap 45 detik. Batas job tetap membatasi keseluruhan pemeriksaan;
dua render yang sama-sama sangat lambat dapat melewatinya dan tetap memicu pengaman karantina.
Ketiga sidecar memakai ID stabil dan unik sebagaimana dicatat pada runbook.
Worker ketiga kemudian kembali mengalami timeout saat mengakses SerayaMotor. Discovery/crawl
pada host tersebut dinonaktifkan dengan `HORIZON_LIGHT_CRAWL_MAX_PROCESSES=0` dan sidecar dihentikan;
worker pertama dan kedua tetap menangani crawl. Tugas analisis worker ketiga tetap berjalan.

## DiskusiWebHostingAdapter

Discovery:
- RSS + forum listing.
- priority forums: testimonial, complaints, shared/cloud hosting, VPS/cloud server, ISP/network.

Exclude:
- offers/WTS/promo signatures.

## SerayaMotorAdapter

Discovery:
- Review Corner;
- Suggestion Corner;
- Our Voices;
- selected Common Topics.

Extract:
- one post = candidate opinion.
- quoted previous posts removed before hashing.

## IndoForumAdapter

Use allowlisted forum IDs only. Aggressive spam prefilter allowed only for obvious promotional/duplicated content; do not judge opinion correctness.

## BlueskyAdapter

Consume Jetstream JSON. Filter records to public post events; candidate filter by normalized entity aliases. Store URI/hash, timestamp, text temporary.

## LowEndTalkAdapter

Target categories Reviews, Providers, Outages and targeted discovered threads. Low crawl rate, cache thread cursors, skip Offers unless a reply clearly appears in a tracked discussion flow and is needed.

## Source health states

- healthy
- degraded
- blocked
- policy_disabled
- parser_broken
- quota_limited

Adapter failure never blocks other sources.
