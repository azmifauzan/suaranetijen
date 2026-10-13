<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home, methodology, sources } from '@/routes';
import { Link } from '@inertiajs/vue3';
import PublicSeo from '@/components/PublicSeo.vue';
</script>

<template>
    <PublicLayout>
        <PublicSeo
            title="Syarat dan Ketentuan"
            description="Syarat dan ketentuan penggunaan platform SuaraNetijen, indeks opini publik Indonesia."
            canonical-path="/terms"
        />

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
                        ini mencerminkan persepsi dan sentimen netizen, bukan
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

                    <h2 class="text-base font-bold text-neutral-900">
                        4. Rating Netijen
                    </h2>
                    <p>
                        Untuk memberi Rating Netijen, kamu perlu masuk ke akun.
                        Setiap akun hanya punya satu rating untuk tiap entitas.
                        Kalau kamu menilai entitas yang sama lagi, rating lama
                        diganti, dan kamu bisa menghapus rating kapan saja.
                        Jumlah pengiriman rating per menit dibatasi. Rating
                        Netijen ditampilkan terpisah dan tidak mengubah skor
                        Sentimen Netijen.
                    </p>

                    <h2 class="text-base font-bold text-neutral-900">
                        5. Opini dari Situs Lain
                    </h2>
                    <p>
                        Skor Sentimen Netijen dihitung dari opini yang terbuka
                        untuk umum di
                        <Link
                            :href="sources()"
                            class="font-semibold text-emerald-700 hover:underline"
                            >sumber yang kami daftarkan</Link
                        >. Teks aslinya hanya disimpan sementara untuk diproses,
                        lalu dihapus otomatis. Yang kami simpan seterusnya adalah
                        hasil olahannya: label positif, netral, atau negatif,
                        dan tema yang dibahas. Kami tidak menyimpan nama
                        pengguna, foto profil, atau data pribadi penulisnya.
                        Klasifikasi dilakukan oleh model dan bisa keliru;
                        penjelasan cara kerjanya ada di halaman
                        <Link
                            :href="methodology()"
                            class="font-semibold text-emerald-700 hover:underline"
                            >metodologi</Link
                        >.
                    </p>

                    <h2 class="text-base font-bold text-neutral-900">
                        6. Papan Sponsor
                    </h2>
                    <p>
                        Papan Sponsor menjual posisi tampil, dibayar lewat QRIS.
                        Posisi diurutkan berdasarkan nominal sponsor yang sudah
                        terkonfirmasi dan berlaku untuk periode yang sedang
                        berjalan. Sponsor tidak masuk ke skor Sentimen Netijen,
                        ranking kategori, atau hasil pencarian, dan tidak
                        menjamin kunjungan atau penjualan. Tokoh publik dan
                        politisi tidak bisa disponsori. Admin bisa menjeda atau
                        menghapus entri yang melanggar ketentuan ini, misalnya
                        yang mengarah ke situs judi, pinjaman ilegal, konten
                        dewasa, penipuan, atau malware.
                    </p>
                </div>
            </div>
        </main>
    </PublicLayout>
</template>
