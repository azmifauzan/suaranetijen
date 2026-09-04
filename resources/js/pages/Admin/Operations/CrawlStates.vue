<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface CrawlStateItem {
    id: number;
    cursor_key: string;
    cursor_value: string | null;
    last_external_id: string | null;
    last_crawled_at: string | null;
    metadata: Record<string, any> | null;
    source: {
        id: number;
        name: string;
        key: string;
    };
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    states: PaginatedData<CrawlStateItem>;
    sources: Array<{ id: number; name: string; key: string }>;
    filters: {
        source_id?: number | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Crawl States', href: '/admin/operations/crawl-states' },
        ],
    },
});

const selectedSource = ref<number | null>(props.filters.source_id || null);

function applyFilters() {
    router.get(
        '/admin/operations/crawl-states',
        { source_id: selectedSource.value || undefined },
        { preserveState: true }
    );
}
</script>

<template>
    <Head title="Crawl States - Admin" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                    Crawl States Diagnostics
                </h1>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Inspeksi kursor dan posisi crawling inkremental per sumber data (docs/09, docs/17).
                </p>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <Link
                    href="/admin/sources"
                    class="rounded-lg border border-neutral-300 bg-white px-3 py-2 font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                >
                    ← Sources Registry
                </Link>
                <Link
                    href="/admin/operations/ingestion-failures"
                    class="rounded-lg border border-neutral-300 bg-white px-3 py-2 font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                >
                    Ingestion Failures →
                </Link>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="flex flex-wrap items-center gap-3">
            <select
                v-model="selectedSource"
                @change="applyFilters"
                class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
            >
                <option :value="null">Semua Sumber</option>
                <option v-for="s in sources" :key="s.id" :value="s.id">
                    {{ s.name }} ({{ s.key }})
                </option>
            </select>
        </div>

        <!-- States Table -->
        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-neutral-600 dark:text-neutral-400">
                    <thead class="border-b border-neutral-200 bg-neutral-50 text-xs font-semibold text-neutral-500 uppercase dark:border-neutral-800 dark:bg-neutral-800/60 dark:text-neutral-400">
                        <tr>
                            <th class="px-6 py-3.5">Sumber Data</th>
                            <th class="px-6 py-3.5">Kunci Kursor</th>
                            <th class="px-6 py-3.5">Nilai Kursor</th>
                            <th class="px-6 py-3.5">External ID Terakhir</th>
                            <th class="px-6 py-3.5">Waktu Crawl Terakhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                        <tr v-if="states.data.length === 0">
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-neutral-400">
                                Belum ada riwayat state crawling yang tercatat.
                            </td>
                        </tr>
                        <tr
                            v-for="item in states.data"
                            :key="item.id"
                            class="transition hover:bg-neutral-50/50 dark:hover:bg-neutral-800/40"
                        >
                            <td class="px-6 py-4">
                                <div class="font-bold text-neutral-900 dark:text-neutral-100">
                                    {{ item.source.name }}
                                </div>
                                <div class="font-mono text-xs text-neutral-400">
                                    {{ item.source.key }}
                                </div>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs">
                                {{ item.cursor_key }}
                            </td>
                            <td class="px-6 py-4 font-mono text-xs max-w-xs truncate text-neutral-800 dark:text-neutral-200">
                                {{ item.cursor_value || '— (awal)' }}
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-neutral-500">
                                {{ item.last_external_id || '—' }}
                            </td>
                            <td class="px-6 py-4 text-xs text-neutral-500">
                                {{ item.last_crawled_at || 'Belum pernah' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="states.last_page > 1" class="flex items-center justify-between border-t border-neutral-200 px-6 py-3 dark:border-neutral-800">
                <span class="text-xs text-neutral-400">
                    Total: {{ states.total }} state
                </span>
                <div class="flex gap-2">
                    <Link
                        v-if="states.prev_page_url"
                        :href="states.prev_page_url"
                        class="rounded-md border border-neutral-300 px-3 py-1 text-xs hover:bg-neutral-50 dark:border-neutral-700"
                    >
                        ← Sebelumnya
                    </Link>
                    <Link
                        v-if="states.next_page_url"
                        :href="states.next_page_url"
                        class="rounded-md border border-neutral-300 px-3 py-1 text-xs hover:bg-neutral-50 dark:border-neutral-700"
                    >
                        Berikutnya →
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
