<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';
import PublicSeo from '@/components/PublicSeo.vue';

interface SourceItem {
    id: number;
    name: string;
    key: string;
    source_type: string;
    health_state: string;
    is_operational: boolean;
    enabled: boolean;
    last_preflight_at?: string;
}

defineProps<{
    sources: SourceItem[];
}>();
</script>

<template>
    <PublicLayout>
        <PublicSeo
            title="Sumber Data dan Transparansi"
            description="Lihat sumber data, status crawler, dan kode open source SuaraNetijen untuk memahami cara opini netizen diolah."
            canonical-path="/sources"
        />

        <!-- Main Content -->
        <main class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
            <!-- Breadcrumbs -->
            <nav class="mb-6 flex items-center gap-2 text-xs text-neutral-500">
                <Link :href="home()" class="hover:underline">Beranda</Link>
                <span>/</span>
                <span class="font-medium text-neutral-800">Sumber Data</span>
            </nav>

            <div
                class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-10"
            >
                <h1
                    class="text-3xl font-black tracking-tight text-neutral-900 sm:text-4xl"
                >
                    Sumber Data & Integritas Crawler
                </h1>
                <p class="mt-3 text-base leading-relaxed text-neutral-600">
                    SuaraNetijen memprioritaskan transparansi penuh mengenai
                    asal muasal data opini netizen. Sumber data kami mencakup
                    forum komunitas independen, media sosial terdesentralisasi,
                    dan platform diskusi terbuka di Indonesia.
                </p>
                <p class="mt-3 text-sm leading-relaxed text-neutral-600">
                    Kode aplikasi dan dokumentasi SuaraNetijen tersedia sebagai
                    proyek open source di
                    <a
                        href="https://github.com/azmifauzan/suaranetijen"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-800"
                        >GitHub</a
                    >.
                </p>

                <!-- Source Status List -->
                <div class="mt-8">
                    <h2 class="text-lg font-bold text-neutral-900">
                        Daftar Sumber Aktif & Status Adapter
                    </h2>
                    <p class="text-xs text-neutral-500">
                        Setiap adapter crawler memiliki pemeriksaan otomatis
                        (preflight health check) berkala.
                    </p>

                    <div class="mt-4 space-y-3">
                        <div
                            v-for="source in sources"
                            :key="source.id"
                            class="flex flex-col gap-3 rounded-xl border border-neutral-200 bg-neutral-50/50 p-4 transition sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-neutral-900">
                                        {{ source.name }}
                                    </span>
                                    <span
                                        class="rounded bg-neutral-200/80 px-2 py-0.5 text-[10px] font-semibold text-neutral-600 uppercase"
                                    >
                                        {{ source.source_type }}
                                    </span>
                                </div>
                                <div class="mt-1 text-xs text-neutral-400">
                                    Kunci adapter:
                                    <code class="font-mono">{{
                                        source.key
                                    }}</code>
                                    <template v-if="source.last_preflight_at">
                                        • Preflight terakhir:
                                        {{ source.last_preflight_at }}
                                    </template>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800':
                                            source.health_state === 'healthy',
                                        'bg-amber-100 text-amber-800':
                                            source.health_state === 'degraded',
                                        'bg-rose-100 text-rose-800':
                                            source.health_state === 'blocked' ||
                                            source.health_state ===
                                                'parser_broken',
                                        'bg-neutral-200 text-neutral-700':
                                            !source.is_operational,
                                    }"
                                >
                                    ● {{ source.health_state }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ethical Crawling Commitments -->
                <div class="mt-10 border-t border-neutral-100 pt-8">
                    <h2 class="text-lg font-bold text-neutral-900">
                        Etika & Kebijakan Crawler SuaraNetijen
                    </h2>
                    <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <div class="rounded-xl border border-neutral-200 p-4">
                            <h3 class="font-bold text-neutral-900">
                                Rate Limiting & Kesantunan Server
                            </h3>
                            <p class="mt-1.5 text-xs text-neutral-600">
                                Setiap adapter memiliki rate limiting berbasis
                                Redis untuk memastikan crawling tidak membebani
                                server asal, mematuhi header respons, dan
                                melakukan jeda adaptif.
                            </p>
                        </div>
                        <div class="rounded-xl border border-neutral-200 p-4">
                            <h3 class="font-bold text-neutral-900">
                                Tanpa Pembobotan Sumber
                            </h3>
                            <p class="mt-1.5 text-xs text-neutral-600">
                                Sesuai prinsip produk SuaraNetijen, peran sumber
                                hanya mempengaruhi frekuensi crawling, bukan
                                bobot skor sentimen. Satu opini di satu forum
                                bernilai setara dengan forum lainnya.
                            </p>
                        </div>
                        <div class="rounded-xl border border-neutral-200 p-4">
                            <h3 class="font-bold text-neutral-900">
                                Perlindungan Privasi Penulis
                            </h3>
                            <p class="mt-1.5 text-xs text-neutral-600">
                                Kami hanya mengevaluasi reputasi brand dan
                                produk. Data pribadi seperti username, foto
                                profil, dan surel tidak pernah dikoleksi maupun
                                disimpan dalam basis data.
                            </p>
                        </div>
                        <div class="rounded-xl border border-neutral-200 p-4">
                            <h3 class="font-bold text-neutral-900">
                                Penyimpanan Sementara (Raw TTL)
                            </h3>
                            <p class="mt-1.5 text-xs text-neutral-600">
                                Konten mentah hanya disimpan selama proses
                                ekstraksi opini. Setelah observasi sentimen
                                terekam, teks asli dihapus otomatis dan
                                digantikan dengan hash satu arah untuk
                                deduplikasi.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </PublicLayout>
</template>
