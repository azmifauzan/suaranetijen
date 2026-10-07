# 31 - SEO Plan

Status: rencana (1 Oktober 2026, diperbarui 2 Oktober 2026 dengan data RankMySEO, Search Console,
dan GA4). Fase 0-1 sudah diimplementasikan, direview, dan dideploy ke staging/produksi pada 2 Oktober 2026 (lihat "Status implementasi"). Melengkapi `docs/13` (model
halaman SEO), `docs/28` (topik), `docs/29` (beranda), dan `docs/30` (relevansi search). Bila ada
konflik, standing constraints di `CLAUDE.md` dan ADR di `docs/21` menang.

## Tujuan

Menaikkan on-page score RankMySEO dari 34 ke >= 80 dan mengubah impresi Search Console menjadi klik
pada keyword long-tail berbahasa Indonesia, dengan data yang hanya SuaraNetijen punya: skor
sentimen, jumlah opini, distribusi, dan tema yang paling sering dipuji atau dikeluhkan.

## Sumber data riset

| Sumber | Yang diambil | Catatan |
|---|---|---|
| RankMySEO (akun Starter) | Overview, Next actions, On-page issues + daftar URL, Search Console, Backlinks, Keyword planner, Rivals, Keyword gap vs `mediakonsumen.com` | Kuota riset harian 1 Okt habis; dilanjutkan 2 Okt |
| Google Search Console | Performance 28 hari, laporan Page indexing beserta daftar URL per alasan | Data performa tertinggal 2-3 hari (sampai 29 Sep) |
| Google Analytics 4 | Traffic acquisition (channel, source/medium), Pages and screens, 28 hari (4 Sep-1 Okt) | |
| `curl` ke `https://suaranetijen.id` | HTML mentah, robots, sitemap, header, TTFB | Ini yang dilihat crawler tanpa JS |
| Props Inertia di 77 halaman entitas live | Jumlah opini, skor, eligibility, jumlah tema, ringkasan | Database lokal adalah salinan lama (0 topik, angka opini berbeda); angka di dokumen ini dari produksi |
| Google Autocomplete (`hl=id`, `gl=id`) | Bentuk kalimat yang benar-benar diketik orang | Bukti permintaan, bukan angka volume |
| Kompetitor (`brandlist.id`, `mediakonsumen.com`, dll) | Struktur URL, title, heading, schema, jumlah kata | Diambil lewat `curl` dan web search |

## Baseline (1 Oktober 2026)

| Metrik | Nilai |
|---|---|
| On-page score | 34/100, 142 halaman, 587 issue |
| Domain authority | 2 |
| Backlink / referring domain | 3 / 1 |
| Search Console 28 hari | 1 klik, 12 impresi, posisi rata-rata 75.3 |
| Keyword yang dilacak | 4, semuanya "sentiment analysis ..." (bahasa Inggris, intent salah) |
| Kompetitor di RankMySEO | `hootsuite.com`, diganti `mediakonsumen.com` pada 1 Okt 2026 (batas Starter: 1 kompetitor) |
| Search Console 28 hari (2 Okt, data s.d. 29 Sep) | 1 klik, 25 impresi, CTR 4%, posisi rata-rata 33,7 |
| Page indexing (GSC) | 78 terindeks; 217 tidak: 155 noindex, 38 discovered-not-indexed, 18 crawled-not-indexed, 6 server error 5xx |
| GA4 28 hari | 1.185 sesi, 965 pengguna aktif, 3.302 views, 0 key event; organic search 10 sesi (0,8%) |
| Sitemap | 142 URL: 77 `/e`, 24 `/category`, 24 `/top`, 10 `/topik`, 7 halaman statis |
| TTFB | `/e/samsung` 1,29 s, `/` 0,97 s, `/methodology` 0,22 s; Cloudflare `DYNAMIC`, tidak ada cache edge |

Query Search Console yang muncul hampir semuanya nama orang atau entitas (`trajaan`, `albert zhang`,
`abdul kadir karding`, `vivo`, `mazda`, `komentar shopee`, `isp di indonesia`). Halaman yang sudah
tampil di posisi 4-11: `/methodology`, `/sources`, `/privacy`, `/about`, `/category/isp-telco`,
`/category/brand-umum`, `/search`, `/register`.

## Temuan utama: SSR tidak jalan di produksi

`config/inertia.php` memasang `ssr.enabled = true`, tetapi:

- `package.json` `build` hanya menjalankan `vp build`; `build:ssr` ada tetapi tidak dipanggil Dockerfile;
- tidak ada proses `php artisan inertia:start-ssr` di container mana pun;
- adapter Laravel gagal menghubungi server SSR lalu diam-diam jatuh ke render sisi klien.

Akibatnya setiap halaman mengirim `<head>` default dari `resources/views/app.blade.php` dan
`<body>` kosong. Googlebot merender JS sehingga akhirnya melihat title yang benar, tetapi crawler lain
(RankMySEO, Bing pada sebagian kunjungan, preview WhatsApp/X/Facebook, crawler AI) tidak.

Semua 5 "Next actions" RankMySEO berasal dari satu akar ini:

| Next action RankMySEO | Halaman | Penyebab |
|---|---|---|
| Duplicate titles | 118 | Title default blade sama di semua halaman |
| Duplicate meta descriptions | 118 | Description default blade sama di semua halaman |
| Thin content | 118 | `<body>` tidak berisi teks sebelum JS jalan |
| Missing H1 | 118 | H1 hanya ada setelah Vue mount |
| Unfriendly URL | 115 | Daftar URL (dibuka 2 Okt) berisi slug yang sudah bersih: `/about`, `/e/apple`, `/e/honda-beat`, `/e/bank-mandiri`. Jadi pemeriksaannya "URL memuat kata dari title", dan title-nya sama untuk semua halaman. Tidak perlu ganti URL; selesai bersama SSR |

Enam action "Competitor gaps" dari `hootsuite.com` hilang setelah kompetitor diganti.

### Temuan turunan: noindex tidak sampai ke crawler selain Google

Karena `robots` meta juga hanya dipasang di klien, HTML mentah selalu berisi `index, follow`:

| URL | HTML mentah | Seharusnya |
|---|---|---|
| `/e/abdul-kadir-karding` (0 opini) | `index, follow` | `noindex, follow` |
| `/search?q=samsung` | `index, follow` | `noindex, follow` (`docs/28`) |
| `/login`, `/register` | `index, follow` | `noindex` |

Koreksi 2 Oktober: laporan Page indexing menunjukkan Google **menghormati** noindex sisi klien (155
halaman "Excluded by noindex tag"), karena Googlebot merender JS. Jadi risiko halaman tipis terindeks di
Google lebih kecil dari perkiraan awal. Risiko yang tersisa:

- Bing, crawler AI, dan preview sosial tidak merender JS, jadi mereka melihat `index` pada 497 entitas
  aktif, termasuk sekitar 420 yang tipis;
- Google harus merender setiap halaman sebelum tahu halaman itu noindex. Itu menghabiskan antrean render
  dan memperlambat penemuan halaman yang memang layak;
- `/e/abdul-kadir-karding` dan `/register` tetap sempat tampil di Search Console sebelum dirender.

## Temuan Search Console dan GA4 (2 Oktober 2026)

**Server error (5xx), 6 URL**, pertama terdeteksi 22 Sep: `/sitemap.xml`, `/top/tokoh-publik`,
`/top/ride-hailing`, `/top/consumer-brands`, `/e/toyota`, `/e/uya-kuya`, `/e/james-prananto`. Semua
mengembalikan 200 saat dicek ulang 2 Okt; kemungkinan besar terjadi saat redeploy atau insiden sekitar
22 Sep. Tindakan: tekan "Validate fix" di GSC. `/sitemap.xml` butuh 2,3 detik; sitemap yang lambat
atau gagal membuat Google berhenti membacanya, jadi cache sitemap (lihat Fase 0).

**Crawled - currently not indexed, 18 URL**, termasuk entitas dengan data kuat: `/e/daihatsu` (222
opini), `/e/dana` (114), `/e/honda-brio`, `/e/telkomsel`, `/e/vidio`, `/e/matahari`,
`/e/tecno-pova-6-pro`, `/e/tecno-camon-30-pro`, `/category/logistics`, `/top/saas-software`. Google
sudah membaca halaman ini dan memutuskan nilainya terlalu rendah. Ini sinyal kualitas konten: title
generik, teks tipis, dan halaman yang mirip satu sama lain. Fase 1 ditujukan ke sini.

**Discovered - currently not indexed, 38 URL**: diketahui tetapi belum dirender. Sejalan dengan antrean
render yang terbebani oleh halaman yang ternyata noindex.

**GA4: lalu lintas datang dari sosial, bukan dari pencarian.**

| Sumber / medium | Sesi | Porsi | Engagement rata-rata |
|---|---|---|---|
| `l.threads.com` / referral | 759 | 64% | 16 s |
| (direct) | 183 | 15% | 36 s |
| `lm.facebook.com`, `facebook.com`, `l.facebook.com` | 96 | 8% | 12-37 s |
| `checkout.pymnt.app` (pembayaran sponsor) | 36 | 3% | 3 m 36 s |
| `panjat` / leaderboard, `pamerin`, `digimarket` | 55 | 5% | 11-30 s |
| Organic Search | 10 | 0,8% | 6 m 10 s |

- Pengunjung organik sedikit tetapi paling terlibat (6 menit, 26 event per sesi). Mereka datang dengan
  pertanyaan dan menemukan jawabannya.
- Beranda menerima 1.599 dari 3.302 view, tetapi rata-rata hanya 14 detik dan 1,67 view per pengguna:
  pengunjung Threads mendarat di beranda lalu pergi. Mengirim tautan ke halaman entitas atau topik yang
  spesifik akan lebih berguna daripada tautan beranda.
- 72% lalu lintas lewat preview tautan sosial. Preview Threads dan Facebook membaca `og:title` dan
  `og:image` dari HTML mentah, jadi tanpa SSR setiap tautan yang dibagikan tampil dengan judul generik
  dan gambar SVG yang tidak didukung. Item 0.1 dan 0.8 berdampak langsung ke kanal terbesar saat ini.
- 0 key event: GA4 belum tahu apa yang dianggap berhasil. Tandai sebagai key event: pencarian
  (`search`), buka halaman entitas dari hasil pencarian, kirim rating, klik sponsor.
- Search Console belum ditautkan ke GA4 (rekomendasi muncul di beranda GA4). Tautkan supaya query dan
  landing page organik terlihat di satu tempat.
- `panjat`, `pamerin`, dan `digimarket` mengirim kunjungan, tetapi RankMySEO hanya menghitung 1 referring
  domain. Kemungkinan tautannya `nofollow`, lewat redirect, atau dirender sisi klien. Belum diverifikasi.

## Kompetitor

Pengganti `hootsuite.com` (sentiment SaaS global, intent dan pasar berbeda):

| Situs | Peran | Kenapa relevan |
|---|---|---|
| `mediakonsumen.com` | Dilacak di RankMySEO | Menguasai "keluhan X", "review indihome"; juga sumber data kita (`MediaKonsumenAdapter`) |
| `brandlist.id` | Pembanding model bisnis paling dekat | Skor brand 0-10, metodologi terbuka, 495 brand, URL `/{kategori}/{subkategori}/{brand}/`, halaman `/compare/a-vs-b/` |
| `bandingvps.com`, `101internet.id` | Ceruk hosting dan ISP | Peringkat untuk "review vps biznet gio" dan "review indihome" |
| `inet.detik.com`, `idntimes.com`, `kompas.com` | Media, tidak dikejar | Domain authority terlalu tinggi untuk head term; kita menang di sudut "kata netizen", bukan spesifikasi |
| `carmudi.co.id`, `oto.com` | Ceruk otomotif | Peringkat untuk "kekurangan X", "X vs Y" mobil |

Pola halaman `brandlist.id` yang perlu dibandingkan (diambil dari `/keuangan/bank-digital/jenius/`):

- title `Ulasan Jenius: Skor 6.6/10 (B+) | BrandList`, angka skor di title;
- ±2.800 kata di HTML mentah (SSR);
- H2 per bagian: "Kelebihan dan kekurangan", "Analisis ulasan pengguna", "Pertanyaan seputar";
- JSON-LD `BreadcrumbList`, `FAQPage`, `ItemList`, `Organization`;
- halaman perbandingan `x-vs-y` yang dibuat dari data dua brand.

Ganti kompetitor di RankMySEO bila paket naik: tambahkan `brandlist.id` sebagai kompetitor kedua.

## Riset keyword

### Bukti volume (RankMySEO Planner, Indonesia)

| Keyword | Volume/bulan | Difficulty | CPC |
|---|---|---|---|
| review iphone 16 | 170 | 43 | $0.06 |
| review indihome | 140 | 26 | $0.54 |
| vps biznet gio | 140 | 42 | $0.28 |
| review infinix | 110 | 28 | $0.09 |

Phrase yang lebih panjang ("hp infinix bagus atau tidak", "review byd", "review xiaomi") mengembalikan
"—": bukan nol, tetapi di bawah ambang data tool.

### Bukti permintaan (Google Autocomplete)

| Pola | Contoh saran Google |
|---|---|
| `X vs Y bagus mana` | oppo vs vivo bagus mana; vivo vs samsung bagus mana; avanza vs ertiga bagus mana; beat vs scoopy irit mana; iphone 15 vs 16 bagus mana |
| `bagusan X atau Y` | bagusan byd atau wuling; bagus samsung atau iphone; bagus samsung atau xiaomi |
| `X bagus atau tidak` | byd bagus atau tidak; infinix bagus gak; dana aman atau tidak |
| `kekurangan X` / `kelebihan dan kekurangan X` | kekurangan toyota avanza; kekurangan ertiga hybrid; kelebihan dan kekurangan honda beat; kekurangan byd atto 1 |
| `keluhan X` | keluhan shopee; keluhan shopeepay; keluhan pelanggan shopee |
| `X worth it` | iphone 16 worth it sampai kapan |

Kata "netizen" dan "sentimen" tidak muncul di query pengguna (kecuali makna kata "netizen" itu sendiri).
Title dan H1 harus memakai kosakata pencari ("review", "bagus atau tidak", "kelebihan dan
kekurangan", "bagus mana"), lalu "netizen" sebagai pembeda di dalamnya.

### Inventori yang bisa ditarget

Hanya entitas yang lolos threshold publik (>= 30 opini) yang boleh ditarget. Distribusi 77 entitas di
sitemap produksi:

| Kategori | Entitas | Contoh dengan data terkuat (opini, skor) |
|---|---|---|
| Mobil | 24 | Toyota 1.088 (72,4), BYD 632 (74,0), Hyundai 468 (74,5), Toyota Avanza 225 (77,3), Suzuki Ertiga Hybrid 202 (81,7) |
| Smartphone | 20 | Samsung 2.134 (72,5), Xiaomi 1.222 (80,0), Oppo 865 (84,6), Apple 776 (58,4), Vivo 276 (89,1), Infinix 224 (76,3) |
| FMCG | 8 | |
| Motor | 6 | Honda Beat 131 (77,9), Suzuki Satria F150 93 (83,3) |
| ISP & Telco | 4 | |
| E-commerce, Brand Umum, Bank & E-Wallet | 3 masing-masing | Shopee 189 (54,2), DANA 114 (70,2), Bank Mandiri 96 (74,0) |
| Ride Hailing, Politisi, Selebriti, SaaS | 1-2 | GitHub 1.191 (99,5) |

70 dari 77 punya ringkasan Suara Netijen; 7 tidak punya tema sama sekali (contoh: Honda Mobil).

### Keyword gap vs `mediakonsumen.com` (RankMySEO, 2 Oktober 2026)

`mediakonsumen.com` mendapat sekitar 2.302 kunjungan/bulan dari 200 keyword (119 di top 10, rata-rata
#13,6). 100 keyword yang mereka punya dan kita tidak, dikelompokkan:

| Pola | Contoh (volume, difficulty, posisi mereka) | Untuk kita |
|---|---|---|
| `X bagus atau tidak` / `apakah X bagus` | tv merk aqua bagus atau tidak (170, KD 15, #2); wifi ion apakah bagus (170, KD 31, #4) | **Ya.** Pola klaster A terbukti punya volume dengan difficulty rendah. Mediakonsumen menang hanya dengan satu surat pembaca; kita punya ratusan opini teragregasi |
| `X review` / `ulasan X` | koinworks review (140, KD 18); review tunaiku (320, KD 18); kredione review (170, KD 28); ulasan widarin (140, KD 12) | Ya, untuk entitas yang kita punya. Fintech/pinjol belum ada di seed |
| `kenapa tagihan X naik` / `tagihan X naik` | kenapa tagihan indihome naik (260, KD 23); tagihan indihome naik (260, KD 28) | Ya bila tema "kenaikan harga" muncul untuk entitas itu. Jawab dari tema, bukan dari fakta tarif |
| Customer service | customer service xl (4.400, KD 25, #48); cs xl (3.600, KD 26, #28) | Tidak. Intent-nya mencari kontak, bukan opini |
| Keluhan pengiriman | paket shopee tidak sampai (210, KD 25); spx instant (4.400); yes jne (12.100) | Sebagian: `/e/shopee` bisa menampilkan tema keluhan pengiriman. Keyword layanan JNE/SPX bukan untuk kita |
| `X penipuan` / `X penipu` | penipuan ovo (9.900, KD 24, #38); penipuan kredivo (2.900); lakuemas penipu (170) | **Tidak.** Risiko hukum (pencemaran nama baik) dan bertentangan dengan prinsip "index menilai entitas dari agregat, bukan memvonis" |
| Cabang lokal, istilah umum | indomaret pagarsih; bpjs ketenagakerjaan jakarta gambir; exceeded artinya; ready player one | Tidak. Bukan entitas kita |

Kesimpulan: keyword gap mengonfirmasi klaster A (bagus atau tidak) dan B (review/keluhan) dari data
yang sebenarnya, dengan difficulty 12-31, mudah untuk domain baru. Mediakonsumen tidak punya halaman
per brand; mereka menang lewat artikel tunggal. Halaman entitas yang dirender di server dengan agregat
opini adalah jawaban yang lebih lengkap untuk intent yang sama.

## Klaster keyword dan halaman target

Satu keyword utama dipetakan ke satu URL. Jangan membuat URL baru bila halaman yang ada bisa memenuhi
intent.

| Klaster | Pola keyword | Halaman target | Prioritas |
|---|---|---|---|
| A. Review entitas | `review X`, `X bagus atau tidak`, `X menurut pengguna` | `/e/{slug}` | P1 |
| B. Kelebihan dan kekurangan | `kelebihan dan kekurangan X`, `kekurangan X` | `/e/{slug}`, bagian baru dari tema positif/negatif | P1 |
| C. Perbandingan | `X vs Y`, `X vs Y bagus mana`, `bagusan X atau Y` | `/banding/{a}-vs-{b}` (baru, perlu keputusan) | P2 |
| D. Keluhan | `keluhan X` | `/e/{slug}#keluhan` dulu; halaman sendiri hanya jika volume terbukti | P2 |
| E. Daftar kategori | `merek hp paling disukai`, `mobil dengan sentimen tertinggi` | `/top/{slug}`, `/topik/{slug}` | P2 |
| F. Merek di bawah threshold | apa pun | Tidak ditarget, tetap `noindex` | - |

Prioritas awal per klaster (data kuat, permintaan terbukti):

- A/B: Infinix, iPhone 16, Samsung Galaxy S24 Ultra, Toyota Avanza, Suzuki Ertiga Hybrid, Honda Beat,
  BYD, Xiaomi, Oppo, Vivo, Shopee, DANA, Biznet Gio / VPS Biznet Gio (cek eligibility di produksi).
- C: oppo-vs-vivo, samsung-vs-xiaomi, oppo-vs-samsung, vivo-vs-samsung, iphone-15-vs-iphone-16,
  toyota-avanza-vs-suzuki-ertiga-hybrid, toyota-avanza-vs-mitsubishi-xpander, byd-vs-hyundai.
- E: `/top/smartphone`, `/top/mobil` dengan title yang memakai "merek HP" dan "mobil".

Ditolak:

- `apakah X error hari ini` (Bank Mandiri, dll). Intent real-time; pipeline kita harian dan bukan
  status page. Menargetkannya menjanjikan sesuatu yang tidak kita punya.
- `hp terbaik`, `mobil terbaik`. Melanggar aturan copy ("sentimen netijen tertinggi", bukan
  "terbaik di Indonesia") dan dikuasai media besar.
- Spesifikasi dan harga. ADR-008 dan audit 001 menghapus spesifikasi dari halaman publik.

## Rencana kerja

### Fase 0: perbaikan teknis (minggu 1, prasyarat semua fase lain)

| # | Pekerjaan | Kriteria selesai |
|---|---|---|
| 0.1 | Jalankan SSR di produksi: `build` memanggil `vp build && vp build --ssr` (atau Dockerfile memanggil `build:ssr`), server SSR berjalan sebagai proses terpisah (`php artisan inertia:start-ssr`, mis. container `suaranetijen-ssr` dari image yang sama dengan `command:` override, sama seperti horizon/scheduler), `INERTIA_SSR_URL` menunjuk ke sana | `curl /e/samsung` memuat title unik, H1, teks ringkasan, dan JSON-LD tanpa JS |
| 0.2 | Pastikan bundle SSR berjalan setelah `rm -rf node_modules` di Dockerfile (dependency harus ter-bundle atau `node_modules` produksi dipertahankan) | Server SSR tidak crash saat start; health `GET /health` = OK |
| 0.3 | Pantau kegagalan SSR: dengarkan event `SsrRenderFailed` dan log, tambahkan cek ke `monitor:metrics` | Render yang gagal terlihat di log, tidak lagi diam |
| 0.4 | Noindex di sisi server, tidak bergantung pada SSR: header `X-Robots-Tag: noindex, follow` untuk entitas di bawah threshold, `/search?q=*`, `/login`, `/register`, `/forgot-password`, halaman sponsor checkout/status | `curl -I` menunjukkan header pada URL tersebut |
| 0.5 | Default `robots` di `app.blade.php` jangan `index, follow` untuk semua; render nilai dari server berdasarkan halaman | Tidak ada halaman tipis dengan `index` di HTML mentah |
| 0.6 | `robots.txt`: tambah `Sitemap: https://suaranetijen.id/sitemap.xml`; `Disallow` untuk `/admin`, `/dashboard`, `/settings` | |
| 0.7 | Keluarkan `/search` dari sitemap (halaman alat, bukan konten) | |
| 0.8 | `og:image` saat ini SVG (`/logo.svg`); WhatsApp, X, dan Facebook tidak mendukung SVG. Ganti default ke PNG 1200x630, lalu (Fase 2) gambar OG per entitas | Preview link di WhatsApp menampilkan gambar |
| 0.9 | Cache halaman publik untuk tamu: jangan set cookie session pada GET publik anonim bila tidak perlu, lalu cache di Cloudflare dengan TTL pendek (5-15 menit) atau cache response di Laravel | TTFB `/e/{slug}` < 400 ms dari cache |
| 0.10 | Setelah 0.1-0.7 live: resubmit sitemap di Search Console, minta re-crawl di RankMySEO | On-page score naik; 5 Next actions turun drastis |
| 0.11 | Cache respons `/sitemap.xml` (saat ini 2,3 s) dan regenerasi saat snapshot berubah | Sitemap < 300 ms; tidak ada lagi 5xx pada sitemap di GSC |
| 0.12 | GSC: "Validate fix" untuk 6 URL Server error (5xx); semuanya sudah 200 | Validasi lulus |
| 0.13 | GA4: tautkan Search Console; tandai key event (`search`, buka entitas dari hasil pencarian, kirim rating, klik sponsor) | Laporan Search Console muncul di GA4; key event > 0 |

Target fase 0: on-page score >= 70.

### Fase 1: template on-page entitas (minggu 2-3)

1. Title entitas (maks ±60 karakter, kosakata pencari dulu):
   - eligible: `Review {Nama}: Bagus atau Tidak Menurut {n} Opini Netizen`
   - bila terlalu panjang: `Review {Nama} Menurut Netizen: Sentimen {skor}/100`
   - di bawah threshold: tetap `noindex`, title boleh generik.
2. Meta description dibangkitkan dari data, deterministik, tanpa LLM:
   `{n} opini netizen tentang {Nama}: {pos}% positif, {neg}% negatif. Paling sering dipuji: {tema+}.
   Paling sering dikeluhkan: {tema-}.` Lolos `PublicCopyGuard`.
3. H1 `{Nama}` + subjudul berisi kalimat intent ("Bagus atau tidak menurut netizen?").
4. Bagian baru "Kelebihan dan kekurangan menurut netizen", diambil dari tema positif dan negatif
   `docs/25`. Hanya label tema dan frekuensi ("disebut 9 kali"), tanpa skor per tema (standing
   constraint). Entitas tanpa tema tidak menampilkan bagian ini.
5. Bagian "Pertanyaan seputar {Nama}", 3-5 tanya jawab yang dibangkitkan dari data:
   "Apakah {Nama} bagus menurut netizen?", "Apa keluhan paling sering tentang {Nama}?",
   "Dari mana data ini berasal?". Jawaban berasal dari angka dan ringkasan yang sudah ada.
   Catatan: Google membatasi rich result FAQ untuk situs pemerintah dan kesehatan sejak 2023, jadi
   ini untuk konten dan crawler AI, bukan untuk snippet bintang.
6. Isi `description` entitas yang kosong (Avoskin, GitHub, Matahari, Vidio, dll) karena itu teks
   pembuka halaman.
7. Teks konteks per kategori di `/category/{slug}` dan `/top/{slug}` (2-3 kalimat statis dari admin),
   supaya halaman kategori tidak tipis.

### Fase 2: struktur dan tautan (minggu 3-5)

1. JSON-LD:
   - `BreadcrumbList` di `/e`, `/category`, `/top` (saat ini hanya ada di `/topik/{slug}`);
   - `ItemList` di `/top/{slug}` dan `/category/{slug}`;
   - `FAQPage` untuk bagian tanya jawab Fase 1;
   - `AggregateRating` tetap hanya untuk Rating Netijen first-party yang sudah ada (ADR-007/011).
2. Tautan internal:
   - entitas -> kategori -> top list -> entitas lain di kategori yang sama;
   - brand <-> produk (sudah ada lewat `relatedEntities`, pastikan tautannya ter-render SSR);
   - `/topik/{slug}` menaut ke setiap entitas di daftar;
   - entitas menaut ke halaman perbandingan yang melibatkannya (Fase 3).
3. Gambar OG per entitas (skor, jumlah opini, nama), dibangkitkan dan di-cache, untuk CTR dari
   share sosial.
4. `lastmod` sitemap mengikuti perubahan snapshot yang berarti (`docs/13`), bukan `updated_at`.

### Fase 3: halaman perbandingan (minggu 5-8, perlu keputusan produk)

`/banding/{a}-vs-{b}` untuk pasangan entitas yang:

- sama kategori dan sama tipe (brand vs brand, produk vs produk);
- keduanya lolos threshold publik;
- ada bukti permintaan (autocomplete, Search Console, atau `search_queries`).

Isi: dua kartu Sentimen Netijen berdampingan, distribusi masing-masing, tema yang paling sering
dipuji dan dikeluhkan untuk tiap sisi, tautan ke kedua entitas. Copy "sentimen lebih tinggi",
bukan "lebih bagus". Tidak ada skor gabungan atau pemenang; metrik tidak digabung (ADR-007/011).
Slug kanonik urut abjad (`oppo-vs-vivo`, bukan `vivo-vs-oppo`; yang lain 301).

Pembuatan halaman mengikuti alur `docs/28`: kandidat otomatis, review admin, lalu publish. Jangan
membangkitkan semua pasangan.

Keputusan yang perlu dibuat sebelum dibangun: apakah perbandingan dua skor sentimen di satu halaman
sesuai dengan prinsip ADR-011, dan siapa yang mengkurasi daftar pasangan.

### Fase 4: off-page (berjalan terus mulai minggu 2)

Domain authority 2 dengan 1 referring domain adalah batas atas peringkat. Langkah:

1. Profil dan direktori: Google Business Profile (bila memenuhi syarat), Product Hunt, direktori
   startup Indonesia (DailySocial, StartupRanking), GitHub repo publik bila open source.
2. Konten berbasis data yang layak dikutip: laporan bulanan "brand smartphone dengan sentimen netizen
   tertinggi bulan ini" di `/topik` atau blog, dengan grafik yang bisa disematkan beserta atribusi.
3. Pitch ke media teknologi dan otomotif (detik inet, Kompas Tekno, IDN Times, Gridoto) dengan angka
   unik: "70% opini netizen tentang Samsung positif, keluhan paling sering soal harga".
4. Papan Sponsor: halaman sponsor memberi alasan bagi sponsor untuk menaut balik ke profilnya.
5. Badge untuk brand: widget skor yang bisa ditempel di situs brand, dengan tautan balik.

Target: 15 referring domain dalam 3 bulan.

### Fase 5: pengukuran dan iterasi (setiap minggu)

- RankMySEO tracked keywords: **kerjakan sekarang, tidak menunggu SSR.** Per 2 Okt keempat keyword
  "sentiment analysis" ada di posisi 20+ untuk kita dan untuk `mediakonsumen.com`, jadi Rank compare
  kosong dan tidak memberi informasi. Ganti dengan `review infinix`, `review iphone 16`,
  `vps biznet gio` (batas Starter 3/situs). Saat paket naik, tambah `oppo vs vivo`,
  `kekurangan toyota avanza`, `kenapa tagihan indihome naik`.
- Search Console: ekspor query per minggu, cari query di posisi 8-20 dan perkuat halamannya.
- Buka lagi RankMySEO pasca-SSR untuk memastikan "Unfriendly URL" dan "Thin content" hilang tanpa
  perubahan slug.
- GSC Page indexing per minggu: pantau "Crawled - currently not indexed" (18) dan "Discovered" (38).
  Keduanya harus turun setelah Fase 0-1.
- Distribusi ke sosial: Threads adalah kanal terbesar. Bagikan tautan ke `/e/{slug}` atau `/topik/{slug}`,
  bukan beranda (beranda: 14 detik engagement), dan baru setelah 0.1/0.8 live supaya preview benar.
- Jalankan Lighthouse mobile di `/`, `/e/samsung`, `/top/smartphone` setelah SSR (LCP, CLS, INP).

## Status implementasi (review 2 Oktober 2026)

Implementasi Fase 0-1 di-commit (`0c06c9d`) dan dideploy 2 Oktober 2026. Direview dengan
menjalankan server SSR sungguhan (`php artisan inertia:start-ssr`) dan mengambil HTML mentah dari
tiap jenis halaman, bukan hanya membaca kode.

| Item | Status | Catatan |
|---|---|---|
| 0.1 build SSR | selesai | `npm run build` menghasilkan `bootstrap/ssr/app.js`; semua import runtime SSR ada di `dependencies`, jadi `npm prune --omit=dev` di Dockerfile aman |
| 0.1 proses SSR di produksi | selesai (2 Okt) | Container `suaranetijen-ssr` di host utama, healthy; `INERTIA_SSR_URL=http://suaranetijen-ssr:13714`. Diverifikasi dari luar: title, canonical absolut, H1, noindex dan `X-Robots-Tag` benar di HTML mentah |
| 0.3 pemantauan SSR | selesai | listener `SsrRenderFailed` + cek `/health` di `monitor:metrics` |
| 0.4, 0.5 noindex sisi server | selesai | `ApplyRobotsPolicy` + `RobotsPolicy`; header `X-Robots-Tag` dan meta ikut berubah. Diverifikasi: entitas di bawah threshold, `/login`, `/search?q=` |
| 0.6, 0.7, 0.11 robots, sitemap | selesai | `Sitemap:` di robots.txt, `/search` keluar dari sitemap, sitemap di-cache dan dibersihkan saat snapshot atau topik berubah |
| 0.8 og-image | selesai | `public/og-image.png` 1200x630, `twitter:card` menjadi `summary_large_image` |
| 0.9 cache halaman publik | **belum** | Tidak ada perubahan cache respons atau cookie session pada GET anonim; TTFB entitas tetap sekitar 1,3 s |
| 0.12, 0.13 akun GSC/GA4 | belum | Pekerjaan di dashboard, bukan kode |
| Fase 1 title, meta, subjudul, FAQ, kelebihan/kekurangan, konteks kategori | selesai | Output SSR diverifikasi untuk entitas eligible, entitas kecil, kategori, top, login, search, metodologi |

Gap yang ditemukan di review dan sudah diperbaiki:

1. **Prop `seo` pada halaman entitas menimpa prop `seo` bersama** (`site_url`, `site_name`) dari
   `HandleInertiaRequests`. Akibatnya `canonical`, `og:url`, dan `og:image` di semua halaman entitas
   menjadi path relatif (`/e/infinix`, `/og-image.png`), dan preview sosial tetap rusak walau SSR
   hidup. Prop entitas diganti nama menjadi `entitySeo`; aturan dicatat di `.ai/rules/controllers.md`.
   Test lama hanya memeriksa prop Inertia sehingga tidak menangkap ini; test baru memeriksa
   `seo.site_url` tetap ada.
2. **Meta description `/category/{slug}` dan `/top/{slug}` identik** (keduanya memakai deskripsi
   konteks kategori, 113-181 karakter, sebagian melewati 160). Keduanya kini punya meta description
   sendiri; paragraf konteks tetap tampil di badan halaman.
3. **Label tema bersuperlatif dipotong, bukan dilewati**: "Samsung terbaik" menjadi "Paling sering
   dipuji: Samsung." Kini label itu dilewati dan label berikutnya yang dipakai.
4. **Klaim "objektif" kembali muncul** di jawaban FAQ dan deskripsi kategori cadangan, padahal audit 001
   (temuan 5) menghapusnya. Diganti dengan pernyataan yang sama dengan halaman Metodologi (sponsor tidak
   memengaruhi skor).
5. **PHPStan gagal (7 error)** di `EntitySeoService`; tipe array ditulis lengkap dan `??` yang tak
   perlu dibuang. `auth/*` dan `go/*` ditambahkan ke daftar noindex.

Verifikasi: 610 tes lulus (2.906 assertion), Pint dan PHPStan bersih, `npm run build` lulus.

Gap yang masih terbuka:

- ~~Tidak ada timeout untuk permintaan SSR~~ (diperbaiki 2 Okt): `inertia-laravel` 3.3.3 tidak punya
  `inertia.ssr.timeout`, jadi `App\Http\Ssr\TimeoutHttpGateway` menggantikan `HttpGateway` dengan
  timeout 3 s dan connect timeout 1 s (`INERTIA_SSR_TIMEOUT`, `INERTIA_SSR_CONNECT_TIMEOUT`), termasuk
  untuk health check. Diuji terhadap socket yang menerima koneksi tetapi tidak pernah menjawab: halaman
  kembali ke render sisi klien setelah sekitar 3 detik, dan `SsrRenderFailed` tercatat. Hapus kelas ini
  bila paket sudah mendukung opsi timeout sendiri. Healthcheck Docker dan `restart: unless-stopped` tetap
  dipakai supaya proses yang menggantung dimulai ulang.
- 0.9 (cache halaman) belum dikerjakan.
- `Welcome.vue` masih berisi "Tiga metrik objektif, tanpa kompromi", klaim yang dihapus audit 001 dari
  halaman lain.
- Halaman `/leaderboard` (299 view di GA4) tidak ada di sitemap; apakah perlu diindeks adalah keputusan
  produk.

### Runbook: menjalankan SSR di staging/produksi

Compose server (di luar repo) perlu satu service dari image yang sama, tanpa publish port:

```yaml
suaranetijen-ssr:
  image: azmifauzan/suaranetijen:latest
  command: php artisan inertia:start-ssr
  restart: unless-stopped
  env_file: .env
  mem_limit: 512m
  cpus: 0.5
  healthcheck:
    test: ["CMD", "php", "artisan", "inertia:check-ssr"]
    interval: 30s
    timeout: 5s
    retries: 3
```

`.env` di host utama: `INERTIA_SSR_URL=http://suaranetijen-ssr:13714`. Server SSR mendengarkan
`0.0.0.0`, jadi jangan memetakan port 13714 ke host. Urutan deploy: bangun dan push image, tarik di
host utama, recreate `suaranetijen-ssr` lebih dulu, lalu `suaranetijen-app` dan scheduler, lalu
`docker exec nginx-proxy nginx -s reload` (catatan di `CLAUDE.md`). Worker Horizon tidak membutuhkan
SSR. Setelah live, cek `curl -s https://suaranetijen.id/e/samsung | grep -o '<title[^>]*>[^<]*'` dan
`curl -I https://suaranetijen.id/e/abdul-kadir-karding | grep -i x-robots`.

## Target

| Metrik | Baseline | 1 bulan | 3 bulan |
|---|---|---|---|
| On-page score RankMySEO | 34 | >= 70 | >= 85 |
| Next actions SEO issues | 5 tipe, 587 issue | <= 2 tipe | 0 High |
| Halaman terindeks (GSC) | 78 | 120 | 250 |
| Crawled/Discovered - not indexed | 18 / 38 | < 10 / < 20 | < 5 / < 10 |
| Server error 5xx (GSC) | 6 | 0 | 0 |
| Impresi Search Console / 28 hari | 25 | 300 | 3.000 |
| Klik Search Console / 28 hari | 1 | 15 | 150 |
| Sesi organic search / 28 hari (GA4) | 10 (0,8%) | 50 | 400 (> 15% dari total) |
| Key event GA4 | 0 terdefinisi | 4 terdefinisi | tren naik |
| Keyword di top 20 (yang dilacak) | 0 | 1 | 3 |
| Referring domain | 1 | 5 | 15 |

## Risiko dan catatan data

- GitHub: 1.191 opini dengan skor 99,5. Kemungkinan kecocokan entitas yang salah (tautan GitHub di
  forum developer dihitung sebagai opini). Halaman dengan angka janggal merusak kepercayaan bila
  diindeks; audit dengan alur alias-blocklist yang sudah ada sebelum halaman ini dipromosikan.
- Label tema seperti "Samsung terbaik" berasal dari LLM. Tampil sebagai label frekuensi tidak
  melanggar aturan copy, tetapi jangan dipakai di title atau meta description.
- Tokoh Publik: 3 orang di sitemap (Prabowo Subianto, Joko Widodo, Nagita Slavina) dan sebagian besar
  impresi Search Console datang dari nama orang. Mengejar keyword orang adalah keputusan produk,
  bukan efek samping; default rencana ini tidak menargetkannya.
- SSR menambah satu proses Node yang bisa gagal. Fallback ke CSR tetap aman bagi pengguna, tetapi
  diam bagi SEO; itu alasan 0.3 dan 0.4 dibuat tidak bergantung pada SSR.
- Ketergantungan pada Threads: 64% sesi dari satu platform. Organik adalah lindung nilai, bukan sekadar
  kanal tambahan.
- Volume keyword hanya 4 titik data planner ditambah 100 keyword gap dari satu kompetitor. Ukur ulang dari Search Console 4-6 minggu
  setelah Fase 0-1 live, lalu sesuaikan klaster.

## Deploy 2 Oktober 2026

Image `azmifauzan/suaranetijen:latest` (digest `sha256:fc60635e…`) dibangun, diuji (bundle SSR dan
`inertia:check-ssr` di dalam image), dan didorong. Host utama: `suaranetijen-ssr` ditambahkan ke
compose (cadangan `docker-compose.yml.bak-*` dan `.env.bak-*`), `INERTIA_SSR_URL` ditambahkan ke
`.env`, SSR dijalankan lebih dulu sampai healthy, lalu app dan scheduler di-recreate, `nginx -s reload`.
Tidak ada migrasi. Tiga worker Horizon memakai image baru (`horizon:terminate`, lalu recreate) karena
hook `SentimentSnapshot` membersihkan cache sitemap dan harus berjalan di worker yang menyimpan snapshot;
semua supervisor `running`, 0 failed job. Hasil dari luar: title unik per halaman, canonical dan
`og:image` absolut, `X-Robots-Tag: noindex` pada entitas tipis, `/login`, dan `/search?q=`;
`/sitemap.xml` 0,6 s saat cache dingin dan 0,2 s saat panas.

## Fase 2 dan kartu share (6 Oktober 2026)

Dideploy ke produksi (`ecca529`; image `sha256:e484b14c…`; SSR, app, scheduler di-recreate; worker tidak
perlu karena tidak ada perubahan di sisi worker).

| Item | Hasil |
|---|---|
| JSON-LD | Sudah lengkap sebelum Fase 2: BreadcrumbList di 137 halaman, ItemList di 39, FAQPage di 80; semua valid dan absolut (crawl 144 URL sitemap). Tidak ada yang ditambah |
| Tautan internal | Crawl menemukan 4 halaman entitas tanpa tautan masuk (`samsung-galaxy-s24-ultra`, `iphone-17`, `toyota-yaris`, `yamaha-nmax-155`). `/category/{slug}` kini memuat tautan ke semua entitas yang lolos threshold; breadcrumb entitas menunjuk ke kategori (sama dengan JSON-LD); `/top/{slug}` menaut balik ke kategori |
| Kartu share per entitas | `/og/e/{slug}.png` (1200x630) untuk entitas yang lolos threshold: nama, kategori, skor Sentimen Netijen, jumlah opini, sebaran positif/netral/negatif. Dirender GD + DejaVu Sans (`resources/fonts/`, lisensi disertakan), di-cache di `storage/app/og` per versi snapshot, URL membawa `?v=`. Entitas tipis dan slug tak dikenal: 404, `og:image` tetap gambar situs. `twitter:card` menjadi `summary_large_image` |

Diverifikasi dari luar: `og:image` `/e/samsung` menunjuk ke kartu yang valid (PNG 1200x630, 0,56 s saat
dirender, 0,30 s saat di-cache), `/e/abdul-kadir-karding` dan slug palsu mengembalikan 404 untuk kartu.

Catatan:

- Render kartu pertama kali terjadi saat crawler atau platform sosial mengambilnya; file lama per slug
  dihapus saat versi berubah. `storage/app/og` tidak ikut cadangan dan tidak perlu.
- Pratinjau di Threads/Facebook belum diuji dengan debugger platform masing-masing; gunakan Sharing
  Debugger Facebook untuk memaksa ambil ulang `og:image` setelah deploy.
- Fase 3 (halaman perbandingan) tetap menunggu keputusan produk soal ADR-011.

## Tindakan akun (6 Oktober 2026)

| Item | Hasil |
|---|---|
| GSC: Validate fix 6 URL 5xx | Validasi dimulai 6 Okt 2026. Keenam URL dicek ulang 200 sebelum ditekan. Hasil muncul dalam beberapa hari |
| GSC: kirim ulang sitemap | Tidak perlu. `sitemap.xml` sudah terkirim (1 Okt), dibaca Google 4 Okt (setelah SSR live) dengan status Success dan 158 halaman ditemukan |
| GA4: tautkan Search Console | Selesai: `suaranetijen.id` (domain) ke stream SuaraNetijen, "Link created". Data query dan landing page organik baru muncul di GA4 setelah beberapa hari |
| GA4: key event | `sponsor_click` dan `view_search_results` ditandai (dua event yang memang dikirim aplikasi). `purchase` sudah ada tanpa data. Event kirim-rating belum dipancarkan aplikasi; belum ada yang bisa ditandai |
| RankMySEO: keyword | Sudah `review infinix`, `review iphone 16`, `vps biznet gio` (volume 113/170/149), semuanya 20+ (belum ada peringkat). Rank coverage kini 100% |
| RankMySEO: skor on-page | Masih 34 (142 halaman, 587 isu): review terakhir 1 Okt, sebelum SSR live. Tidak ada tombol untuk memicu review ulang; menunggu jadwal RankMySEO |

Mengukur dampak: bandingkan Search Console 28 hari (baseline 1 klik, 25 impresi, posisi 33,7), jumlah halaman terindeks
(78), "Crawled/Discovered - not indexed" (18/38), dan sesi organic search GA4 (10) setelah 4-6 minggu.

## Fase 3 dan event rating (6 Oktober 2026)

- **Keputusan ADR-011:** halaman perbandingan tidak melanggarnya. ADR-007/011 melarang *menggabungkan*
  metrik dan ADR-008 melarang skor aspek; menampilkan metrik yang sama untuk dua entitas berdampingan
  tidak termasuk. Dicatat sebagai ADR-012 di `docs/21`, termasuk batasannya: tanpa skor gabungan, tanpa
  selisih, tanpa pemenang, tanpa skor per tema, tanpa Rating Netijen.
- **Halaman:** `/banding/{a}-vs-{b}` (`ComparisonController`, `EntityComparison`, `Comparison/Show.vue`).
  Dua kartu berdampingan (skor, opini, sebaran, tema dipuji/dikeluhkan sebagai frekuensi), satu kalimat
  yang hanya membaca dua skor ("lebih tinggi daripada", atau "relatif setara" bila selisih < 5), FAQ, JSON-LD
  Breadcrumb dan FAQPage. Urutan dibalik di-301 ke urutan alfabet. Entitas tipis atau tak dikenal: 404.
- **Daftar kurasi:** `config/comparisons.php` berisi 15 pasangan yang punya bukti pencarian (autocomplete)
  dan entitas yang lolos threshold di produksi. Hanya pasangan itu yang indexable dan masuk sitemap; pasangan
  lain yang valid tetap tampil tetapi `noindex`, begitu pula beda kategori. Menambah pasangan = menambah
  satu baris, tanpa migrasi.
- **Tautan:** halaman entitas memuat "Bandingkan {nama} dengan yang lain" ke pasangan kurasinya yang sisi
  lainnya lolos threshold.
- **Event rating:** `submit_rating` dikirim ke GA4 saat rating berhasil disimpan (`resources/js/lib/analytics.ts`,
  tanpa efek bila gtag tidak dimuat). Tandai sebagai key event di GA4 setelah event pertama masuk.

Deploy 6 Oktober 2026 (digest `sha256:fdcfc158…`): SSR, app, scheduler di-recreate. Diverifikasi dari luar:
`/banding/oppo-vs-vivo`, `samsung-vs-xiaomi`, `mitsubishi-xpander-vs-toyota-avanza`, `apple-vs-samsung` 200, canonical
absolut, `index, follow`, kalimat pembacaan skor benar ("relatif setara" bila selisih < 5); pasangan tak dikenal 404;
15 pasangan di sitemap; `/e/oppo` menaut ke 5 perbandingan. Koreksi GA4: satu klik nyasar saat menandai key event sempat
menandai `click` sebagai key event; sudah dibatalkan, key event aktif hanya `sponsor_click` dan `view_search_results`
(`purchase` bawaan, tanpa data). `submit_rating` baru bisa ditandai setelah muncul di "Recent events" GA4 (setelah
rating pertama dikirim lewat situs).

## Audit GitHub, deskripsi entitas, Lighthouse (6 Oktober 2026)

**Audit entitas GitHub: data tercemar, sudah dibersihkan.** Dari 1.235 observasi GitHub, 1.197 (97%) berasal dari
`indoforum`: satu per thread, 1.197 thread berbeda dan tak berkaitan ("Windows Vista Community", "Cara Reschedule Tiket
Pesawat"), semuanya positif, tanpa `matched_term`. Itu 96% dari seluruh observasi IndoForum (1.243). Sumber lain tidak
menunjukkan pola ini (entitas teratas per sumber 9-23%). Penyebab yang paling masuk akal, mengikuti pelajaran Kaskus:
selektor `//main` dan `//body` di `IndoForumAdapter::extract()` membaca seluruh halaman sebagai satu opini, sehingga
teks tetap di setiap halaman cocok dengan satu entitas. Teks mentahnya sudah kedaluwarsa dan IndoForum sekarang hanya
mengembalikan halaman tantangan bot, jadi penyebab pastinya tidak bisa direproduksi; tes regresi memakai halaman tanpa
node post dan gagal sebelum perbaikan.

Tindakan: `//main` dan `//body` dihapus dari IndoForum; `entities:purge-mismatched-opinions` mendapat opsi `--source`;
1.197 observasi dihapus (dry run lebih dulu: 1.197, semuanya tak terverifikasi) dan agregat dibangun ulang;
`monitor:metrics` kini memberi peringatan bila satu entitas menguasai lebih dari 60% observasi satu sumber dalam 24 jam.
Hasil: GitHub turun dari 99,1 (1.221 opini, 365 hari) menjadi 24 opini 365 hari (di bawah ambang, tanpa skor) dan 38
opini seluruh waktu (skor 57,9). Halamannya kini `noindex` dan keluar dari sitemap. Tiga worker Horizon memakai adapter
baru. Adapter HTML lain (DiskusiWebHosting, LowEndTalk, MediaKonsumen, Mojok, SerayaMotor) masih punya fallback `//body`,
tetapi datanya tidak menunjukkan pencemaran; dibiarkan dan dicatat di `.ai/rules/adapters.md`.

**Deskripsi entitas:** 35 entitas yang lolos ambang tetapi kosong deskripsinya diisi satu kalimat pendek lewat migrasi
yang tidak menimpa suntingan admin, plus baris yang sama di `seed_entities.csv` (importer akan mengosongkannya lagi bila
tidak). Hanya yang saya yakin faktanya; yang ambigu dilewati (Fiesta, So Good, Vit, Ultra, Club). Dari 5.358 entitas aktif,
5.079 masih kosong, hampir semuanya di bawah ambang dan `noindex`.

**Temuan terkait data:** `website_url` banyak yang salah hasil pencarian Wikidata, dan tautannya tampil di halaman
entitas yang diindeks: Jago -> jagodina.org.rs, Benefit -> journals.ums.ac.id, Club -> atleticodemadrid.com,
Pixy -> allartenter.com, Honda Jazz -> rpmnews.com, Mazda -> mazda.com/ja. Belum diperbaiki.

**Lighthouse mobile (produksi, sebelum perbaikan a11y):**

| Halaman | Perf | A11y | Best practices | SEO | LCP | TTFB |
|---|---|---|---|---|---|---|
| `/` | 74 | 96 | 100 | 100 | 4,1 s | 3,3 s (dingin) |
| `/e/samsung` | 82 | 95 | 100 | 100 | 3,4 s | 1,0 s |
| `/top/smartphone` | 88 | 95 | 100 | 100 | 3,3 s | 0,8 s |
| `/banding/oppo-vs-vivo` | 89 | 96 | 100 | 100 | 3,4 s | 0,8 s |

SEO 100 di keempatnya; CLS 0. Performa dibatasi waktu respons server (TTFB 0,8-1,0 s, dan 3,3 s pada beranda saat cache
15 menitnya dingin), jadi cache halaman publik (0.9) tetap satu-satunya langkah performa yang berarti. Aksesibilitas gagal
di dua hal yang sama di semua halaman: teks muted `#69796c`/`#667861` di 4,2-4,4:1 (kini `#5d6e61`, 4,9:1 atau lebih) dan
`aria-label` tautan merek yang tidak diawali teks yang terlihat (kini "suaranetijen Beranda"). `Sponsor/Index.vue` masih
memakai `#788a7e` (3,7:1) dan `#687a6d`; tidak diubah karena halaman itu sedang dikerjakan.

Deploy hari ini juga membawa perbaikan tata letak halaman sponsor (kartu teaser dan tombol aksi top-3).

## Fase 4: paket outreach (siap kirim, belum dikirim)

Fase ini bergantung pada orang, bukan kode: tidak ada yang dikirim atas nama SuaraNetijen tanpa persetujuan. Yang
tersedia di bawah adalah bahan yang siap dipakai, dengan angka dari produksi pada 6 Oktober 2026.

**Angka yang boleh dikutip** (semua bisa dicek di situs):

| Klaim | Sumber |
|---|---|
| Smartphone dengan Sentimen Netijen tertinggi: Vivo 88 (280 opini), Oppo 85 (861), Realme 84 (117), Xiaomi 80 (1.208), Infinix 77 (223), Samsung 72 (2.116) | `/top/smartphone` |
| Mobil: Suzuki Ertiga Hybrid 78 (345 opini), Honda Brio 77 (123), Hyundai 76 (612), Daihatsu 76 (303), BYD 74 (656) | `/top/mobil` |
| Perbandingan berdampingan 15 pasangan (Oppo vs Vivo, Samsung vs Xiaomi, Apple vs Samsung, Avanza vs Xpander, ...) | `/banding/...` |
| 172 URL di sitemap; metodologi, sumber data, dan rumus skor terbuka | `/methodology`, `/sources` |

Hindari: "terbaik", "objektif", "akurat", dan klaim bahwa skor menunjukkan kualitas produk. Pakai "sentimen netizen
tertinggi" dan sebut jumlah opini; tiga metrik tidak digabung (ADR-007/011/012).

**Sudut berita yang punya data sendiri** (bukan sekadar promosi):
1. "Merek HP dengan sentimen netizen tertinggi bulan ini" dengan perubahan peringkat dari bulan lalu, ditaruh di
   `/topik` atau halaman laporan agar bisa ditautkan.
2. "Apa yang paling sering dikeluhkan netizen tentang {merek}": dari tema Top Suara Netijen, sebagai frekuensi.
3. Perbandingan pasangan yang sedang ramai (peluncuran baru), berbasis `/banding/...`.

**Sasaran, berurutan dari yang paling mungkin**:
- Direktori dan daftar: Product Hunt, daftar startup Indonesia (DailySocial startup list, StartupRanking), daftar
  "awesome" proyek open source Indonesia di GitHub (situs menyebut dirinya proyek open source; sertakan tautan repo).
- Komunitas tempat data ini relevan dan mengizinkan tautan: forum teknologi dan otomotif (bukan Reddit, yang melarang
  perayap dan API-nya komersial). Bagikan halaman entitas atau perbandingan, bukan beranda (beranda: 14 detik
  engagement).
- Media: DailySocial, Tech in Asia Indonesia, Kompas Tekno, detikInet, IDN Times Tech, Oto Detik/Otomotifnet untuk
  sudut mobil. Kirim satu paragraf, satu grafik, dan tautan ke halaman sumber.
- Tautan balik dari sponsor: Papan Sponsor sudah mengirim `rel="sponsored"`; tambahkan opsi "tampilkan kami di
  halaman produk Anda" hanya bila sponsor memintanya.

**Contoh pesan pitch (Indonesia):**

> Kami mengolah ribuan opini netizen tentang merek HP dan mobil di Indonesia menjadi skor sentimen yang bisa dicek
> siapa saja, tanpa iklan yang memengaruhi skor. Bulan ini: Vivo (88 dari 280 opini) dan Oppo (85 dari 861) ada di
> puncak, Samsung (72 dari 2.116 opini) paling banyak dibicarakan. Datanya terbuka di suaranetijen.id/top/smartphone
> dan metodenya di suaranetijen.id/methodology. Kalau berguna untuk liputan Anda, kami bisa kirim data lengkap per
> kategori.

**Target tiga bulan:** 15 domain perujuk (sekarang 1). Ukur di RankMySEO (Backlinks) dan GA4 (source/medium referral).

## Cache halaman publik dan Lighthouse ulang (6 Oktober 2026, sore)

**Latar:** TTFB halaman publik naik menjadi 1,1-1,6 s (beranda 1,7-2,9 s hangat, 5,2 s dingin) karena Postgres bersama
(batas 8 CPU, memori 961 MiB dari 1 GiB) dipakai penuh oleh pipeline ingestion (`MatchEntitiesJob`,
`ClassifySentimentJob`; load host 11,9). Crawler dan bot pratinjau menunggu itu.

**Perubahan (item 0.9):** `CachePublicPages` menyajikan HTML publik dari cache selama 5 menit hanya untuk permintaan
tanpa cookie, tanpa query string, bukan Inertia XHR, untuk rute publik (beranda, entitas, kategori, top, topik,
perbandingan, halaman statis). Berjalan sebelum session, jadi hit tidak mengirim cookie; permintaan ber-cookie tidak
pernah di-cache maupun dilayani dari cache, sehingga data per pengguna (rating, flash, tema) tidak bocor. Halaman
entitas bersponsor tidak di-cache (jumlah tayang sponsor), kunci memuat hash manifest Vite (deploy tidak menyajikan
HTML yang menunjuk aset yang sudah hilang), dan menyunting entitas, topik, atau kategori mengosongkan cache. Perubahan
skor dari pipeline tidak mengosongkannya; menunggu TTL.

**Hasil produksi (tanpa cookie):**

| Halaman | Sebelum | Hit cache |
|---|---|---|
| `/` | 1,7 s (dingin 5,2 s) | 0,20-0,24 s |
| `/e/samsung` | 1,1-1,5 s | 0,17-0,23 s |
| `/top/smartphone` | 0,8-1,1 s | 0,18-0,20 s |
| `/banding/oppo-vs-vivo` | 1,1 s | 0,19-0,30 s |

**Lighthouse mobile sesudah cache:** waktu dokumen akar turun dari 760-3.240 ms menjadi 90-350 ms. LCP tidak ikut turun
secara berarti: hasilnya bimodal pada halaman yang sama (`/top/smartphone`, tiga kali jalan dengan dokumen 120-220 ms:
skor 87, 87, 74, LCP 3,4 s, 3,4 s, 5,6 s), jadi LCP dibatasi render (CSS pemblokir render 26 KB, JS dan gtag sekitar
536 KiB) dan bukan server. Perbaikan LCP berikutnya ada di sisi front-end (memecah JS, memuat gtag setelah interaksi,
CSS kritis), dan belum dikerjakan.

**Aksesibilitas putaran kedua:** sisa kegagalan setelah putaran pertama adalah warna muted lain (`#6d7c61` 3,9:1,
`#66736c` 4,4:1, dan 19 varian hijau-abu-abu antara 2,4 dan 4,5:1) dan nama tautan merek (teks terlihat "suaranetijen."
memuat titik, jadi `aria-label` kini "suaranetijen. Beranda"). Semua warna muted itu kini `#5d6e61`; warna aksen dan
amber dibiarkan.

**Belum bisa dikerjakan dari sini (ekstensi browser tidak tersambung):** menandai `submit_rating` sebagai key event di
GA4 (event harus muncul dulu, atau dibuat lewat "New key event"), dan membaca ulang skor on-page RankMySEO.

**Deploy akhir (6 Oktober 2026, image `sha256:` terbaru dari `7cb905c`):** app, SSR, scheduler, dan tiga worker. Lighthouse
mobile setelah deploy: **aksesibilitas 100, best practices 100, SEO 100** pada `/`, `/e/samsung`, dan `/top/smartphone`
(sebelumnya aksesibilitas 95-96). Item `label-content-name-mismatch` masih muncul sebagai informasi tetapi tidak
memengaruhi skor. Hit cache tanpa cookie: 0,14-0,26 s.

## LCP: pengukuran yang benar (6 Oktober 2026)

**Koreksi atas catatan sebelumnya.** Saya menulis bahwa LCP "dibatasi render" (CSS pemblokir, JS, gtag) dan perlu kerja
front-end. Setelah ditelusuri lewat trace, itu salah dibaca: skor Lighthouse mobile (LCP 3,4 s, kadang 5,6 s pada halaman
yang sama) sebagian besar artefak pengukuran, bukan pengalaman pengguna.

Bukti:
- Pada run Lighthouse tanpa throttling, semua aset selesai diunduh pada 0,3-0,5 s dan thread utama menganggur, tetapi
  paint pertama baru terjadi pada 1,3 s (selisih tetap sekitar 1,15 s, tiga dari empat run). Filmstrip kosong pada
  375/750/1125 ms. Kontrol: example.com dengan alat dan komputer yang sama paint pada 0,1 s.
- Chrome yang sama, dikendalikan lewat puppeteer dengan emulasi mobile tanpa throttling jaringan, delapan kali muat:
  FCP = LCP 0,33-0,75 s, tanpa mutasi DOM (hidrasi tidak mengganti isi SSR), elemen LCP adalah paragraf SSR.
- Dengan kondisi yang sama seperti Lighthouse mobile (1,6 Mbps, RTT 150 ms, CPU 4x), enam kali muat halaman
  `/top/smartphone`: **FCP = LCP 1,41-1,72 s**, TTFB 147-493 ms, 502 KB dalam 32 permintaan, load 3,1-3,4 s. Batas
  "baik" Google untuk LCP adalah 2,5 s.

Kesimpulan: LCP nyata pengguna di jaringan lambat sekitar 1,5 s, di bawah batas 2,5 s. Angka 3,4-5,7 s di laporan
Lighthouse mensimulasikan dan melebih-lebihkan, dan melompat-lompat karena penundaan frame headless. Tidak ada kerja
front-end besar yang dibenarkan oleh data ini (menunda gtag, memecah JS, CSS kritis hanya akan mengubah TBT atau
ukuran, bukan LCP yang sudah sehat), jadi tidak dikerjakan.

**Yang dikerjakan:** aset ber-hash di `/build/assets` kini dikirim dengan
`Cache-Control: public, max-age=31536000, immutable` (konfigurasi Apache di Dockerfile; diuji pada image `php:8.5-apache`).
Sebelumnya hanya `max-age=14400` yang ditambahkan Cloudflare, sehingga pengunjung yang kembali setelah 4 jam
memvalidasi ulang 30-an berkas. Ini membantu kunjungan ulang dan mengurangi beban origin, bukan LCP kunjungan pertama.

**Untuk mengukur ke depan:** pakai data lapangan (Search Console, laporan Core Web Vitals, setelah ada cukup trafik) dan
skrip puppeteer dengan throttling di atas, bukan skor Lighthouse tunggal.

## Halaman daftar perbandingan (6 Oktober 2026)

`/banding` (`ComparisonController@index`, `Comparison/Index.vue`) mendaftar semua halaman perbandingan yang indexable,
dikelompokkan per kategori, dengan skor dan jumlah opini kedua sisi di tiap kartu, sehingga halaman `/banding/{a}-vs-{b}`
dapat dicapai dari situs dan bukan hanya dari hasil pencarian. Tautan "Perbandingan" ada di footer semua halaman publik
(kolom "Mulai di sini"), halaman itu masuk sitemap bila ada pasangan yang lolos, memuat JSON-LD Breadcrumb dan ItemList,
disajikan dari cache halaman, dan menjadi `noindex` bila daftarnya kosong. Daftar mengikuti `config/comparisons.php`
dan hanya menampilkan pasangan yang kedua sisinya lolos ambang 30 opini.

Juga diverifikasi di produksi: aset ber-hash dikirim origin dengan `immutable, max-age=31536000`; salinan lama di
Cloudflare masih membawa `max-age=14400` sampai kedaluwarsa (4 jam), URL baru sudah memakai header baru.

## Fase 4: sasaran yang sudah diverifikasi (6 Oktober 2026)

Pengecekan 6 Oktober memastikan cara mendaftar ke tiap sasaran dari halamannya sendiri. Belum ada yang dikirim.

| Sasaran | Cara mendaftar (terverifikasi) | Tautan | Catatan |
|---|---|---|---|
| `IndopenSource/awesome-indonesia` (juga tampil di indopensource.org/projects dan tokengratis.id/opensource) | Pull request yang menambah satu baris `"azmifauzan/suaranetijen"` ke `repos.json`, jalankan `make validate`; README dibuat otomatis dari API GitHub | repo GitHub | Syarat: open source Indonesia yang masih aktif di 2026, publik, bukan duplikat. Tag, deskripsi, lisensi, dan bintang diambil dari metadata GitHub, jadi deskripsi dan topik repo menentukan tampilannya |
| `maziyank/awesome-indonesia` | Ikuti `CONTRIBUTING.md` repo itu; daftar terurut menurut bintang, tanpa seksi per framework, terakhir disinkronkan 6 Okt 2026 | repo GitHub | Aktif |
| `rujukan/made-in-indonesia` | Fork, tambah satu baris di README pada urutan abjad: `(⭐ N) [suaranetijen](https://github.com/azmifauzan/suaranetijen) - "Indeks sentimen publik Indonesia" _by [azmifauzan](https://github.com/azmifauzan)_`, lalu PR | repo GitHub | Syarat: lisensi OSS (MIT, memenuhi) dan pembuat orang Indonesia |
| AppVerse.id | Alamat kontak `hello@appverse.id`; bagian GitHub Repos menerima URL publik tanpa login dan tampil setelah ditinjau admin. Formulir produknya tidak terbaca dari halaman publik | appverse.id | Gratis menurut halamannya; kebijakan dofollow tidak disebutkan |
| Product Hunt | Akun pribadi (akun perusahaan tidak bisa memposting), URL langsung ke halaman produk (tanpa tautan pendek atau pelacak), tagline maks 60 karakter, deskripsi maks 260, gambar, dan komentar pembuat | producthunt.com/launch | Satu peluncuran per produk; jadwalkan 12:01 PST. Tautan di Product Hunt biasanya nofollow, nilainya ada di sorotan dan rujukan |
| DailySocial dan media teknologi | Tidak ada formulir tip di halaman yang terbaca; kirim siaran pers ringkas ke alamat redaksi di halaman About masing-masing media | news.dailysocial.id/about | Subjek singkat, informatif, bukan klik-umpan; cantumkan nama, jabatan, telepon, email |

**Kesiapan repositori** (kini sebagian besar terpenuhi): publik, lisensi MIT, README diperbarui 6 Okt dengan tautan situs
langsung, contoh, dan stack. **Belum:** kolom "website" dan topik repositori di GitHub kosong. Daftar di atas menarik tag
dan deskripsi dari metadata itu. Perubahan yang disarankan: homepage `https://suaranetijen.id` dan topik
`sentiment-analysis`, `indonesia`, `laravel`, `inertiajs`, `vue`, `public-opinion`, `open-source`.

**Siap dikirim, menunggu persetujuan** (semuanya bertindak atas nama akun GitHub atau email Anda, jadi tidak saya
jalankan sendiri): (1) `gh repo edit` homepage dan topik; (2) tiga pull request ke daftar di atas; (3) surel ke
`hello@appverse.id`; (4) satu paragraf pitch ke redaksi media (teks ada di bagian paket outreach); (5) draf peluncuran
Product Hunt.

## Fase 4: status pengiriman (6 Oktober 2026)

| Sasaran | Status |
|---|---|
| Metadata repositori GitHub (homepage, topik, deskripsi) | Selesai, diisi pemilik repo |
| `IndopenSource/awesome-indonesia` | Pull request #50 terbuka, menunggu tinjauan |
| `maziyank/awesome-indonesia` | Dilewati: fork dari IndopenSource, tercakup PR #50 |
| `rujukan/made-in-indonesia` | Dilewati: tidak aktif sejak 2023 |
| AppVerse.id | Pemilik yang mendaftar sendiri; belum ada konfirmasi |
| Product Hunt | Draf dibuat dan dijadwalkan pemilik untuk 9 Oktober 2026. Tagline "Public sentiment index for brands in Indonesia", tag Analytics dan Open Source, komentar pembuat terisi, galeri 3 gambar (kartu share, halaman entitas Samsung, perbandingan Apple vs Samsung), disimpan 7 Okt; launch 9 Okt 2026 12:01 AM PDT |
| Media teknologi | Belum dikirim; menunggu pilihan media dan waktu dari pemilik |

## Pengukuran 7 Oktober 2026

- **GA4:** event `submit_rating` belum pernah masuk (daftar event 28 hari: `click`, `first_visit`, `form_start`, `page_view`, `scroll`, `session_start`, `sponsor_click`, `user_engagement`, `view_search_results`). GA4 baru menampilkan bintang key event setelah event pertama diterima, jadi penandaan menunggu rating asli dari pengguna. Key event saat ini: `sponsor_click`, `view_search_results`.
- **RankMySEO:** skor on-page masih 34 (142 halaman, 587 isu), dari audit 5 Oktober, sebelum perbaikan SSR, noindex, dan a11y. Tidak ada tombol audit ulang yang ditemukan; skor baru perlu re-crawl manual. Tiga keyword terlacak belum masuk 20 besar, domain authority 2, 3 backlink. Search Console 28 hari: 30 impresi, 0 klik, posisi rata-rata 51,2.
