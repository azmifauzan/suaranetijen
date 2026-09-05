<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <PublicLayout>
        <Head title="Syarat & Ketentuan - SuaraNetijen">
            <meta
                name="description"
                content="Syarat dan ketentuan penggunaan platform SuaraNetijen, indeks opini publik Indonesia."
            />
        </Head>

        <!-- Main Content -->
        <main class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
            <nav class="mb-6 flex items-center gap-2 text-xs text-neutral-500">
                <Link :href="home()" class="hover:underline">Beranda</Link>
                <span>/</span>
                <span class="font-medium text-neutral-800"
                    >Syarat & Ketentuan</span
                >
            </nav>

            <div
                class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-10"
            >
                <h1
                    class="text-3xl font-black tracking-tight text-neutral-900 sm:text-4xl"
                >
                    Syarat & Ketentuan Layanan
                </h1>
                <div
                    class="mt-6 space-y-6 text-sm leading-relaxed text-neutral-700"
                >
                    <p>
                        Selamat datang di SuaraNetijen. Dengan mengakses dan
                        menggunakan platform ini, Anda menyetujui
                        ketentuan-ketentuan berikut:
                    </p>

                    <h2 class="text-base font-bold text-neutral-900">
                        1. Sifat Data dan Indeks Opini
                    </h2>
                    <p>
                        SuaraNetijen menyajikan skor sentimen publik yang
                        dihasilkan melalui crawling terotomasi dan pemrosesan
                        komputasi atas percakapan di ruang publik digital. Skor
                        ini mencerminkan persepsi dan sentimen netijen, bukan
                        rekomendasi profesional atau jaminan kualitas mutlak.
                    </p>

                    <h2 class="text-base font-bold text-neutral-900">
                        2. Larangan Manipulasi & Bot
                    </h2>
                    <p>
                        Pengguna dilarang melakukan upaya manipulasi rating
                        (astroturfing, rating bombing, botting) pada fitur
                        first-party Rating Netijen. Sistem kami dilengkapi
                        deteksi anti-abuse terotomasi dan akan memblokir akun
                        yang terindikasi melakukan penyalahgunaan.
                    </p>

                    <h2 class="text-base font-bold text-neutral-900">
                        3. Hak Kekayaan Intelektual
                    </h2>
                    <p>
                        Semua nama brand, merek dagang, dan logo yang
                        ditampilkan di platform ini adalah milik dari pemilik
                        sah masing-masing. Penggunaan nama entitas semata-mata
                        bertujuan untuk kategorisasi dan analisis sentimen
                        publik secara wajar (fair use).
                    </p>
                </div>
            </div>
        </main>
    </PublicLayout>
</template>
