`/contact` belum ada di Google. Saya klik "Request indexing".

Request indexing sudah dikirim untuk dua URL, dan GSC menampilkan "Indexing requested" di keduanya.

- **`https://suaranetijen.id/contact`**: belum dikenal Google sama sekali ("URL is unknown to Google", tanpa sitemap atau referring page). Sekarang masuk antrean crawl prioritas.
- **`https://suaranetijen.id/about`**: sudah terindeks, tapi isinya berubah (ada bagian pengelola). Saya minta crawl ulang.

Yang tidak saya ajukan:
- **`/llms.txt`**: bukan halaman untuk indeks pencarian, dibaca langsung oleh crawler AI.
- **Halaman entitas**: footer dan link koreksinya akan ikut ter-crawl lewat jadwal normal. Sitemap juga sudah memuat `/contact`.

Hasil crawl biasanya terlihat dalam beberapa hari. Kuota request indexing GSC terbatas per hari, jadi sisanya biarkan lewat sitemap.