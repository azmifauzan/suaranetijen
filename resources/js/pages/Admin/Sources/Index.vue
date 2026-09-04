<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

interface SourceItem {
    id: number;
    key: string;
    name: string;
    adapter: string;
    source_type: string;
    enabled: boolean;
    priority: number;
    health_state: string;
    is_operational: boolean;
    last_preflight_at?: string;
    documents_count: number;
    items_count: number;
}

defineProps<{
    sources: SourceItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Sources Registry', href: '/admin/sources' },
        ],
    },
});

function toggleKillSwitch(source: SourceItem) {
    const action = source.enabled ? 'NONAKTIFKAN (Kill Switch)' : 'AKTIFKAN KEMBALI';
    if (confirm(`Apakah Anda yakin ingin me-${action} crawler sumber "${source.name}"?`)) {
        router.post(`/admin/sources/${source.id}/toggle-status`, {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Sources Registry & Kill Switch - Admin" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Page Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                    Sources Registry & Kill Switch
                </h1>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Kelola status crawler, pantau kesehatan adapter, dan matikan sumber bermasalah secara instan tanpa deploy kode (docs/17).
                </p>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <Link
                    href="/admin/operations/crawl-states"
                    class="rounded-lg border border-neutral-300 bg-white px-3 py-2 font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                >
                    Crawl States →
                </Link>
                <Link
                    href="/admin/operations/ingestion-failures"
                    class="rounded-lg border border-neutral-300 bg-white px-3 py-2 font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                >
                    Ingestion Failures →
                </Link>
            </div>
        </div>

        <!-- Sources Table -->
        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-neutral-600 dark:text-neutral-400">
                    <thead class="border-b border-neutral-200 bg-neutral-50 text-xs font-semibold text-neutral-500 uppercase dark:border-neutral-800 dark:bg-neutral-800/60 dark:text-neutral-400">
                        <tr>
                            <th class="px-6 py-3.5">Sumber & Kunci</th>
                            <th class="px-6 py-3.5">Adapter</th>
                            <th class="px-6 py-3.5">Tipe & Prioritas</th>
                            <th class="px-6 py-3.5">Kesehatan</th>
                            <th class="px-6 py-3.5 text-right">Data Terkumpul</th>
                            <th class="px-6 py-3.5 text-center">Status & Kill Switch</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                        <tr
                            v-for="source in sources"
                            :key="source.id"
                            class="transition hover:bg-neutral-50/50 dark:hover:bg-neutral-800/40"
                            :class="{ 'opacity-60 bg-neutral-50/30': !source.enabled }"
                        >
                            <td class="px-6 py-4">
                                <div class="font-bold text-neutral-900 dark:text-neutral-100">
                                    {{ source.name }}
                                </div>
                                <div class="text-xs font-mono text-neutral-400">
                                    {{ source.key }}
                                </div>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-neutral-500">
                                {{ source.adapter }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="rounded bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                    {{ source.source_type }}
                                </span>
                                <span class="ml-2 text-xs text-neutral-400">P{{ source.priority }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300': source.health_state === 'healthy',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300': source.health_state === 'degraded',
                                        'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300': !source.is_operational,
                                    }"
                                >
                                    ● {{ source.health_state }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-xs">
                                <div class="font-semibold text-neutral-800 dark:text-neutral-200">
                                    {{ source.items_count.toLocaleString() }} opini
                                </div>
                                <div class="text-neutral-400">
                                    {{ source.documents_count.toLocaleString() }} dokumen
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button
                                        type="button"
                                        @click="toggleKillSwitch(source)"
                                        class="rounded-lg px-3 py-1.5 text-xs font-bold transition shadow-xs"
                                        :class="source.enabled
                                            ? 'bg-rose-600 text-white hover:bg-rose-700 dark:bg-rose-700 dark:hover:bg-rose-800'
                                            : 'bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600'
                                        "
                                    >
                                        {{ source.enabled ? 'Matikan (Kill Switch)' : 'Aktifkan' }}
                                    </button>
                                </div>
                                <div class="mt-1 text-[10px]" :class="source.enabled ? 'text-emerald-600' : 'text-rose-600 font-bold'">
                                    {{ source.enabled ? 'Crawler Berjalan' : 'KILLED / Dihentikan' }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
