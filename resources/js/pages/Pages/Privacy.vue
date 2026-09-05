<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';
import PublicSeo from '@/components/PublicSeo.vue';
</script>

<template>
    <PublicLayout>
        <PublicSeo
            title="Kebijakan Privasi"
            description="Kebijakan privasi SuaraNetijen tentang minimalisasi data dan perlindungan informasi pengguna."
            canonical-path="/privacy"
        />

        <!-- Main Content -->
        <main class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
            <nav class="mb-6 flex items-center gap-2 text-xs text-neutral-500">
                <Link :href="home()" class="hover:underline">Beranda</Link>
                <span>/</span>
                <span class="font-medium text-neutral-800"
                    >Kebijakan Privasi</span
                >
            </nav>

            <div
                class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-10"
            >
                <h1
                    class="text-3xl font-black tracking-tight text-neutral-900 sm:text-4xl"
                >
                    Kebijakan Privasi & Perlindungan Data
                </h1>
                <div
                    class="mt-6 space-y-6 text-sm leading-relaxed text-neutral-700"
                >
                    <p>
                        SuaraNetijen berkomitmen menjunjung tinggi privasi
                        setiap pengguna dan menghormati ketentuan peraturan
                        perundang-undangan perlindungan data pribadi yang
                        berlaku di Republik Indonesia (Undang-Undang No. 27
                        Tahun 2022 tentang Perlindungan Data Pribadi / UU PDP).
                    </p>

                    <h2 class="text-base font-bold text-neutral-900">
                        1. Prinsip Minimalisasi Data Pihak Ketiga (Data
                        Minimization)
                    </h2>
                    <p>
                        Platform kami mengindeks sentimen atas
                        <strong>entitas (brand, produk, layanan)</strong>, bukan
                        manusia atau individu:
                    </p>
                    <ul class="list-disc space-y-1.5 pl-5">
                        <li>
                            Kami tidak pernah mengambil atau menyimpan nama
                            akun, username, avatar, surel, nomor kontak, riwayat
                            pertemanan, atau identitas pribadi penulis komentar
                            dari forum atau media publik.
                        </li>
                        <li>
                            Kami tidak melakukan inferensi demografi (seperti
                            jenis kelamin, agama, suku, atau orientasi politik
                            penulis).
                        </li>
                        <li>
                            Konten mentah yang diambil selama proses crawling
                            hanya disimpan sementara (short TTL) untuk
                            klasifikasi sentimen, kemudian dihapus dan diganti
                            dengan representasi hash kriptografis untuk
                            keperluan deduplikasi.
                        </li>
                    </ul>

                    <h2 class="text-base font-bold text-neutral-900">
                        2. Data Akun Pengguna Terdaftar
                    </h2>
                    <p>
                        Jika Anda mendaftarkan akun di SuaraNetijen untuk
                        memberikan rating:
                    </p>
                    <ul class="list-disc space-y-1.5 pl-5">
                        <li>
                            Kami hanya menyimpan nama dan alamat email yang Anda
                            daftarkan serta nilai bintang (star rating) yang
                            Anda berikan pada entitas.
                        </li>
                        <li>
                            Data akun Anda dilindungi dengan enkripsi kata sandi
                            modern dan dukungan otentikasi dua faktor (2FA).
                        </li>
                        <li>
                            Anda berhak mengubah atau menghapus akun dan riwayat
                            rating Anda sewaktu-waktu melalui menu Pengaturan
                            Profil.
                        </li>
                    </ul>

                    <h2 class="text-base font-bold text-neutral-900">
                        3. Hubungi Kami
                    </h2>
                    <p>
                        Apabila Anda memiliki pertanyaan atau masukan mengenai
                        tata kelola privasi kami, silakan hubungi tim kami
                        melalui surel di
                        <span class="font-medium text-emerald-600"
                            >privacy@suaranetijen.id</span
                        >.
                    </p>
                </div>
            </div>
        </main>
    </PublicLayout>
</template>
