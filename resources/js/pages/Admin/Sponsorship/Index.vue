<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle, Pause, Play, Trash2 } from '@lucide/vue';

interface PeriodItem {
    id: number;
    key: string;
    name: string;
    status: string;
}

interface EntryItem {
    id: number;
    entity_id: number;
    settled_total_amount: number;
    first_settled_at: string | null;
    status: string;
    clicks_count: number;
    entity: {
        id: number;
        name: string;
        slug: string;
        category?: { name: string };
    };
}

interface OrderItem {
    id: number;
    provider_order_id: string;
    amount: number;
    status: string;
    created_at: string;
    user?: { name: string; email: string };
    sponsored_entry?: {
        entity?: { name: string };
    };
}

defineProps<{
    periods: PeriodItem[];
    selectedPeriod: PeriodItem;
    activePeriod: PeriodItem;
    entries: {
        data: EntryItem[];
        links: any[];
    };
    recentOrders: OrderItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Papan Sponsor', href: '/admin/sponsorship' },
        ],
    },
});

function toggleStatus(entry: EntryItem) {
    const action = entry.status === 'active' ? 'JEDA' : 'AKTIFKAN';
    if (confirm(`Apakah Anda yakin ingin me-${action} entitas "${entry.entity.name}" di papan sponsor?`)) {
        router.post(`/admin/sponsorship/entries/${entry.id}/toggle-status`, {}, { preserveScroll: true });
    }
}

function removeEntry(entry: EntryItem) {
    if (confirm(`PERINGATAN: Apakah Anda yakin ingin MENGHAPUS entitas "${entry.entity.name}" dari papan sponsor?`)) {
        router.post(`/admin/sponsorship/entries/${entry.id}/remove`, {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Papan Sponsor Moderasi - Admin" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                Papan Sponsor Moderasi
            </h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                Kelola periode, pantau pesanan sponsor, dan lakukan jeda (pause) atau hapus (remove) entitas sponsor jika melanggar kebijakan (docs/26).
            </p>
        </div>

        <!-- Period Selector -->
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Pilih Periode:</span>
            <div class="flex flex-wrap gap-2">
                <Link
                    v-for="p in periods"
                    :key="p.id"
                    :href="`/admin/sponsorship?period_id=${p.id}`"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        selectedPeriod.id === p.id
                            ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                            : 'border border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200'
                    "
                >
                    {{ p.name }}
                    <span v-if="p.id === activePeriod.id" class="text-[10px] text-green-500 font-bold">(Aktif)</span>
                </Link>
            </div>
        </div>

        <!-- Entries Table -->
        <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <div class="border-b border-neutral-200 px-6 py-4 dark:border-neutral-800">
                <h2 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                    Entitas Sponsor — {{ selectedPeriod.name }}
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-neutral-700 dark:text-neutral-300">
                    <thead class="border-b border-neutral-200 bg-neutral-50 text-xs font-semibold uppercase text-neutral-500 dark:border-neutral-800 dark:bg-neutral-800/50 dark:text-neutral-400">
                        <tr>
                            <th class="px-6 py-3">Entitas</th>
                            <th class="px-6 py-3">Kategori</th>
                            <th class="px-6 py-3">Total Sponsor</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Klik</th>
                            <th class="px-6 py-3 text-right">Aksi Moderasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                        <tr v-if="entries.data.length === 0">
                            <td colspan="6" class="px-6 py-8 text-center text-neutral-500">
                                Belum ada entitas sponsor pada periode ini.
                            </td>
                        </tr>
                        <tr v-for="entry in entries.data" :key="entry.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                            <td class="px-6 py-4 font-bold text-neutral-900 dark:text-neutral-100">
                                {{ entry.entity.name }}
                            </td>
                            <td class="px-6 py-4 text-xs text-neutral-500">
                                {{ entry.entity.category?.name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 font-extrabold text-amber-600 dark:text-amber-400">
                                Rp{{ entry.settled_total_amount.toLocaleString('id-ID') }}
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="
                                        entry.status === 'active'
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                            : entry.status === 'paused'
                                              ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
                                              : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                    "
                                >
                                    {{ entry.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                {{ entry.clicks_count }}
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <button
                                    type="button"
                                    class="rounded px-2.5 py-1 text-xs font-semibold"
                                    :class="
                                        entry.status === 'active'
                                            ? 'border border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-700 dark:text-amber-300'
                                            : 'border border-green-300 text-green-700 hover:bg-green-50 dark:border-green-700 dark:text-green-300'
                                    "
                                    @click="toggleStatus(entry)"
                                >
                                    {{ entry.status === 'active' ? 'Jeda' : 'Aktifkan' }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded border border-red-300 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-300"
                                    @click="removeEntry(entry)"
                                >
                                    Hapus
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Orders Table -->
        <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <div class="border-b border-neutral-200 px-6 py-4 dark:border-neutral-800">
                <h2 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                    Pesanan Sponsor Terbaru
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-neutral-700 dark:text-neutral-300">
                    <thead class="border-b border-neutral-200 bg-neutral-50 text-xs font-semibold uppercase text-neutral-500 dark:border-neutral-800 dark:bg-neutral-800/50 dark:text-neutral-400">
                        <tr>
                            <th class="px-6 py-3">Order ID</th>
                            <th class="px-6 py-3">Entitas</th>
                            <th class="px-6 py-3">Pengguna</th>
                            <th class="px-6 py-3">Nominal</th>
                            <th class="px-6 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                        <tr v-if="recentOrders.length === 0">
                            <td colspan="5" class="px-6 py-8 text-center text-neutral-500">
                                Belum ada pesanan sponsor tercatat.
                            </td>
                        </tr>
                        <tr v-for="order in recentOrders" :key="order.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                            <td class="px-6 py-4 font-mono text-xs text-neutral-600 dark:text-neutral-400">
                                {{ order.provider_order_id }}
                            </td>
                            <td class="px-6 py-4 font-bold text-neutral-900 dark:text-neutral-100">
                                {{ order.sponsored_entry?.entity?.name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 text-xs">
                                {{ order.user?.name ?? '-' }} ({{ order.user?.email ?? '-' }})
                            </td>
                            <td class="px-6 py-4 font-extrabold text-neutral-900 dark:text-neutral-100">
                                Rp{{ order.amount.toLocaleString('id-ID') }}
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="
                                        order.status === 'paid'
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                            : order.status === 'pending'
                                              ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
                                              : 'bg-neutral-100 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-300'
                                    "
                                >
                                    {{ order.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
