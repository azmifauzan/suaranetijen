<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home, methodology, privacy, sources, terms } from '@/routes';
import { Link } from '@inertiajs/vue3';
import PublicSeo from '@/components/PublicSeo.vue';

// email_off keeps Cloudflare from rewriting the address into a /cdn-cgi link that 404s for crawlers; Vue strips template comments, hence v-html.
const emailLink =
    '<!--email_off--><a href="mailto:info@suaranetijen.id" class="font-semibold text-emerald-700 hover:underline">info@suaranetijen.id</a><!--/email_off-->';
</script>

<template>
    <PublicLayout>
        <PublicSeo
            title="Kontak dan Koreksi"
            description="Hubungi Tim SuaraNetijen untuk pertanyaan, hak jawab, dan koreksi data entitas. Kami membalas paling lambat 5 hari kerja."
            canonical-path="/contact"
        />

        <main class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
            <nav class="mb-6 flex items-center gap-2 text-xs text-neutral-500">
                <Link :href="home()" class="hover:underline">Beranda</Link>
                <span>/</span>
                <span class="font-medium text-neutral-800"
                    >Kontak dan Koreksi</span
                >
            </nav>

            <div
                class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-10"
            >
                <h1
                    class="text-3xl font-black tracking-tight text-neutral-900 sm:text-4xl"
                >
                    Kontak dan Koreksi
                </h1>
                <div
                    class="mt-6 space-y-6 text-sm leading-relaxed text-neutral-700"
                >
                    <p>
                        SuaraNetijen dikelola oleh Tim SuaraNetijen. Kirim
                        pertanyaan, masukan, atau permintaan koreksi ke
                        <span v-html="emailLink"></span>. Kami membalas paling
                        lambat 5 hari kerja.
                    </p>

                    <h2
                        id="koreksi"
                        class="scroll-mt-24 pt-2 text-xl font-bold text-neutral-900"
                    >
                        Hak jawab dan koreksi
                    </h2>
                    <p>
                        Brand, perusahaan, tokoh publik, maupun pembaca boleh
                        meminta koreksi bila menemukan kesalahan di halaman
                        entitas. Yang bisa kami perbaiki:
                    </p>
                    <ul class="list-disc space-y-1.5 pl-5">
                        <li>
                            Opini yang tercatat untuk entitas yang salah,
                            misalnya ulasan tentang produk lain yang terhitung
                            untuk brand Anda.
                        </li>
                        <li>
                            Data entitas yang keliru: nama, kategori,
                            deskripsi, situs resmi, atau hubungan antara brand
                            dan produknya.
                        </li>
                        <li>
                            Ringkasan otomatis atau label tema yang tidak sesuai
                            dengan opini yang dirujuknya.
                        </li>
                    </ul>

                    <p>Yang tidak kami ubah atas permintaan siapa pun:</p>
                    <ul class="list-disc space-y-1.5 pl-5">
                        <li>
                            Skor Sentimen Netijen. Semua entitas dihitung dengan
                            rumus yang sama, dijelaskan di halaman
                            <Link
                                :href="methodology()"
                                class="font-semibold text-emerald-700 hover:underline"
                                >metodologi</Link
                            >. Bila ada opini yang salah tempat, kami
                            menghapus opini itu dan skornya dihitung ulang dari
                            opini yang tersisa.
                        </li>
                        <li>
                            Rating Netijen yang diberikan pengguna terdaftar.
                            Bila Anda mencurigai penyalahgunaan rating,
                            laporkan kepada kami dan kami akan memeriksanya.
                        </li>
                        <li>
                            Posisi di ranking sentimen. Sponsor tidak bisa
                            membeli skor atau urutan ranking.
                        </li>
                    </ul>

                    <h2 class="pt-2 text-xl font-bold text-neutral-900">
                        Cara mengajukan koreksi
                    </h2>
                    <p>
                        Kirim surel ke <span v-html="emailLink"></span> dan
                        sertakan:
                    </p>
                    <ul class="list-disc space-y-1.5 pl-5">
                        <li>tautan halaman entitas yang dimaksud,</li>
                        <li>bagian yang menurut Anda keliru,</li>
                        <li>
                            penjelasan atau bukti pendukung, misalnya tautan ke
                            situs resmi.
                        </li>
                    </ul>
                    <p>
                        Bila Anda mewakili sebuah brand atau tokoh publik,
                        sebutkan hubungan Anda dengan entitas tersebut. Kami
                        membalas paling lambat 5 hari kerja. Bila koreksi
                        diterima, perubahan tampil di halaman entitas setelah
                        datanya dihitung ulang.
                    </p>

                    <h2 class="pt-2 text-xl font-bold text-neutral-900">
                        Pertanyaan lain
                    </h2>
                    <p>
                        Soal data akun dan privasi, baca
                        <Link
                            :href="privacy()"
                            class="font-semibold text-emerald-700 hover:underline"
                            >Kebijakan Privasi</Link
                        >. Aturan memakai situs ini ada di
                        <Link
                            :href="terms()"
                            class="font-semibold text-emerald-700 hover:underline"
                            >Ketentuan Penggunaan</Link
                        >, dan daftar sumber opini beserta statusnya ada di
                        halaman
                        <Link
                            :href="sources()"
                            class="font-semibold text-emerald-700 hover:underline"
                            >sumber data</Link
                        >.
                    </p>
                </div>
            </div>
        </main>
    </PublicLayout>
</template>
