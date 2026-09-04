<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Kebijakan Privasi - SuaraNetijen">
        <meta
            name="description"
            content="Kebijakan privasi SuaraNetijen. Prinsip minimalisasi data dan kepatuhan UU PDP dalam pengumpulan opini publik."
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
                <span class="font-medium text-neutral-800 dark:text-neutral-200">Kebijakan Privasi</span>
            </nav>

            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-10 dark:border-neutral-800 dark:bg-neutral-900">
                <h1 class="text-3xl font-black tracking-tight text-neutral-900 sm:text-4xl dark:text-neutral-100">
                    Kebijakan Privasi & Perlindungan Data
                </h1>
                <div class="mt-6 space-y-6 text-sm leading-relaxed text-neutral-700 dark:text-neutral-300">
                    <p>
                        SuaraNetijen berkomitmen menjunjung tinggi privasi setiap pengguna dan menghormati ketentuan peraturan perundang-undangan perlindungan data pribadi yang berlaku di Republik Indonesia (Undang-Undang No. 27 Tahun 2022 tentang Perlindungan Data Pribadi / UU PDP).
                    </p>

                    <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">
                        1. Prinsip Minimalisasi Data Pihak Ketiga (Data Minimization)
                    </h2>
                    <p>
                        Platform kami mengindeks sentimen atas <strong>entitas (brand, produk, layanan)</strong>, bukan manusia atau individu:
                    </p>
                    <ul class="list-disc space-y-1.5 pl-5">
                        <li>Kami tidak pernah mengambil atau menyimpan nama akun, username, avatar, surel, nomor kontak, riwayat pertemanan, atau identitas pribadi penulis komentar dari forum atau media publik.</li>
                        <li>Kami tidak melakukan inferensi demografi (seperti jenis kelamin, agama, suku, atau orientasi politik penulis).</li>
                        <li>Konten mentah yang diambil selama proses crawling hanya disimpan sementara (short TTL) untuk klasifikasi sentimen, kemudian dihapus dan diganti dengan representasi hash kriptografis untuk keperluan deduplikasi.</li>
                    </ul>

                    <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">
                        2. Data Akun Pengguna Terdaftar
                    </h2>
                    <p>
                        Jika Anda mendaftarkan akun di SuaraNetijen untuk memberikan rating:
                    </p>
                    <ul class="list-disc space-y-1.5 pl-5">
                        <li>Kami hanya menyimpan nama dan alamat email yang Anda daftarkan serta nilai bintang (star rating) yang Anda berikan pada entitas.</li>
                        <li>Data akun Anda dilindungi dengan enkripsi kata sandi modern dan dukungan otentikasi dua faktor (2FA).</li>
                        <li>Anda berhak mengubah atau menghapus akun dan riwayat rating Anda sewaktu-waktu melalui menu Pengaturan Profil.</li>
                    </ul>

                    <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">
                        3. Hubungi Kami
                    </h2>
                    <p>
                        Apabila Anda memiliki pertanyaan atau masukan mengenai tata kelola privasi kami, silakan hubungi tim kami melalui surel di <span class="font-medium text-emerald-600 dark:text-emerald-400">privacy@suaranetijen.id</span>.
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
                    <Link href="/terms" class="hover:underline">Ketentuan</Link>
                    <Link href="/privacy" class="hover:underline font-semibold text-emerald-600">Privasi</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
