# 29 - Homepage SEO Revamp

Status: rencana, belum diimplementasikan (30 September 2026). Sub-proyek 2 dari rangkaian
`docs/28` (topik) → `docs/29` (homepage) → `docs/30` (relevansi search).

## Tujuan

Homepage adalah halaman dengan otoritas tertinggi. Tugasnya: menyebarkan link equity ke halaman
yang ingin diindeks (kategori, `/top`, topik, entitas) dan memberi Google teks berkeyword yang
unik dan segar. Leaderboard sponsor tidak diubah.

## Keputusan yang sudah divalidasi

| Keputusan | Pilihan |
|---|---|
| Blok kategori | 11 kategori induk |
| Top 6 sentimen tertinggi (lintas kategori) | Dihapus, digantikan top 3 per kategori induk |
| Entitas baru diperbarui | Dihapus. Freshness datang dari blok kategori dan topik |
| Penjelasan 3 metrik | Dipertahankan, diringkas |
| Hero, search, suggestion, podium + leaderboard sponsor #1-10, CTA sumber data | Tidak diubah |

## Bergantung pada

- `docs/28` (topik) untuk blok "Yang sering dibicarakan netizen" dan link topik per kategori.
  Tanpa topik published, kedua bagian itu disembunyikan, jadi homepage tetap bisa dirilis lebih
  dulu.

## Aturan yang tidak boleh dilanggar

- Copy ranking: "sentimen netizen tertinggi", tidak pernah "terbaik".
- Skor hanya tampil kalau eligible (>= `scoring.public_min_opinions`).
- Tidak ada `AggregateRating` untuk Sentimen Netijen.
- Blok kategori tidak boleh berisi daftar kosong atau dipaksa penuh. Kurang data = tampilkan link
  kategori anak saja.
- Sponsor tetap pita terpisah berlabel, tidak mempengaruhi urutan blok kategori (`docs/26`).

## Desain

### Susunan halaman

1. **Hero** (tetap). H1 diperkaya keyword: "Sentimen netizen tentang brand, produk, dan layanan di
   Indonesia". Search + suggestion tidak berubah.
2. **Podium sponsor #1-3 dan leaderboard #4-10** (tetap).
3. **Jelajahi per kategori** (baru, menggantikan grid nama kategori). Satu blok per kategori
   induk:
   - H2: "{Kategori} menurut netizen".
   - Top 3 entitas dari seluruh kategori anak, urut skor sentimen desc, `opinion_count` desc, nama
     asc (sama dengan `SentimentRankingService`), window 365d, hanya eligible. Per baris: nama →
     `/e/{slug}`, skor, jumlah opini, nama kategori anak.
   - Link ke setiap kategori anak (`/category/{slug}`) dan `/top/{slug}`.
   - Maksimal 3 link topik published di kategori itu ("vps murah", dst).
   - Kurang dari 1 entitas eligible: tanpa daftar, hanya link kategori anak.
   - **Tokoh Publik**: hanya link kategori anak, tanpa daftar top 3. Menampilkan "politisi
     dengan sentimen tertinggi" di halaman depan terlalu berisiko untuk nilai SEO yang didapat.
     Halaman `/top/{slug}` mereka tetap ada.
   - Mobile: blok satu kolom. Desktop: grid 2-3 kolom.
4. **Yang sering dibicarakan netizen** (baru). Maksimal 12 topik published dan layak index
   (`docs/28`), urut `candidate_signal`, link ke `/topik` di akhir. Disembunyikan kalau kosong.
5. **Tiga metrik** (diringkas). Satu baris per metrik (Sentimen Netijen, Top Suara Netijen,
   Rating Netijen) + link `/methodology`.
6. **CTA sumber data** (tetap).

### Data dan performa

- Pindahkan closure `/` di `routes/web.php` ke `HomePageController` (closure sudah terlalu besar,
  dan query top 3 per induk menambah logika).
- Top 3 per kategori induk dengan satu query: `ROW_NUMBER() OVER (PARTITION BY induk ORDER BY
  score desc, opinion_count desc, name asc)`, filter `<= 3`. Bukan 11 query.
- Query `topEntities` dan `recentEntities` dihapus.
- Hasil blok kategori di-cache 15 menit (data snapshot berubah harian, bukan per detik).

### SEO

- `<title>` dan meta description berkeyword, ejaan "netizen".
- JSON-LD `WebSite` (name, url) + `Organization` (name, url, logo). **Tanpa `SearchAction`**:
  Google menghentikan sitelinks search box pada November 2024, jadi markup itu tidak lagi
  berpengaruh.
- Tanpa `ItemList` di homepage: Google hanya memakainya untuk carousel tipe tertentu, dan halaman
  campuran seperti homepage tidak memenuhi syarat.
- Heading berurutan: satu H1, H2 per bagian, H3 per kategori induk tidak dipakai (H2 cukup).

## Rencana eksekusi

Setiap task: test dulu, implementasi, `vendor/bin/pint --dirty --format agent`, commit.

### Task 1: Controller dan data blok kategori
- `HomePageController` menggantikan closure, dengan props yang sama dikurangi `topEntities` dan
  `recentEntities`, ditambah `categoryBlocks` dan `popularTopics`.
- Service kecil untuk top 3 per induk (satu query window function), cache 15 menit.
- Test: top 3 per induk dari kategori anak, urutan skor → opini → nama, non-eligible tidak masuk,
  entitas disabled/non-searchable keluar, induk tanpa data → daftar kosong tapi link anak tetap,
  Tokoh Publik tanpa daftar, jumlah query tetap (tidak N+1).

### Task 2: Topik di homepage
- Prop `popularTopics` (maksimal 12) dan maksimal 3 topik per blok kategori, dari model
  `SearchLandingPage` (`docs/28`). Kalau `docs/28` belum rilis, task ini ditunda.
- Test: hanya topik published dan layak index, batas jumlah, bagian hilang kalau kosong.

### Task 3: Vue homepage
- `Welcome.vue`: hapus bagian top 6 dan baru diperbarui, ganti grid kategori dengan blok per
  induk, tambah bagian topik, ringkas bagian metrik, perbarui H1/title/meta, tambah JSON-LD.
- Pertimbangkan memecah blok kategori ke komponen `CategoryBlock.vue` (file sekarang 885 baris).
- Test: feature test props homepage; cek visual di browser 360px dan desktop.

### Task 4: Verifikasi live
- Rich Results Test untuk `WebSite`/`Organization`.
- Lighthouse SEO + performa mobile di staging.
- Search Console: request indexing homepage setelah deploy.

## Titik rawan untuk review

1. Entitas masuk top 3 di dua blok karena kategori anaknya punya dua induk. Harapan: tidak
   mungkin (satu induk per kategori), tapi test harus membuktikan tidak ada duplikat.
2. Kategori induk tanpa kategori anak (entitas langsung di induk). Harapan: entitas induk itu
   sendiri ikut dihitung.
3. Cache blok kategori basi setelah entitas di-disable admin. Harapan: maksimal 15 menit,
   dapat diterima. Entitas disabled akan 404 kalau diklik, jadi pertimbangkan invalidasi cache
   saat status entitas berubah.
4. Semua skor eligible di satu induk sama. Harapan: urutan deterministik (opini lalu nama).
5. Homepage tanpa sponsor dan tanpa topik (situs baru). Harapan: halaman tetap utuh, tanpa
   bagian kosong yang terlihat rusak.
