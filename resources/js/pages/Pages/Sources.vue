<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

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
    <Head title="Sumber Data & Transparansi Crawler - SuaraNetijen">
        <meta
            name="description"
            content="Daftar sumber data terbuka, forum diskusi, dan status crawler publik yang digunakan oleh indeks sentimen SuaraNetijen."
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
            <!-- Breadcrumbs -->
            <nav class="mb-6 flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                <Link href="/" class="hover:underline">Beranda</Link>
                <span>/</span>
                <span class="font-medium text-neutral-800 dark:text-neutral-200">Sumber Data</span>
            </nav>

            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-10 dark:border-neutral-800 dark:bg-neutral-900">
                <h1 class="text-3xl font-black tracking-tight text-neutral-900 sm:text-4xl dark:text-neutral-100">
                    Sumber Data & Integritas Crawler
                </h1>
                <p class="mt-3 text-base leading-relaxed text-neutral-600 dark:text-neutral-300">
                    SuaraNetijen memprioritaskan transparansi penuh mengenai asal muasal data opini netijen. Sumber data kami mencakup forum komunitas independen, media sosial terdesentralisasi, dan platform diskusi terbuka di Indonesia.
                </p>

                <!-- Source Status List -->
                <div class="mt-8">
                    <h2 class="text-lg font-bold text-neutral-900 dark:text-neutral-100">
                        Daftar Sumber Aktif & Status Adapter
                    </h2>
                    <p class="text-xs text-neutral-500">
                        Setiap adapter crawler memiliki pemeriksaan otomatis (preflight health check) berkala.
                    </p>

                    <div class="mt-4 space-y-3">
                        <div
                            v-for="source in sources"
                            :key="source.id"
                            class="flex flex-col gap-3 rounded-xl border border-neutral-200 bg-neutral-50/50 p-4 transition sm:flex-row sm:items-center sm:justify-between dark:border-neutral-800 dark:bg-neutral-800/40"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-neutral-900 dark:text-neutral-100">
                                        {{ source.name }}
                                    </span>
                                    <span class="rounded bg-neutral-200/80 px-2 py-0.5 text-[10px] font-semibold text-neutral-600 uppercase dark:bg-neutral-700 dark:text-neutral-300">
                                        {{ source.source_type }}
                                    </span>
                                </div>
                                <div class="mt-1 text-xs text-neutral-400">
                                    Kunci adapter: <code class="font-mono">{{ source.key }}</code>
                                    <template v-if="source.last_preflight_at">
                                        • Preflight terakhir: {{ source.last_preflight_at }}
                                    </template>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300': source.health_state === 'healthy',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300': source.health_state === 'degraded',
                                        'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300': source.health_state === 'blocked' || source.health_state === 'parser_broken',
                                        'bg-neutral-200 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-300': !source.is_operational,
                                    }"
                                >
                                    ● {{ source.health_state }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ethical Crawling Commitments -->
                <div class="mt-10 border-t border-neutral-100 pt-8 dark:border-neutral-800">
                    <h2 class="text-lg font-bold text-neutral-900 dark:text-neutral-100">
                        Etika & Kebijakan Crawler SuaraNetijen
                    </h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                            <h3 class="font-bold text-neutral-900 dark:text-neutral-100">Rate Limiting & Kesantunan Server</h3>
                            <p class="mt-1.5 text-xs text-neutral-600 dark:text-neutral-300">
                                Setiap adapter memiliki rate limiting berbasis Redis untuk memastikan crawling tidak membebani server asal, mematuhi header respons, dan melakukan jeda adaptif.
                            </p>
                        </div>
                        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                            <h3 class="font-bold text-neutral-900 dark:text-neutral-100">Tanpa Pembobotan Sumber</h3>
                            <p class="mt-1.5 text-xs text-neutral-600 dark:text-neutral-300">
                                Sesuai prinsip produk SuaraNetijen, peran sumber hanya mempengaruhi frekuensi crawling, bukan bobot skor sentimen. Satu opini di satu forum bernilai setara dengan forum lainnya.
                            </p>
                        </div>
                        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                            <h3 class="font-bold text-neutral-900 dark:text-neutral-100">Perlindungan Privasi Penulis</h3>
                            <p class="mt-1.5 text-xs text-neutral-600 dark:text-neutral-300">
                                Kami hanya mengevaluasi reputasi brand dan produk. Data pribadi seperti username, foto profil, dan surel tidak pernah dikoleksi maupun disimpan dalam basis data.
                            </p>
                        </div>
                        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                            <h3 class="font-bold text-neutral-900 dark:text-neutral-100">Penyimpanan Sementara (Raw TTL)</h3>
                            <p class="mt-1.5 text-xs text-neutral-600 dark:text-neutral-300">
                                Konten mentah hanya disimpan selama proses ekstraksi opini. Setelah observasi sentimen terekam, teks asli dihapus otomatis dan digantikan dengan hash satu arah untuk deduplikasi.
                            </p>
                        </div>
                    </div>
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
                    <Link href="/sources" class="hover:underline font-semibold text-emerald-600">Sumber Data</Link>
                    <Link href="/about" class="hover:underline">Tentang Kami</Link>
                    <Link href="/terms" class="hover:underline">Ketentuan</Link>
                    <Link href="/privacy" class="hover:underline">Privasi</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
