# 28 - Topic Landing Pages (SEO)

Status: rencana, belum diimplementasikan (30 September 2026). Menggantikan draft
`28-advanced-search-plan.md`.

## Tujuan

Murni SEO: menangkap keyword long-tail ("vps murah", "hp baterai awet") dengan halaman terindeks
yang berisi data unik SuaraNetijen, tanpa menjadikan hasil search mentah sebagai halaman publik.

Keputusan yang sudah divalidasi:

| Keputusan | Pilihan |
|---|---|
| Prioritas | Traffic SEO dari Google, bukan UX search internal |
| Isi daftar | Rule dinamis: 1 kategori + N tema, diurutkan frekuensi tema |
| Sumber keyword | Antrian kandidat otomatis + draft LLM, admin review lalu publish |
| Query cocok dengan topik | Kartu di atas hasil `/search`, bukan redirect |
| Riwayat pencarian user | Tidak dibuat. Diganti hub publik `/topik` berisi topik terkurasi |

## Kenapa hasil search mentah tidak diindeks

`/search?q=` tetap `noindex, follow` (`resources/js/pages/Search/Index.vue`). Mengindeks query user
mentah berisiko:

- Pedoman Google: halaman hasil search internal = thin/auto-generated. Kualitas seluruh situs turun.
- Spam injection (judi online, link). Di Indonesia berisiko pemblokiran domain.
- Query tentang Tokoh Publik ("X korupsi") terbaca sebagai klaim situs (risiko UU ITE).
- Query bisa berisi nama, nomor HP, email (melanggar data minimization).
- Crawl budget habis untuk halaman sampah.

Pipeline topik mengubah query populer menjadi halaman terindeks lewat filter manusia.

## Batas scope

Masuk: tabel topik, generator kandidat, draft LLM, admin, halaman publik `/topik/{slug}`, hub
`/topik`, sitemap, internal link, kartu di `/search`.

Di luar scope, masing-masing punya siklus plan sendiri:

1. **Revamp homepage** (sub-proyek 2, setelah dokumen ini selesai): bagian selain leaderboard
   sponsor diganti menjadi blok per kategori (top 3 + link `/top` dan topik), blok "Yang sering
   dicari netizen" (topik published), hierarki H1/H2 berkeyword, JSON-LD `WebSite` + `SearchAction`
   dan `ItemList`. Bergantung pada topik yang sudah ada.
2. **Relevansi search** (sub-proyek 3): FTS (`simple` config, Postgres tidak punya `indonesian`),
   pencocokan tema dan deskripsi. Nilai SEO rendah karena `/search` noindex.
3. Riwayat pencarian per user: tidak dibuat (YAGNI, tanpa nilai SEO).

## Aturan yang tidak boleh dilanggar

- Tidak ada skor numerik per tema. Yang tampil hanya frekuensi ("N opini menyebut *murah*")
  (`docs/25`, ADR-007/011).
- Sentimen Netijen hanya tie-breaker setelah frekuensi tema (`docs/13`).
- Copy tanpa "terbaik"/"terburuk"/superlatif, persentase, handle, atau URL. Pakai "paling sering
  dibicarakan netizen". Ejaan SEO "netizen", nama metrik tetap "Netijen".
- Tidak ada `AggregateRating` di halaman topik.
- Kategori Tokoh Publik (dan turunannya) dikecualikan total dari topik.
- LLM hanya lewat `LlmClient` dengan model yang dikonfigurasi di `/admin/llm-settings`.
- Slug terkunci setelah publish. URL terindeks tidak boleh putus.

## Desain

### 1. Data

Tabel `search_landing_pages`:

| Kolom | Catatan |
|---|---|
| `slug` unique | URL `/topik/{slug}` |
| `keyword` | bentuk tampilan |
| `normalized_keyword` unique | `TextNormalizer::normalize()`, dipakai dedup dan kartu `/search` |
| `category_id` FK nullable | wajib saat publish, nullable untuk kandidat yang belum dipetakan |
| `title`, `meta_description`, `intro` | ditulis LLM (draft) atau admin |
| `status` | enum `candidate` / `draft` / `published` / `rejected` |
| `source` | enum `search_query` / `category_theme` / `manual` |
| `candidate_signal` int | jumlah sesi unik atau jumlah entitas, urutan antrian |
| `llm_drafted_at`, `published_at` | timestamp nullable |

Pivot `search_landing_page_themes (search_landing_page_id, theme_id)`, unique pasangan, cascade on
delete di kedua sisi.

`rejected` permanen: `normalized_keyword` unique lintas status, jadi kandidat yang ditolak tidak
pernah muncul lagi (pola yang sama dengan `entity_candidates`).

Lokasi kode: domain `app/Domains/Search` (model, service, controller, command).

### 2. Generator kandidat

Command `landing-pages:scan-candidates`, dijadwalkan mingguan di `routes/console.php`.

**Sumber `search_query`:**
- `search_queries` 30 hari terakhir, `result_count > 0`, dikelompokkan per `normalized_query`.
- Sinyal = `COUNT(DISTINCT COALESCE(user_id::text, session_id))`, ambang config (default 5).
- Dilewati kalau query sama persis dengan nama/alias entitas atau nama kategori.

**Sumber `category_theme`:**
- Pasangan kategori daun × tema dengan >= 5 entitas aktif (config) yang punya
  `entity_theme_snapshots.observation_count >= 3` di window `365d`.
- Keyword = "{nama kategori} {label tema}". Sinyal = jumlah entitas.

**Filter keamanan deterministik (sebelum LLM):**
- Blocklist kata di config (judi, slot, togel, gacor, casino, porn, dll).
- Regex nomor HP, email, URL.
- Kategori Tokoh Publik dikecualikan.

**Draft LLM** (`TopicDraftWriter`):
- Input: keyword, daftar kategori valid (tanpa Tokoh Publik), maksimal 30 tema kandidat yang
  disaring trigram terhadap token keyword, top entitas beserta hitungan temanya.
- Output JSON schema: `is_relevant`, `category_id` (harus dari input), `theme_ids` (subset input),
  `title` <= 60, `meta_description` <= 155, `intro` 2-3 paragraf.
- Validasi setelah respons: id di luar input ditolak, copy guard (lihat bawah), panjang.
- `is_relevant=false` → simpan sebagai `rejected` (tidak di-enrich ulang minggu depan).
- Respons tidak valid atau LLM gagal → simpan sebagai `candidate` tanpa draft, catat log.
- Batas panggilan LLM per scan (config, default 20). Satu sumber gagal tidak memblokir sumber lain.

**Copy guard:** ekstrak `EntityThemeSummarizer::isAllowedCopy()` menjadi satu kelas bersama
(misalnya `App\Domains\Themes\Services\PublicCopyGuard`) yang dipakai summarizer, draft writer,
dan validasi admin. Satu regex, satu tempat.

### 3. Halaman publik

**`/topik/{slug}`** (`TopicShowController`). Status selain `published` → 404.

- Entitas `active` + `searchable` di kategori topik, join `entity_theme_snapshots` window `365d`
  untuk tema topik, `SUM(observation_count)` sebagai `mention_count`. Kalau hasil 365d di bawah
  ambang index, pakai window `all`.
- Urutan: `mention_count` desc, skor sentimen (eligible) desc null terakhir, nama asc. Maksimal 20.
- Per baris: link `/e/{slug}`, "N opini menyebut {label tema}", Sentimen Netijen bila eligible,
  satu kutipan `theme_observations.context` (parafrase <= 200 karakter yang sudah tampil di
  halaman entitas).
- H1 = `title`, lalu `intro`, lalu "Diperbarui {tanggal}".
- Cache 1 jam, key id topik + `updated_at`.

**Ambang index:** kurang dari 3 entitas dengan `mention_count >= 3` → `noindex, follow` dan keluar
dari sitemap. Halaman tetap live. Admin melihat badge "belum layak index". Nilai ambang di config.

**Structured data:** JSON-LD `ItemList` (URL entitas) + `BreadcrumbList` (Beranda › Topik ›
Kategori). `canonical` = URL sendiri. Tanpa `AggregateRating`.

**Hub `/topik`:** topik published dan layak index, dikelompokkan per kategori induk. Terindeks.

**Sitemap:** `SitemapController` menambahkan `/topik` dan setiap topik yang layak index,
`lastmod` = `calculated_at` snapshot terbaru.

**Internal link:**
- `/category/{slug}` dan `/top/{slug}`: blok "Topik terkait" (<= 6 topik di kategori itu).
- `/e/{slug}`: blok "Masuk dalam topik" (<= 4 topik yang mencantumkan entitas ini).
- Footer publik: link `/topik`.

**Kartu `/search`:** `SearchPageController` mencari topik published dengan `normalized_keyword`
sama persis dengan query. Kalau ada, kartu "Lihat daftar: {title}" tampil di atas hasil. Exact
match saja.

### 4. Admin

`/admin/topics`, masuk grup nav Admin di `AppSidebar.vue`, di balik gate `access-admin`.

- Tab per status: Kandidat (urut `candidate_signal`), Draft, Published, Ditolak.
- Form: keyword, slug (otomatis, terkunci setelah publish), kategori, picker tema (pencarian async,
  hanya tema yang punya snapshot di kategori terpilih), title/meta dengan penghitung karakter,
  intro.
- Preview: jumlah entitas yang tampil dan status layak index.
- Aksi: Simpan draft, Publish, Tolak, Unpublish (kembali ke draft, URL jadi 404), Regenerate draft
  LLM, Buat manual.
- Validasi publish: title, meta, intro wajib; >= 1 tema; kategori bukan Tokoh Publik; lolos copy
  guard (error validasi, bukan peringatan).
- Feedback via `Inertia::flash('toast', ...)`, bukan `->with('success')`.

## Rencana eksekusi

Setiap task: test dulu (Pest, `Http::fake` untuk LLM, tanpa jaringan), lalu implementasi, lalu
`vendor/bin/pint --dirty --format agent`, commit.

### Task 1: Skema dan model
- Migration `search_landing_pages` + pivot tema, model `SearchLandingPage` (enum status/source,
  relasi `category`, `themes`), factory dengan state per status.
- Test: unique `normalized_keyword`, cascade pivot saat tema dihapus.

### Task 2: Copy guard bersama
- Ekstrak `PublicCopyGuard` dari `EntityThemeSummarizer`, summarizer memakai kelas baru.
- Test: superlatif, persen, handle, URL ditolak; teks normal lolos; test summarizer tetap hijau.

### Task 3: Query daftar entitas topik
- Service `TopicEntityList` (satu method: entitas + mention_count + skor + kutipan, plus flag
  layak index).
- Test: urutan frekuensi → skor → nama, fallback `365d` → `all`, entitas non-aktif/non-searchable
  keluar, ambang index, tidak ada skor per tema di output.

### Task 4: Halaman publik, hub, sitemap
- `TopicShowController`, `TopicIndexController`, route `/topik` dan `/topik/{slug}`, Vue
  `Topics/Show.vue` dan `Topics/Index.vue` (daftarkan di pengecualian layout `resources/js/app.ts`
  seperti `Sponsor/*`), JSON-LD, robots meta, entri sitemap, link footer.
- Test: draft/rejected 404, `noindex` di bawah ambang, tanpa `AggregateRating`, sitemap memuat
  hanya topik layak index, hub hanya topik published layak index.

### Task 5: Generator kandidat (tanpa LLM)
- Command `landing-pages:scan-candidates` dengan dua sumber + filter keamanan + dedup, jadwal
  mingguan, konfigurasi di `config/landing_pages.php`.
- Test: 1 sesi × 100 query = sinyal 1, ambang sesi, blocklist, HP/email/URL, query = nama
  entitas/kategori dilewati, Tokoh Publik dikecualikan, dedup lintas status (rejected tidak muncul
  lagi), satu sumber melempar exception sumber lain tetap jalan.

### Task 6: Draft LLM
- `TopicDraftWriter` lewat `LlmClient`, dipanggil dari scan dan dari aksi Regenerate.
- Test: `is_relevant=false` → rejected, `category_id`/`theme_ids` di luar input ditolak, copy
  melanggar guard dibuang, LLM error → candidate tanpa draft, batas panggilan per scan.

### Task 7: Admin
- `AdminTopicsController` (index per tab, edit/update, publish, unpublish, reject, regenerate,
  create manual, endpoint pencarian tema), Vue `Admin/Topics/*`, nav sidebar.
- Test: hanya admin, validasi publish, slug terkunci setelah publish, Tokoh Publik ditolak, copy
  guard jadi error validasi, toast flash.

### Task 8: Internal link dan kartu search
- Blok "Topik terkait" di kategori/top, "Masuk dalam topik" di entitas, kartu exact match di
  `/search`.
- Test: blok hanya berisi topik published, batas jumlah, kartu muncul hanya pada exact match.

### Task 9: Verifikasi live
- Jalankan scan di staging, review kandidat di admin, publish 3-5 topik.
- Cek: halaman 200, JSON-LD valid (Rich Results Test), topik muncul di `/sitemap.xml`,
  submit sitemap di Search Console.

## Titik rawan untuk review

1. Keyword yang dinormalisasi jadi string kosong atau sangat pendek (1 karakter) masuk antrian.
   Harapan: dilewati.
2. Kategori topik dinonaktifkan atau entitasnya jadi disabled setelah publish. Harapan: halaman
   tetap jalan, jatuh ke `noindex` kalau di bawah ambang, tidak error 500.
3. Dua kandidat dengan slug hasil generate yang sama tapi keyword normalisasi berbeda. Harapan:
   slug diberi sufiks, tidak melempar unique violation.
4. LLM mengembalikan intro berisi angka yang tidak ada di input. Harapan: admin tetap harus
   membaca sebelum publish (guard hanya menangkap pola, bukan fakta). Status draft tidak pernah
   publik.
5. Tema digabung oleh `themes:consolidate` sehingga id lama hilang. Harapan: pivot cascade, admin
   melihat topik dengan 0 tema, halaman jatuh ke `noindex`.
