<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

interface Stats {
    total_entities: number;
    total_categories: number;
    total_aliases: number;
    total_sources: number;
    enabled_sources: number;
    total_crawl_states: number;
    unresolved_failures: number;
    total_unmatched: number;
    pending_entity_candidates: number;
}

defineProps<{
    stats: Stats;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin Dashboard',
                href: '/admin',
            },
        ],
    },
});
</script>

<template>
    <Head title="Admin Dashboard - SuaraNetijen" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                Admin Overview
            </h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                Katalog entitas, manajemen sumber crawler, kill switch, dan diagnostik operasi (docs/17).
            </p>
        </div>

        <!-- Catalog Overview -->
        <div>
            <h2 class="text-xs font-semibold tracking-wider text-neutral-400 uppercase dark:text-neutral-500">
                Katalog Entitas
            </h2>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Total Entitas</div>
                    <div class="mt-2 text-3xl font-extrabold text-neutral-900 dark:text-neutral-100">{{ stats.total_entities }}</div>
                    <div class="mt-4">
                        <Link href="/admin/entities" class="text-sm font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                            Kelola entitas &rarr;
                        </Link>
                    </div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Total Kategori</div>
                    <div class="mt-2 text-3xl font-extrabold text-neutral-900 dark:text-neutral-100">{{ stats.total_categories }}</div>
                    <div class="mt-4">
                        <Link href="/admin/categories" class="text-sm font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                            Kelola kategori &rarr;
                        </Link>
                    </div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Total Alias</div>
                    <div class="mt-2 text-3xl font-extrabold text-neutral-900 dark:text-neutral-100">{{ stats.total_aliases }}</div>
                    <div class="mt-4 text-xs text-neutral-400">
                        Digunakan untuk resolusi pencarian & matching
                    </div>
                </div>
            </div>
        </div>

        <!-- Operations & Crawler Overview (Epic 11) -->
        <div class="mt-4">
            <h2 class="text-xs font-semibold tracking-wider text-neutral-400 uppercase dark:text-neutral-500">
                Operasi & Crawler Diagnostics (Epic 11)
            </h2>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Sources & Kill Switch</div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold text-neutral-900 dark:text-neutral-100">{{ stats.enabled_sources }}</span>
                        <span class="text-sm text-neutral-400">/ {{ stats.total_sources }} aktif</span>
                    </div>
                    <div class="mt-4">
                        <Link href="/admin/sources" class="text-sm font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                            Kelola & Kill Switch &rarr;
                        </Link>
                    </div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Crawl States</div>
                    <div class="mt-2 text-3xl font-extrabold text-neutral-900 dark:text-neutral-100">{{ stats.total_crawl_states }}</div>
                    <div class="mt-4">
                        <Link href="/admin/operations/crawl-states" class="text-sm font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                            Inspeksi kursor crawl &rarr;
                        </Link>
                    </div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Ingestion Failures</div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span
                            class="text-3xl font-extrabold"
                            :class="stats.unresolved_failures > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-neutral-900 dark:text-neutral-100'"
                        >
                            {{ stats.unresolved_failures }}
                        </span>
                        <span class="text-xs text-neutral-400">unresolved</span>
                    </div>
                    <div class="mt-4">
                        <Link href="/admin/operations/ingestion-failures" class="text-sm font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                            Lihat & Replay &rarr;
                        </Link>
                    </div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Unmatched Mentions</div>
                    <div class="mt-2 text-3xl font-extrabold text-neutral-900 dark:text-neutral-100">{{ stats.total_unmatched }}</div>
                    <div class="mt-4">
                        <Link href="/admin/operations/unmatched-mentions" class="text-sm font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                            Inspeksi mention ambigu &rarr;
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Entity Candidates & LLM Settings -->
        <div class="mt-4">
            <h2 class="text-xs font-semibold tracking-wider text-neutral-400 uppercase dark:text-neutral-500">
                Seed Growth
            </h2>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Entity Candidates Pending</div>
                    <div class="mt-2 text-3xl font-extrabold text-neutral-900 dark:text-neutral-100">{{ stats.pending_entity_candidates }}</div>
                    <div class="mt-4">
                        <Link href="/admin/entity-candidates" class="text-sm font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                            Review candidates &rarr;
                        </Link>
                    </div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="text-sm font-medium text-neutral-500 dark:text-neutral-400">LLM Settings</div>
                    <div class="mt-2 text-xs text-neutral-400">Base URL, model, key used by candidate enrichment</div>
                    <div class="mt-4">
                        <Link href="/admin/llm-settings" class="text-sm font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                            Configure &rarr;
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
