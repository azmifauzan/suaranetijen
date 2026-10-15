<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { contact, home, methodology, sources } from '@/routes';
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

                    <h2 class="pt-4 text-xl font-bold text-neutral-900">
                        Apa yang Kami Nilai
                    </h2>
                    <p>
                        Yang kami nilai adalah entitas: brand, produk, layanan,
                        atau tokoh publik. Brand dan produknya punya halaman
                        sendiri-sendiri. Opini yang menyebut nama brand
                        dihitung untuk brand itu, sedangkan opini yang
                        menyebut produk atau layanan tertentu dihitung untuk
                        produk atau layanan tersebut. Opini tentang brand tidak
                        otomatis menurun ke produk atau layanannya.
                    </p>

                    <h2 class="pt-4 text-xl font-bold text-neutral-900">
                        Dari Mana Opininya
                    </h2>
                    <p>
                        Crawler kami membaca forum komunitas, kolom komentar
                        YouTube, situs keluhan konsumen, dan artikel media
                        yang terbuka untuk umum. Daftar lengkapnya, beserta
                        status tiap sumber, ada di halaman
                        <Link
                            :href="sources()"
                            class="font-semibold text-emerald-700 hover:underline"
                            >sumber data</Link
                        >. Setiap opini dicocokkan dulu ke satu entitas. Kalau
                        sebuah penyebutan bisa merujuk ke lebih dari satu
                        entitas, atau tidak berisi pengalaman memakai produk,
                        opini itu tidak dihitung. Kami lebih memilih kehilangan
                        satu opini daripada menempelkannya ke entitas yang
                        salah.
                    </p>

                    <h2 class="pt-4 text-xl font-bold text-neutral-900">
                        Kapan Skor Ditampilkan
                    </h2>
                    <p>
                        Skor Sentimen Netijen baru tampil setelah sebuah
                        entitas punya minimal 30 opini relevan, dan entitas
                        baru masuk ranking kategori setelah punya minimal 100
                        opini. Di bawah angka itu, sampelnya terlalu kecil
                        untuk ditarik kesimpulan. Periode bawaan adalah 365
                        hari terakhir, dan kamu bisa memilih 30 hari, 90 hari,
                        atau semua waktu. Rumus lengkapnya ada di halaman
                        <Link
                            :href="methodology()"
                            class="font-semibold text-emerald-700 hover:underline"
                            >metodologi</Link
                        >.
                    </p>

                    <h2 class="pt-4 text-xl font-bold text-neutral-900">
                        Yang Sengaja Tidak Kami Lakukan
                    </h2>
                    <p>
                        Kami tidak memberi subskor per aspek, misalnya skor
                        kamera atau skor layanan pelanggan. Tema yang sering
                        dibahas ditampilkan sebagai frekuensi, bukan angka
                        penilaian. Semua sumber diperlakukan setara dalam
                        perhitungan skor. Kami juga tidak menyimpan nama
                        pengguna, foto profil, atau data pribadi penulis opini
                        dari situs lain. Yang dinilai adalah entitasnya, bukan
                        orang yang menulis.
                    </p>

                    <h2 class="pt-4 text-xl font-bold text-neutral-900">
                        Siapa yang Mengelola
                    </h2>
                    <p>
                        SuaraNetijen dikelola oleh Tim SuaraNetijen. Pertanyaan,
                        hak jawab, dan permintaan koreksi data entitas bisa
                        dikirim lewat halaman
                        <Link
                            :href="contact()"
                            class="font-semibold text-emerald-700 hover:underline"
                            >kontak dan koreksi</Link
                        >. Kami membalas paling lambat 5 hari kerja.
                    </p>

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
