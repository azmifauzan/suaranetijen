# 30 - Search Relevance: Pencarian Seluruh Data

Status: rencana, belum diimplementasikan (30 September 2026). Sub-proyek 3 dari rangkaian
`docs/28` → `docs/29` → `docs/30`.

## Tujuan

User bisa mengetik keyword seperti di mesin pencari ("vps murah", "hp baterai awet",
"hp snapdragon") dan mendapat daftar entitas yang relevan. Pencarian mencakup seluruh data turunan
entitas, tidak hanya nama.

Nilai SEO langsung rendah (`/search` tetap `noindex`), tapi sub-proyek ini memperbaiki UX dan
memberi sinyal lebih bersih ke generator kandidat topik (`docs/28`): query yang sekarang
menghasilkan 0 hasil jadi punya hasil, sehingga lolos filter `result_count > 0`.

## Masalah sekarang

`SearchService::queryCandidates()` mewajibkan **setiap token** query cocok dengan nama, alias,
kategori, atau entitas induk/anak. Contoh: "vps murah" menghasilkan 0 hasil karena "murah" tidak
ada di nama entitas mana pun. Deskripsi, tema, spec, dan ringkasan tidak pernah dicari.

## Keputusan yang sudah divalidasi

| Keputusan | Pilihan |
|---|---|
| Token deskriptif ("murah") | Soft match: token yang cocok ke nama/kategori menjadi filter, sisanya menjadi booster ranking |
| Field yang diindeks | Nama, alias, kategori (sudah ada) + deskripsi, label tema, spec, Ringkasan Suara Netijen |

## Aturan yang tidak boleh dilanggar

- Urutan relevansi `docs/13` tetap: exact name > exact alias > prefix > trigram > konteks.
  Kecocokan field baru berada di bawah kecocokan nama, dan sentimen hanya tie-breaker kecil paling
  akhir.
- Kecocokan tema adalah relevansi, bukan pujian. Hasil tidak boleh menampilkan skor per tema.
- Tidak ada teks opini mentah di indeks (raw payload punya TTL).
- Entitas disabled/non-searchable tidak pernah muncul (perilaku sekarang dipertahankan).
- Autocomplete (`/api/search`) dan halaman `/search` memakai service yang sama. Perilakunya harus
  konsisten.

## Desain

### 1. Dokumen pencarian per entitas

Tabel `entity_search_documents` (satu baris per entitas, `entity_id` unique), berisi teks yang
sudah dinormalisasi (`TextNormalizer`) per field:

| Kolom | Isi | Bobot |
|---|---|---|
| `description_text` | deskripsi entitas | rendah |
| `theme_text` | label tema snapshot 365d (fallback `all`) | tinggi |
| `spec_text` | nilai spec smartphone/mobil/motor/tokoh | sedang |
| `summary_text` | Ringkasan Suara Netijen | rendah |

Setiap kolom punya indeks GIN `gin_trgm_ops` (ekstensi `pg_trgm` sudah terpasang).

**Kenapa bukan `tsvector` FTS:** Postgres tidak punya konfigurasi `indonesian`. Dengan config
`simple`, FTS hanya memecah token tanpa stemming, jadi hasilnya setara pencocokan token biasa.
Pencocokan token awal-kata (`' ' || kolom LIKE '% token%'`) di atas indeks trigram memberi hasil
yang sama, berjalan identik di test SQLite (shim yang sudah ada), dan tidak perlu path SQL
terpisah. Ini menutup gap ADR-004 secara fungsional. **Keputusan ini perlu dicatat sebagai
amandemen ADR-004 di `docs/21`.**

**Tema berpolaritas negasi:** label tema yang mengandung penanda negasi (tidak, gak, nggak,
kurang, bukan) tidak dimasukkan ke `theme_text`. Tanpa ini, "vps murah" akan menaikkan entitas
yang sering disebut "tidak murah".

**Pembaruan dokumen:**
- Job `RefreshEntitySearchDocumentJob` (queue `aggregate`), dipicu saat entitas, alias, atau spec
  disimpan, dan setelah snapshot tema atau ringkasan entitas diperbarui.
- Command `search:rebuild-documents` untuk rebuild penuh, dijalankan sekali saat deploy dan
  dijadwalkan harian sebagai jaring pengaman.

### 2. Klasifikasi token

Query dinormalisasi lalu dipecah jadi token:

1. **Stopword** dibuang, dari daftar di config: yang, dan, di, untuk, dengan, paling, terbaik,
   bagus, rekomendasi, angka tahun. "Terbaik"/"bagus" dibuang karena penilaian itu sudah menjadi
   tugas Sentimen Netijen, bukan tugas pencocokan teks. Kata sifat konkret seperti "murah",
   "awet", "cepat" bukan stopword, karena justru itu yang dicocokkan ke tema.
2. **Anchor**: token yang cocok ke nama, alias, kategori, atau entitas induk/anak (kondisi token
   yang sekarang ada di `$tokenConditions`).
3. **Deskriptor**: token sisanya.

Perilaku:

| Kasus | Filter kandidat | Ranking |
|---|---|---|
| Ada anchor + deskriptor ("vps murah") | Semua anchor wajib cocok (perilaku sekarang) | Skor sekarang + skor deskriptor |
| Hanya anchor ("vps biznet") | Tidak berubah | Tidak berubah |
| Hanya deskriptor ("baterai awet") | Minimal satu deskriptor cocok di field dokumen | Skor deskriptor |
| Kosong setelah stopword | Browse (perilaku sekarang) | Nama |

### 3. Skor deskriptor

Per token deskriptor, jumlahkan bobot field yang memuat token itu (tema > spec > deskripsi =
ringkasan). Kecocokan tema dikalikan faktor kecil berdasarkan `log(1 + observation_count)` tema
tersebut, supaya entitas yang paling sering disebut "murah" naik ke atas.

Skala skor deskriptor dibatasi di bawah 4000 (skor konteks induk), sehingga tidak pernah
mengalahkan kecocokan nama, alias, atau prefix. Sentimen tetap tie-breaker terakhir.

Hasil membawa field baru `matched_fields` (misalnya `["theme:harga murah"]`) supaya kartu hasil
bisa menampilkan alasan singkat: "Sering disebut: harga murah". Hanya label, tanpa angka.

### 4. Pencatatan

`search_queries.result_count` tetap dicatat seperti sekarang. Tidak ada kolom baru.

## Rencana eksekusi

Setiap task: test dulu, implementasi, `vendor/bin/pint --dirty --format agent`, commit. Test
ranking juga diverifikasi terhadap Postgres live, karena shim trigram SQLite bukan bukti urutan
(catatan di CLAUDE.md).

### Task 1: Tabel dan builder dokumen
- Migration `entity_search_documents` + indeks trigram (khusus pgsql, seperti migration trigram
  yang sudah ada), model, builder yang menyusun keempat kolom dari entitas.
- Test: setiap field terisi dari sumber yang benar, label tema bernegasi dibuang, entitas tanpa
  spec/ringkasan menghasilkan kolom kosong (bukan error), teks dinormalisasi.

### Task 2: Pemicu pembaruan
- `RefreshEntitySearchDocumentJob`, dipanggil dari penyimpanan entitas/alias/spec, refresh
  snapshot tema, dan ringkasan. Command `search:rebuild-documents` + jadwal harian.
- Test: mengubah deskripsi, menambah alias, dan refresh snapshot tema masing-masing memperbarui
  dokumen; job idempoten (dijalankan dua kali hasilnya sama).

### Task 3: Klasifikasi token dan filter kandidat
- Pecah token menjadi stopword/anchor/deskriptor di `SearchService`; filter kandidat mengikuti
  tabel kasus di atas.
- Test: "vps murah" menghasilkan VPS (sebelumnya 0), "vps biznet" tidak berubah, "baterai awet"
  menghasilkan entitas dengan tema itu, query yang semuanya stopword jatuh ke browse,
  exact name tetap peringkat 1.

### Task 4: Skor deskriptor dan `matched_fields`
- Tambah skor deskriptor ke ORDER BY, batas di bawah skor konteks, `matched_fields` di output.
- Test: entitas dengan observation_count tema lebih tinggi di atas, kecocokan nama selalu di atas
  kecocokan tema, sentimen hanya memutus seri, `matched_fields` tanpa angka.

### Task 5: UI hasil
- `Search/Index.vue` dan autocomplete menampilkan "Sering disebut: {label}" dari `matched_fields`.
- Test: feature test props; cek visual di 360px.

### Task 6: ADR dan verifikasi live
- Tambah amandemen ADR-004 di `docs/21` (token trigram menggantikan `tsvector`, alasan di atas).
- Rebuild dokumen di staging, uji manual 10 query nyata dari `search_queries` yang sebelumnya
  0 hasil, cek waktu respons `/api/search` tetap < 300 ms.

## Titik rawan untuk review

1. Token pendek ("hp", "ac") cocok di tengah kata lain di spec atau deskripsi ("chip"). Harapan:
   pencocokan awal-kata saja, dan token < 2 karakter diabaikan.
2. Kata yang sekaligus anchor dan deskriptor ("cepat" sebagai nama entitas dan tema). Harapan:
   diperlakukan sebagai anchor (perilaku lebih ketat), dan test mengunci pilihan ini.
3. Entitas baru yang belum punya dokumen (job belum jalan). Harapan: tetap bisa dicari lewat
   nama/alias. Dokumen yang tidak ada = skor deskriptor 0, bukan hilang dari hasil.
4. Tema digabung oleh `themes:consolidate`. Harapan: dokumen diperbarui lewat refresh snapshot,
   atau paling lambat pada rebuild harian.
5. Query panjang (10+ token) dari bot. Harapan: token dibatasi (misalnya 8 pertama) supaya SQL
   tidak membengkak.
