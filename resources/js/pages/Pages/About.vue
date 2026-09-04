<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Tentang SuaraNetijen - Indeks Sentimen Publik Indonesia">
        <meta
            name="description"
            content="Mengenal SuaraNetijen, platform indeks opini dan sentimen publik berbasis agregasi crawler untuk brand, produk, dan layanan di Indonesia."
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
                <span class="font-medium text-neutral-800 dark:text-neutral-200">Tentang Kami</span>
            </nav>

            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-10 dark:border-neutral-800 dark:bg-neutral-900">
                <h1 class="text-3xl font-black tracking-tight text-neutral-900 sm:text-4xl dark:text-neutral-100">
                    Tentang SuaraNetijen
                </h1>
                <div class="mt-6 space-y-6 text-base leading-relaxed text-neutral-700 dark:text-neutral-300">
                    <p>
                        SuaraNetijen hadir untuk menjawab pertanyaan mendasar setiap konsumen di Indonesia: <em>"Apa sebenarnya kata netijen tentang produk atau layanan ini?"</em>
                    </p>
                    <p>
                        Di era ulasan sponsor dan review berbayar yang membingungkan, menemukan opini nyata dari sesama pengguna menjadi semakin sulit. SuaraNetijen mengumpulkan percakapan dan pengalaman riil dari ribuan thread komunitas, forum diskusi, dan media terbuka, lalu merangkumnya menjadi indikator sentimen yang jernih, transparan, dan dapat diverifikasi.
                    </p>

                    <h2 class="pt-4 text-xl font-bold text-neutral-900 dark:text-neutral-100">
                        Pilar Utama Kami
                    </h2>
                    <ul class="list-disc space-y-2 pl-5 text-sm">
                        <li><strong>Independen:</strong> Tidak menerima bayaran untuk memanipulasi, menyembunyikan, atau menaikkan skor sentimen brand apapun.</li>
                        <li><strong>Berbasis Data Agregasi:</strong> Skor dihasilkan melalui model pemrosesan bahasa alami (NLP) yang konsisten, deterministik, dan bebas bias subjektif.</li>
                        <li><strong>Dua Sisi yang Adil:</strong> Kami menampilkan apa yang disukai netijen sekaligus apa yang paling sering dikeluhkan, memberikan gambaran utuh sebelum Anda memutuskan membeli atau menggunakan layanan.</li>
                    </ul>
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
                    <Link href="/about" class="hover:underline font-semibold text-emerald-600">Tentang Kami</Link>
                    <Link href="/terms" class="hover:underline">Ketentuan</Link>
                    <Link href="/privacy" class="hover:underline">Privasi</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
