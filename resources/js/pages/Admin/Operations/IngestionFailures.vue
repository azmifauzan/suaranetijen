<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface FailureItem {
    id: number;
    stage: string;
    error_message: string;
    exception_class: string | null;
    context: Record<string, any> | null;
    resolved_at: string | null;
    created_at: string;
    source: {
        id: number;
        name: string;
        key: string;
    };
    item?: {
        id: number;
        external_id: string;
        processing_state: string;
    } | null;
    document?: {
        id: number;
        title: string | null;
        canonical_url: string;
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
    failures: PaginatedData<FailureItem>;
    sources: Array<{ id: number; name: string; key: string }>;
    stages: string[];
    filters: {
        source_id?: number | null;
        stage?: string | null;
        status?: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Ingestion Failures', href: '/admin/operations/ingestion-failures' },
        ],
    },
});

const selectedSource = ref<number | null>(props.filters.source_id || null);
const selectedStage = ref<string | null>(props.filters.stage || null);
const selectedStatus = ref<string>(props.filters.status || 'unresolved');

function applyFilters() {
    router.get(
        '/admin/operations/ingestion-failures',
        {
            source_id: selectedSource.value || undefined,
            stage: selectedStage.value || undefined,
            status: selectedStatus.value || undefined,
        },
        { preserveState: true }
    );
}

function replayFailure(failure: FailureItem) {
    if (confirm(`Re-queue dan jalankan ulang pemrosesan untuk kegagalan #${failure.id}?`)) {
        router.post(`/admin/operations/failures/${failure.id}/retry`, {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Ingestion Failures & Replay - Admin" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                    Ingestion Failures & Replay
                </h1>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Pantau kegagalan crawler, parser, matching, dan jalankan replay item langsung dari panel (docs/17).
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
                    href="/admin/operations/unmatched-mentions"
                    class="rounded-lg border border-neutral-300 bg-white px-3 py-2 font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                >
                    Unmatched Mentions →
                </Link>
            </div>
        </div>

        <!-- Filters -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
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
                v-model="selectedStage"
                @change="applyFilters"
                class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
            >
                <option :value="null">Semua Tahap (Stages)</option>
                <option v-for="stg in stages" :key="stg" :value="stg">
                    Stage: {{ stg }}
                </option>
            </select>

            <select
                v-model="selectedStatus"
                @change="applyFilters"
                class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
            >
                <option value="unresolved">Belum Diselesaikan (Unresolved)</option>
                <option value="resolved">Sudah Direplay (Resolved)</option>
                <option value="all">Semua Status</option>
            </select>
        </div>

        <!-- Failures Table -->
        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-neutral-600 dark:text-neutral-400">
                    <thead class="border-b border-neutral-200 bg-neutral-50 text-xs font-semibold text-neutral-500 uppercase dark:border-neutral-800 dark:bg-neutral-800/60 dark:text-neutral-400">
                        <tr>
                            <th class="px-6 py-3.5">ID & Sumber</th>
                            <th class="px-6 py-3.5">Tahap (Stage)</th>
                            <th class="px-6 py-3.5">Pesan Error & Exception</th>
                            <th class="px-6 py-3.5">Target Item / Dokumen</th>
                            <th class="px-6 py-3.5">Waktu Kejadian</th>
                            <th class="px-6 py-3.5 text-center">Aksi Replay</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                        <tr v-if="failures.data.length === 0">
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-neutral-400">
                                Tidak ada data kegagalan ingestion yang ditemukan.
                            </td>
                        </tr>
                        <tr
                            v-for="fail in failures.data"
                            :key="fail.id"
                            class="transition hover:bg-neutral-50/50 dark:hover:bg-neutral-800/40"
                            :class="{ 'opacity-60': fail.resolved_at }"
                        >
                            <td class="px-6 py-4">
                                <div class="font-bold text-neutral-900 dark:text-neutral-100">
                                    #{{ fail.id }}
                                </div>
                                <div class="text-xs text-neutral-400">
                                    {{ fail.source.name }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="rounded bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-800 uppercase dark:bg-rose-950/60 dark:text-rose-300">
                                    {{ fail.stage }}
                                </span>
                            </td>
                            <td class="px-6 py-4 max-w-sm">
                                <div class="font-medium text-rose-600 dark:text-rose-400 text-xs line-clamp-2">
                                    {{ fail.error_message }}
                                </div>
                                <div v-if="fail.exception_class" class="mt-0.5 font-mono text-[10px] text-neutral-400">
                                    {{ fail.exception_class }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-xs font-mono">
                                <div v-if="fail.item">
                                    Item: #{{ fail.item.id }} ({{ fail.item.external_id }})
                                </div>
                                <div v-else-if="fail.document">
                                    Doc: #{{ fail.document.id }} ({{ fail.document.title || fail.document.canonical_url }})
                                </div>
                                <div v-else class="text-neutral-400">
                                    —
                                </div>
                            </td>
                            <td class="px-6 py-4 text-xs text-neutral-500 whitespace-nowrap">
                                <div>{{ fail.created_at }}</div>
                                <div v-if="fail.resolved_at" class="mt-0.5 text-[10px] text-emerald-600">
                                    Resolved: {{ fail.resolved_at }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button
                                    type="button"
                                    @click="replayFailure(fail)"
                                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 transition"
                                >
                                    Replay ↺
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="failures.last_page > 1" class="flex items-center justify-between border-t border-neutral-200 px-6 py-3 dark:border-neutral-800">
                <span class="text-xs text-neutral-400">
                    Total: {{ failures.total }} kegagalan
                </span>
                <div class="flex gap-2">
                    <Link
                        v-if="failures.prev_page_url"
                        :href="failures.prev_page_url"
                        class="rounded-md border border-neutral-300 px-3 py-1 text-xs hover:bg-neutral-50 dark:border-neutral-700"
                    >
                        ← Sebelumnya
                    </Link>
                    <Link
                        v-if="failures.next_page_url"
                        :href="failures.next_page_url"
                        class="rounded-md border border-neutral-300 px-3 py-1 text-xs hover:bg-neutral-50 dark:border-neutral-700"
                    >
                        Berikutnya →
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
