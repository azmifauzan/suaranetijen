<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    scoring: {
        public_min_opinions: number;
        ranking_min_opinions: number;
        formula_version: string;
    };
}>();
</script>

<template>
    <Head title="Metodologi Skor & Transparansi - SuaraNetijen">
        <meta
            name="description"
            content="Pelajari metodologi penghitungan Sentimen Netijen, threshold opini publik, pemisahan Rating Netijen, dan prinsip crawler independen di SuaraNetijen."
        />
    </Head>

    <div class="min-h-screen bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <!-- Header -->
        <header class="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4 sm:px-6">
                <Link href="/" class="flex items-center gap-2 text-lg font-bold text-emerald-600 dark:text-emerald-400">
                    <span>SuaraNetijen</span>
                </Link>
                <div class="flex items-center gap-4 text-sm">
                    <Link href="/search" class="text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100">
                        Cari Entitas
                    </Link>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
            <!-- Breadcrumbs -->
            <nav class="mb-6 flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                <Link href="/" class="hover:underline">Beranda</Link>
                <span>/</span>
                <span class="font-medium text-neutral-800 dark:text-neutral-200">Metodologi</span>
            </nav>

            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-10 dark:border-neutral-800 dark:bg-neutral-900">
                <h1 class="text-3xl font-black tracking-tight text-neutral-900 sm:text-4xl dark:text-neutral-100">
                    Metodologi SuaraNetijen
                </h1>
                <p class="mt-3 text-base leading-relaxed text-neutral-600 dark:text-neutral-300">
                    SuaraNetijen adalah indeks sentimen publik independen untuk brand, produk, dan layanan di Indonesia. Kami mengumpulkan opini publik dari forum diskusi, komunitas, dan media terbuka, kemudian mengklasifikasikan sentimennya secara objektif tanpa intervensi komersial.
                </p>

                <div class="mt-8 space-y-8 text-neutral-700 dark:text-neutral-300">
                    <!-- Section: Tiga Metrik Utama -->
                    <section>
                        <h2 class="text-xl font-bold text-neutral-900 dark:text-neutral-100">
                            1. Tiga Metrik Terpisah (Tidak Pernah Digabung)
                        </h2>
                        <p class="mt-2 text-sm leading-relaxed">
                            Sesuai Architecture Decision Record (ADR-007 & ADR-011), ketiga metrik berikut dirawat secara independen dan tidak pernah dilebur menjadi skor gabungan tunggal:
                        </p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-3">
                            <div class="rounded-xl bg-neutral-50 p-4 dark:bg-neutral-800/60">
                                <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase">Sentimen Netijen</div>
                                <div class="mt-1 text-2xl font-black">0 - 100</div>
                                <div class="mt-1 text-xs text-neutral-500">Agregasi crawler opini publik pihak ketiga.</div>
                            </div>
                            <div class="rounded-xl bg-neutral-50 p-4 dark:bg-neutral-800/60">
                                <div class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase">Rating Netijen</div>
                                <div class="mt-1 text-2xl font-black">1.0 - 5.0 ★</div>
                                <div class="mt-1 text-xs text-neutral-500">Star rating terverifikasi dari pengguna SuaraNetijen.</div>
                            </div>
                            <div class="rounded-xl bg-neutral-50 p-4 dark:bg-neutral-800/60">
                                <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase">Top Suara Netijen</div>
                                <div class="mt-1 text-2xl font-black">Tema & Topik</div>
                                <div class="mt-1 text-xs text-neutral-500">Frekuensi tema/keluhan netijen (bukan skor angka).</div>
                            </div>
                        </div>
                    </section>

                    <!-- Section: Formula Skor -->
                    <section class="border-t border-neutral-100 pt-8 dark:border-neutral-800">
                        <h2 class="text-xl font-bold text-neutral-900 dark:text-neutral-100">
                            2. Formula Sentimen Netijen (v{{ scoring.formula_version }})
                        </h2>
                        <p class="mt-2 text-sm leading-relaxed">
                            Setiap opini relevan diklasifikasikan ke dalam <strong>Positif</strong> (nilai 100), <strong>Netral</strong> (nilai 50), atau <strong>Negatif</strong> (nilai 0). Rumus matematis agregat skor:
                        </p>
                        <div class="my-4 rounded-xl bg-neutral-100 p-4 font-mono text-sm text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200">
                            score = 100 * (positive_count + 0.5 * neutral_count) / total_opinions
                        </div>
                        <p class="text-xs text-neutral-500">
                            Contoh: Jika entitas memiliki 60 opini positif, 20 netral, dan 20 negatif (total 100 opini), maka skornya adalah: 100 * (60 + 10) / 100 = <strong>70.0 / 100</strong>.
                        </p>
                    </section>

                    <!-- Section: Batas Minimum Observasi -->
                    <section class="border-t border-neutral-100 pt-8 dark:border-neutral-800">
                        <h2 class="text-xl font-bold text-neutral-900 dark:text-neutral-100">
                            3. Batas Minimum Observasi (Thresholds)
                        </h2>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm">
                            <li>
                                <strong>Minimal {{ scoring.public_min_opinions }} Opini untuk Skor Publik:</strong>
                                Entitas dengan opini di bawah ambang batas ini menampilkan status "Belum Cukup Opini" dan tidak diindeks di mesin pencari (noindex) untuk mencegah kesimpulan prematur dari sampel kecil.
                            </li>
                            <li>
                                <strong>Minimal {{ scoring.ranking_min_opinions }} Opini untuk Ranking Kategori:</strong>
                                Untuk masuk ke daftar peringkat (Top List), entitas harus memiliki minimal 100 opini yang dianalisis dalam periode terkait.
                            </li>
                            <li>
                                <strong>Urutan Ranking:</strong>
                                Skor tertinggi (DESC), kemudian jumlah opini terbanyak (DESC), lalu nama alfabetis (ASC). Tidak ada bias popularitas maupun promosi berbayar.
                            </li>
                        </ul>
                    </section>

                    <!-- Section: Prinsip Crawler & Etika Data -->
                    <section class="border-t border-neutral-100 pt-8 dark:border-neutral-800">
                        <h2 class="text-xl font-bold text-neutral-900 dark:text-neutral-100">
                            4. Prinsip Crawler & Etika Data
                        </h2>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm">
                            <li><strong>Menilai Entitas, Bukan Individu:</strong> Kami tidak pernah menyimpan username, avatar, profil, nomor telepon, email, atau data pribadi penulis opini pihak ketiga.</li>
                            <li><strong>Precision over Recall:</strong> Jika penyebutan entitas ambigu atau tidak mengandung evaluasi pengalaman, opini tersebut tidak dimasukkan ke dalam skor sentimen.</li>
                            <li><strong>Raw Content Policy:</strong> Teks mentah hanya disimpan sementara dengan TTL pendek untuk pemrosesan, setelah itu dihapus otomatis. Yang disimpan permanen hanyalah hash untuk deduplikasi dan observasi agregat.</li>
                        </ul>
                    </section>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="mt-16 border-t border-neutral-200 bg-white py-8 text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400">
            <div class="mx-auto flex max-w-5xl flex-col items-center justify-between gap-4 px-4 sm:flex-row sm:px-6">
                <div>© 2026 SuaraNetijen. Indeks Sentimen Publik Indonesia.</div>
                <div class="flex flex-wrap items-center gap-4">
                    <Link href="/search" class="hover:underline">Pencarian</Link>
                    <Link href="/methodology" class="hover:underline font-semibold text-emerald-600">Metodologi</Link>
                    <Link href="/sources" class="hover:underline">Sumber Data</Link>
                    <Link href="/about" class="hover:underline">Tentang Kami</Link>
                    <Link href="/terms" class="hover:underline">Ketentuan</Link>
                    <Link href="/privacy" class="hover:underline">Privasi</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
