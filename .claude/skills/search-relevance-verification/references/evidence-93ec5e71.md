Band-Aid masuk lewat tier `category_context` yang lemah. Saya batasi saran ke tier exact/alias/prefix/trigram saja.

Hasil saran di Postgres staging bisa beda.** Pemeriksaan tingkat kecocokan saran saya jalankan di SQLite dengan pengganti fungsi trigram, padahal aturan proyek minta perubahan ranking dicek di Postgres asli.