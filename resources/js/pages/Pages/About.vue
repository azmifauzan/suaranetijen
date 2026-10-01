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
            description="Mengenal SuaraNetijen, proyek open source yang mengolah opini publik menjadi skor sentimen, tema, dan rating pengguna yang terpisah."
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
                        SuaraNetijen mengolah opini publik tentang brand,
                        produk, dan layanan di Indonesia. Halaman entitas
                        menampilkan skor sentimen, tema yang sering muncul,
                        dan rating pengguna secara terpisah.
                    </p>
                    <p>
                        Model mengelompokkan opini relevan sebagai positif,
                        netral, atau negatif. Hasilnya dapat keliru, terutama
                        untuk sarkasme dan typo berat; opini yang ambigu bisa
                        tidak diklasifikasikan.
                    </p>

                    <h2 class="pt-4 text-xl font-bold text-neutral-900">
                        Cara Indeks Disusun
                    </h2>
                    <ul class="list-disc space-y-2 pl-5 text-sm">
                        <li>
                            <strong>Skor terpisah dari sponsor:</strong>
                            Nominal sponsor tidak masuk ke formula skor atau
                            urutan ranking sentimen.
                        </li>
                        <li>
                            <strong>Berbasis Data Agregasi:</strong> Skor
                            dihitung dari opini relevan yang diklasifikasikan
                            sebagai positif, netral, atau negatif. Klasifikasi
                            otomatis memiliki batas dan dapat keliru.
                        </li>
                        <li>
                            <strong>Tiga informasi terpisah:</strong> Skor
                            sentimen, Rating Netijen, dan frekuensi tema tidak
                            digabung menjadi satu nilai.
                        </li>
                    </ul>

                    <section
                        class="mt-8 rounded-xl border border-emerald-100 bg-emerald-50/60 p-5"
                    >
                        <h2 class="text-lg font-bold text-neutral-900">
                            Proyek Open Source
                        </h2>
                        <p
                            class="mt-2 text-sm leading-relaxed text-neutral-700"
                        >
                            SuaraNetijen dikembangkan secara open source. Kode
                            sumber dan dokumentasinya tersedia di GitHub agar
                            dapat dipelajari dan dikembangkan bersama.
                        </p>
                        <a
                            href="https://github.com/azmifauzan/suaranetijen"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-3 inline-flex min-h-11 items-center rounded-full bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800"
                            >Lihat kode sumber di GitHub</a
                        >
                    </section>
                </div>
            </div>
        </main>
    </PublicLayout>
</template>
