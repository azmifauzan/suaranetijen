<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface MentionItem {
    id: number;
    reason: string;
    content_hash: string;
    created_at: string;
    source: {
        id: number;
        name: string;
        key: string;
    };
    item?: {
        id: number;
        external_id: string;
    } | null;
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
    mentions: PaginatedData<MentionItem>;
    sources: Array<{ id: number; name: string; key: string }>;
    reasons: string[];
    filters: {
        source_id?: number | null;
        reason?: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Unmatched Mentions', href: '/admin/operations/unmatched-mentions' },
        ],
    },
});

const selectedSource = ref<number | null>(props.filters.source_id || null);
const selectedReason = ref<string | null>(props.filters.reason || null);

function applyFilters() {
    router.get(
        '/admin/operations/unmatched-mentions',
        {
            source_id: selectedSource.value || undefined,
            reason: selectedReason.value || undefined,
        },
        { preserveState: true }
    );
}
</script>

<template>
    <Head title="Unmatched Mentions - Admin" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                    Unmatched Mentions Diagnostics
                </h1>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Inspeksi mention yang tidak terpetakan (ambigu) atau tidak mengandung evaluasi pengalaman (docs/10, docs/17).
                </p>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <Link
                    href="/admin/sources"
                    class="rounded-lg border border-neutral-300 bg-white px-3 py-2 font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                >
                    Sources Registry →
                </Link>
                <Link
                    href="/admin/operations/ingestion-failures"
                    class="rounded-lg border border-neutral-300 bg-white px-3 py-2 font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                >
                    Ingestion Failures →
                </Link>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3">
            <select
                v-model="selectedSource"
                @change="applyFilters"
                class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
            >
                <option :value="null">Semua Sumber</option>
                <option v-for="s in sources" :key="s.id" :value="s.id">
                    {{ s.name }}
                </option>
            </select>

            <select
                v-model="selectedReason"
                @change="applyFilters"
                class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
            >
                <option :value="null">Semua Alasan (Reasons)</option>
                <option v-for="r in reasons" :key="r" :value="r">
                    {{ r === 'entity_not_resolved' ? 'Entitas Tidak Terpetakan' : 'Bukan Opini Evaluatif' }}
                </option>
            </select>
        </div>

        <!-- Mentions Table -->
        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-neutral-600 dark:text-neutral-400">
                    <thead class="border-b border-neutral-200 bg-neutral-50 text-xs font-semibold text-neutral-500 uppercase dark:border-neutral-800 dark:bg-neutral-800/60 dark:text-neutral-400">
                        <tr>
                            <th class="px-6 py-3.5">ID & Sumber</th>
                            <th class="px-6 py-3.5">Alasan Penyisihan</th>
                            <th class="px-6 py-3.5">Item External ID</th>
                            <th class="px-6 py-3.5">Content Hash (Deduplikasi)</th>
                            <th class="px-6 py-3.5">Waktu Dicatat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                        <tr v-if="mentions.data.length === 0">
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-neutral-400">
                                Tidak ada data unmatched mentions yang tercatat.
                            </td>
                        </tr>
                        <tr
                            v-for="m in mentions.data"
                            :key="m.id"
                            class="transition hover:bg-neutral-50/50 dark:hover:bg-neutral-800/40"
                        >
                            <td class="px-6 py-4">
                                <div class="font-bold text-neutral-900 dark:text-neutral-100">
                                    #{{ m.id }}
                                </div>
                                <div class="text-xs text-neutral-400">
                                    {{ m.source.name }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="rounded px-2 py-0.5 text-xs font-semibold uppercase"
                                    :class="m.reason === 'entity_not_resolved'
                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                                        : 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300'"
                                >
                                    {{ m.reason }}
                                </span>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-neutral-600 dark:text-neutral-400">
                                {{ m.item ? m.item.external_id : '—' }}
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-neutral-400 max-w-xs truncate">
                                {{ m.content_hash }}
                            </td>
                            <td class="px-6 py-4 text-xs text-neutral-500 whitespace-nowrap">
                                {{ m.created_at }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="mentions.last_page > 1" class="flex items-center justify-between border-t border-neutral-200 px-6 py-3 dark:border-neutral-800">
                <span class="text-xs text-neutral-400">
                    Total: {{ mentions.total }} unmatched mentions
                </span>
                <div class="flex gap-2">
                    <Link
                        v-if="mentions.prev_page_url"
                        :href="mentions.prev_page_url"
                        class="rounded-md border border-neutral-300 px-3 py-1 text-xs hover:bg-neutral-50 dark:border-neutral-700"
                    >
                        ← Sebelumnya
                    </Link>
                    <Link
                        v-if="mentions.next_page_url"
                        :href="mentions.next_page_url"
                        class="rounded-md border border-neutral-300 px-3 py-1 text-xs hover:bg-neutral-50 dark:border-neutral-700"
                    >
                        Berikutnya →
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
