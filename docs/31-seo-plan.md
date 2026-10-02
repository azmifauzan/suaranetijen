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
