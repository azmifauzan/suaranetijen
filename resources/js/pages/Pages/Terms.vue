<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Syarat & Ketentuan - SuaraNetijen">
        <meta
            name="description"
            content="Syarat dan ketentuan penggunaan platform SuaraNetijen, indeks opini publik Indonesia."
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
            <nav class="mb-6 flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                <Link href="/" class="hover:underline">Beranda</Link>
                <span>/</span>
                <span class="font-medium text-neutral-800 dark:text-neutral-200">Syarat & Ketentuan</span>
            </nav>

            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-10 dark:border-neutral-800 dark:bg-neutral-900">
                <h1 class="text-3xl font-black tracking-tight text-neutral-900 sm:text-4xl dark:text-neutral-100">
                    Syarat & Ketentuan Layanan
                </h1>
                <div class="mt-6 space-y-6 text-sm leading-relaxed text-neutral-700 dark:text-neutral-300">
                    <p>
                        Selamat datang di SuaraNetijen. Dengan mengakses dan menggunakan platform ini, Anda menyetujui ketentuan-ketentuan berikut:
                    </p>

                    <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">
                        1. Sifat Data dan Indeks Opini
                    </h2>
                    <p>
                        SuaraNetijen menyajikan skor sentimen publik yang dihasilkan melalui crawling terotomasi dan pemrosesan komputasi atas percakapan di ruang publik digital. Skor ini mencerminkan persepsi dan sentimen netijen, bukan rekomendasi profesional atau jaminan kualitas mutlak.
                    </p>

                    <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">
                        2. Larangan Manipulasi & Bot
                    </h2>
                    <p>
                        Pengguna dilarang melakukan upaya manipulasi rating (astroturfing, rating bombing, botting) pada fitur first-party Rating Netijen. Sistem kami dilengkapi deteksi anti-abuse terotomasi dan akan memblokir akun yang terindikasi melakukan penyalahgunaan.
                    </p>

                    <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">
                        3. Hak Kekayaan Intelektual
                    </h2>
                    <p>
                        Semua nama brand, merek dagang, dan logo yang ditampilkan di platform ini adalah milik dari pemilik sah masing-masing. Penggunaan nama entitas semata-mata bertujuan untuk kategorisasi dan analisis sentimen publik secara wajar (fair use).
                    </p>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="mt-16 border-t border-neutral-200 bg-white py-8 text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400">
            <div class="mx-auto flex max-w-5xl flex-col items-center justify-between gap-4 px-4 sm:flex-row sm:px-6">
                <div>© 2026 SuaraNetijen. Indeks Sentimen Publik Indonesia.</div>
                <div class="flex flex-wrap items-center gap-4">
                    <Link href="/search" class="hover:underline">Pencarian</Link>
                    <Link href="/methodology" class="hover:underline">Metodologi</Link>
                    <Link href="/sources" class="hover:underline">Sumber Data</Link>
                    <Link href="/about" class="hover:underline">Tentang Kami</Link>
                    <Link href="/terms" class="hover:underline font-semibold text-emerald-600">Ketentuan</Link>
                    <Link href="/privacy" class="hover:underline">Privasi</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
