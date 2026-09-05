<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';
import PublicSeo from '@/components/PublicSeo.vue';
</script>

<template>
    <PublicLayout>
        <PublicSeo
            title="Tentang SuaraNetijen"
            description="Mengenal SuaraNetijen, indeks opini dan sentimen publik dari percakapan netizen untuk brand, produk, dan layanan di Indonesia."
            canonical-path="/about"
        />

        <!-- Main Content -->
        <main class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
            <nav class="mb-6 flex items-center gap-2 text-xs text-neutral-500">
                <Link :href="home()" class="hover:underline">Beranda</Link>
                <span>/</span>
                <span class="font-medium text-neutral-800">Tentang Kami</span>
            </nav>

            <div
                class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-10"
            >
                <h1
                    class="text-3xl font-black tracking-tight text-neutral-900 sm:text-4xl"
                >
                    Tentang SuaraNetijen
                </h1>
                <div
                    class="mt-6 space-y-6 text-base leading-relaxed text-neutral-700"
                >
                    <p>
                        SuaraNetijen hadir untuk menjawab pertanyaan mendasar
                        setiap konsumen di Indonesia:
                        <em
                            >"Apa sebenarnya kata netizen tentang produk atau
                            layanan ini?"</em
                        >
                    </p>
                    <p>
                        Di era ulasan sponsor dan review berbayar yang
                        membingungkan, menemukan opini nyata dari sesama
                        pengguna menjadi semakin sulit. SuaraNetijen
                        mengumpulkan percakapan dan pengalaman riil dari ribuan
                        thread komunitas, forum diskusi, dan media terbuka, lalu
                        merangkumnya menjadi indikator sentimen yang jernih,
                        transparan, dan dapat diverifikasi.
                    </p>

                    <h2 class="pt-4 text-xl font-bold text-neutral-900">
                        Pilar Utama Kami
                    </h2>
                    <ul class="list-disc space-y-2 pl-5 text-sm">
                        <li>
                            <strong>Independen:</strong> Tidak menerima bayaran
                            untuk memanipulasi, menyembunyikan, atau menaikkan
                            skor sentimen brand apapun.
                        </li>
                        <li>
                            <strong>Berbasis Data Agregasi:</strong> Skor
                            dihasilkan melalui model pemrosesan bahasa alami
                            (NLP) yang konsisten, deterministik, dan bebas bias
                            subjektif.
                        </li>
                        <li>
                            <strong>Dua Sisi yang Adil:</strong> Kami
                            menampilkan apa yang disukai netizen sekaligus apa
                            yang paling sering dikeluhkan, memberikan gambaran
                            utuh sebelum Anda memutuskan membeli atau
                            menggunakan layanan.
                        </li>
                    </ul>
                </div>
            </div>
        </main>
    </PublicLayout>
</template>
