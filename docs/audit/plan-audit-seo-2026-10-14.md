# Rencana tindak lanjut: Audit SEO eksternal (14 Oktober 2026)

Sumber: `docs/audit/Suaranetijen-Audit-Report.pdf` (Aris Setiawan, RankMySEO, 11 halaman).
Rencana induk SEO tetap `docs/31-seo-plan.md`; dokumen ini hanya memilah temuan audit dan
menambahkan pekerjaan yang belum ada di sana.

## Verifikasi klaim audit (dicek live 14 Oktober 2026)

| # | Klaim audit | Hasil cek | Status |
|---|---|---|---|
| 1 | BreadcrumbList dan FAQPage tercetak dua kali di `/e/xiaomi` | HTML mentah hanya punya 3 blok `application/ld+json` (Organization, BreadcrumbList, FAQPage), masing-masing sekali. String kedua ada di payload Inertia `<script data-page type="application/json">`, yang tidak dibaca Google sebagai structured data | **Positif palsu.** Tutup dengan Rich Results Test |
| 2 | Redirect http->https dan www->apex lambat (630 ms), www timeout | `http://suaranetijen.id/` = satu 301 ke `https://`, 0,12 s. `www.suaranetijen.id` **tidak punya record DNS** sama sekali, jadi timeout | **Benar untuk www.** Perbaikan di Cloudflare, bukan kode |
| 3 | `/contact` 404, tidak ada nama pengelola, tidak ada kebijakan hak jawab | Benar. Footer hanya punya about, metodologi, sumber, GitHub, topik, perbandingan, ketentuan, privasi. Satu-satunya email publik ada di `/privacy` | **Benar** |
| 4 | Lastmod sitemap hanya di kategori dan entitas | 260 dari 267 URL punya `lastmod`, termasuk `/banding` dan `/topik`. Yang tidak: 7 halaman statis. Sitemap sudah terkirim ke GSC sejak 1 Okt (`docs/31`) | **Sebagian besar keliru** |
| 5 | Perbanyak pasangan `/banding` | 33 pasangan di `config/comparisons.php`, semua indexable | Lanjutkan, sudah ada mekanismenya |
| 6 | Tidak ada akun media sosial, nol postingan di X | Benar. Beranda juga tidak punya `sameAs` di JSON-LD Organization | **Benar** |
| 7 | `llms.txt` 404 | Benar | **Benar** |
| 8 | Perluas entitas vertikal keluhan (e-wallet, pinjol, kurir, ISP, bank) | Kategori sudah ada (`bank-e-wallet`, `logistics`, `isp-telco`, `e-commerce`). Kredivo dan Akulaku (paylater) masuk kategori E-commerce. `JNE` dan `JNE Express` dua baris terpisah di `seed_entities.csv` | Masalah cakupan data, bukan template |
| 9 | Digital PR: laporan bulanan | Sudut berita dan pitch sudah ditulis di `docs/31` Fase 4, belum ada halaman laporan dan belum dikirim | Belum dikerjakan |
| 10 | Pasang AggregateRating dari Rating Netijen | Sudah dikodekan (`Entities/Show.vue`), tampil bila `rating_count > 0`. Sweep 174 URL entitas di sitemap: **0** memuatnya, karena belum ada rating pengguna | Kode ada, terhambat data |
| - | Tidak ada GA di beranda | Keliru. `gtag G-8JTWTLSMWX` ada di HTML mentah | Abaikan |

**Temuan tambahan saat verifikasi (tidak disebut audit):** JSON-LD entitas memetakan tipe `person`
ke `"@type": "Product"`, karena kondisinya hanya memeriksa `service` dan `brand`. Tokoh publik
(mis. `/e/dedi-mulyadi`) tercetak sebagai Product, dan bila suatu saat punya rating akan
memancarkan AggregateRating untuk orang, yang tidak didukung review snippet Google.

## Fase A: perbaikan kode kecil (minggu ini)

| # | Pekerjaan | Kriteria selesai |
|---|---|---|
| A1 | JSON-LD entitas: `person` -> `"@type": "Person"`, tanpa `aggregateRating` untuk Person | `curl /e/dedi-mulyadi` memuat `"@type":"Person"`; test Vue/feature menutup kasusnya |
| A2 | Halaman `/contact` (`StaticPageController::contact`, `Pages/Contact.vue`): email kontak (pola `<!--email_off-->` seperti `Privacy.vue`), siapa pengelola, dan bagian **Hak jawab dan koreksi** (siapa yang boleh mengajukan, apa yang dikoreksi: salah cocok entitas, data entitas keliru; apa yang tidak: skor diubah atas permintaan, sesuai prinsip sponsor tidak memengaruhi skor; tenggat respons) | 200, masuk sitemap dan footer, lolos `PublicCopyGuard` |
| A3 | Tautan "Ajukan koreksi" kecil di halaman entitas (terutama `person`) menuju `/contact#koreksi` | Tampil di SSR |
| A4 | `/about`: nama pengelola dan tautan ke `/contact` | Butuh keputusan pemilik (lihat Pertanyaan) |
| A5 | `/llms.txt`: route statis berisi ringkasan situs, rumus skor, tautan ke metodologi, sumber, top list, dan sitemap. Dibangun dari config yang sudah ada, tanpa LLM | `curl /llms.txt` 200 `text/plain` |
| A6 | `sameAs` di JSON-LD Organization beranda | Setelah B2 (akun sosial ada). Jangan isi tautan ke akun yang belum ada |

Dilewati: perbaikan "schema ganda" (positif palsu), lastmod halaman statis (7 URL, tidak berarti).

## Fase B: tindakan pemilik, tanpa kode

| # | Pekerjaan | Kriteria selesai |
|---|---|---|
| B1 | Cloudflare: tambah record `www` (proxied) + Redirect Rule `www.suaranetijen.id/*` -> `https://suaranetijen.id/$1` 301 | `curl -I https://www.suaranetijen.id/x` = satu 301 ke apex |
| B2 | Buat akun X, Instagram, Threads (kanal sesi terbesar per `docs/31`), TikTok bila sanggup mengisi rutin | Akun ada; lanjut A6 |
| B3 | Rich Results Test pada `/e/xiaomi` dan `/banding/samsung-vs-xiaomi` | Tiap tipe schema terdeteksi sekali, tanpa error |
| B4 | GSC: minta indexing 10-20 halaman `/banding` dan entitas teratas; cek "Crawled/Discovered - not indexed" vs baseline 7 Okt | Angka dicatat di `docs/31` |
| B5 | Tawaran audit lanjutan (akses baca GSC untuk auditor) | Keputusan pemilik |

## Fase C: konten dan data (30-90 hari)

| # | Pekerjaan | Catatan |
|---|---|---|
| C1 | Tambah pasangan `/banding` dari bukti permintaan: query GSC, `search_queries`, autocomplete. Audit memberi dua titik volume: `samsung vs xiaomi` 260/bln, `indihome vs biznet` 170/bln | Satu baris per pasangan di `config/comparisons.php`; kedua sisi harus lolos ambang. Cek `indihome-vs-biznet` lolos atau tidak |
| C2 | Cakupan vertikal keluhan: ukur entitas eligible per kategori `bank-e-wallet`, `logistics`, `isp-telco`, `e-commerce` di produksi, lalu tambah alias/sumber untuk yang di bawah ambang (DANA, ShopeePay, JNE, SPX, J&T, IndiHome, XL, Biznet) | Rapikan dulu: gabung `JNE`/`JNE Express`, pertimbangkan subkategori paylater/pinjol untuk Kredivo dan Akulaku. Pinjol ilegal tetap di luar cakupan |
| C3 | Halaman laporan bulanan yang bisa dikutip media (`/topik/...` atau route baru), lalu kirim pitch dari `docs/31` Fase 4 | Copy "sentimen netizen tertinggi"/"paling sering dikeluhkan" sebagai frekuensi; tanpa "terbaik". Butuh keputusan format |
| C4 | Konten sosial mingguan dari top list (kartu OG `/og/e/{slug}.png` sudah ada; kartu top list belum) | Mulai manual dulu; otomasi bila B2 jalan rutin |
| C5 | AggregateRating | Tidak ada kerja kode. Dorong rating pertama (CTA di halaman entitas sudah ada). Pertimbangkan ambang minimal rating sebelum markup tampil |

## Tidak diambil

- Google News (audit sendiri menilai tidak cocok).
- Iklan display (trafik belum ada).
- Klaim profil brand berbayar dan dashboard brand: keputusan produk terpisah; bila dikerjakan,
  tetap di bawah aturan sponsor tidak masuk skor (`docs/26`).

## Keputusan pemilik (14 Oktober 2026)

1. Pengelola dicantumkan sebagai "Tim SuaraNetijen" (tanpa nama perorangan).
2. Email kontak dan koreksi: `info@suaranetijen.id`.
3. Tenggat balasan hak jawab: 5 hari kerja.
4. Laporan bulanan: route baru `/laporan/{tahun}-{bulan}` (Fase C3, belum dibangun).
5. Aturan antislop dipakai saat menulis copy.

## Status Fase A (14 Oktober 2026, belum di-commit dan belum dideploy)

| # | Status | Catatan |
|---|---|---|
| A1 | selesai | `resources/js/lib/entityJsonLd.ts` + test (`entityJsonLd.test.ts`). SSR lokal: `/e/baim-wong` -> `Person`, `/e/samsung` -> `Organization` |
| A2 | selesai | `/contact` (`Pages/Contact.vue`), masuk sitemap, cache halaman publik, dan footer. Email lewat `mailto:` dibungkus `email_off` |
| A3 | selesai | "Ajukan Koreksi" di blok metodologi halaman entitas, menuju `/contact#koreksi` |
| A4 | selesai | Bagian "Siapa yang Mengelola" di `/about` |
| A5 | selesai | `/llms.txt` (`StaticPageController::llms`), ambang dibaca dari `config/scoring.php` |
| A6 | menunggu B2 | |

Verifikasi: 55 tes `tests/Feature/Seo` lulus, 3 tes Vitest lulus, Pint, PHPStan, vue-tsc bersih,
`npm run build` lulus. Halaman produk lokal tidak bisa dirender karena DB lokal belum menjalankan
7 migrasi (`entity_review_videos` dkk.), tidak terkait perubahan ini.

## Status Fase C (19 Oktober 2026)

| # | Status | Catatan |
|---|---|---|
| C1 | selesai | 27 pasangan baru (total 59) di `config/comparisons.php`. Syarat tiap pasangan: kedua sisi lolos ambang di Postgres produksi (snapshot 365d bila ada, sesuai `eligibleSnapshot()`), kategori dan tipe sama, dan ada saran Google Autocomplete untuk "X vs Y". `search_queries` produksi tidak berisi query "vs"/"atau". Dilewati: `indihome-vs-biznet` (Biznet tidak lolos ambang), `indihome-vs-myrepublic` (tipe beda: service vs brand), `dana-vs-gopay` (GoPay 21 opini 365 hari). Ke-27 halaman dicek 200 di produksi sebelum ditambahkan |
| Topik | 12 dipublikasikan | Total 29 topik published. Dilewati: draft berbingkai rekomendasi/perbandingan (editorial), judul berklaim ("Inovasi Terbaru"), slug ber-"terbaik", topik penanganan keluhan, draft yang tumpang tindih dengan topik published, dan kandidat tanpa draft (termasuk `smartphone-ponsel-bagus`, sinyal 137, perlu draft LLM dulu) |

Data yang perlu dirapikan: nama entitas `Infinix Gt 30 Pro`, `Iphone 17`, `Iphone 17 Pro` salah kapital dan ikut tampil di title halaman banding.
