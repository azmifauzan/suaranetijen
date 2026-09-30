<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Bot, CheckCircle, ExternalLink, Plus, RefreshCw, Search, XCircle } from '@lucide/vue';
import { ref } from 'vue';
import admin from '@/routes/admin';

interface TopicItem {
    id: number;
    slug: string;
    keyword: string;
    title: string | null;
    status: string;
    source: string;
    candidate_signal: number;
    category?: { id: number; name: string } | null;
    themes: Array<{ id: number; display_label: string }>;
}

const props = defineProps<{
    topics: {
        data: TopicItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    counts: {
        candidate: number;
        draft: number;
        published: number;
        rejected: number;
    };
    currentStatus: string;
    filters: {
        q?: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Topik Landing Pages', href: '/admin/topics' },
        ],
    },
});

const search = ref(props.filters.q || '');

function onSearch() {
    router.get(
        admin.topics.index(),
        {
            status: props.currentStatus,
            q: search.value,
        },
        { preserveState: true, replace: true },
    );
}

function switchTab(status: string) {
    router.get(
        admin.topics.index(),
        {
            status,
            q: search.value || undefined,
        },
        { preserveState: true },
    );
}

function regenerate(id: number) {
    router.post(admin.topics.regenerate(id), {}, { preserveScroll: true });
}

function reject(id: number) {
    if (confirm('Yakin ingin menolak topik ini secara permanen?')) {
        router.post(admin.topics.reject(id), {}, { preserveScroll: true });
    }
}

function unpublish(id: number) {
    if (confirm('Yakin ingin mengembalikan topik ke draft? Halaman publik akan jadi 404.')) {
        router.post(admin.topics.unpublish(id), {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Topik Landing Pages - Admin" />

    <div class="space-y-6 p-6">
        <!-- Top bar -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-neutral-900">
                    Topik Landing Pages (SEO)
                </h1>
                <p class="text-sm text-neutral-500">
                    Kelola kata kunci landing page untuk menangkap pencarian long-tail netizen.
                </p>
            </div>
            <Link
                :href="admin.topics.create()"
                class="inline-flex items-center gap-1.5 rounded-lg bg-[#087f5b] px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-[#076c4d]"
            >
                <Plus class="size-4" />
                Buat Topik Manual
            </Link>
        </div>

        <!-- Status tabs & search -->
        <div class="flex flex-col gap-4 border-b border-neutral-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                        currentStatus === 'candidate'
                            ? 'bg-[#edf8f0] text-[#185b3b]'
                            : 'text-neutral-600 hover:bg-neutral-100',
                    ]"
                    @click="switchTab('candidate')"
                >
                    Kandidat ({{ counts.candidate }})
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                        currentStatus === 'draft'
                            ? 'bg-[#edf8f0] text-[#185b3b]'
                            : 'text-neutral-600 hover:bg-neutral-100',
                    ]"
                    @click="switchTab('draft')"
                >
                    Draft ({{ counts.draft }})
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                        currentStatus === 'published'
                            ? 'bg-[#edf8f0] text-[#185b3b]'
                            : 'text-neutral-600 hover:bg-neutral-100',
                    ]"
                    @click="switchTab('published')"
                >
                    Published ({{ counts.published }})
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                        currentStatus === 'rejected'
                            ? 'bg-[#edf8f0] text-[#185b3b]'
                            : 'text-neutral-600 hover:bg-neutral-100',
                    ]"
                    @click="switchTab('rejected')"
                >
                    Ditolak ({{ counts.rejected }})
                </button>
            </div>

            <!-- Search input -->
            <form class="flex items-center gap-2" @submit.prevent="onSearch">
                <div class="relative">
                    <Search class="absolute top-2.5 left-2.5 size-4 text-neutral-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari keyword/title..."
                        class="rounded-lg border border-neutral-300 py-1.5 pr-3 pl-8 text-xs focus:border-[#087f5b] focus:outline-hidden"
                    />
                </div>
                <button
                    type="submit"
                    class="rounded-lg border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50"
                >
                    Cari
                </button>
            </form>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xs">
            <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Keyword / Judul</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3">Tema</th>
                        <th class="px-4 py-3">Sinyal</th>
                        <th class="px-4 py-3">Sumber</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200">
                    <tr v-if="topics.data.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-neutral-500">
                            Tidak ada data topik pada status ini.
                        </td>
                    </tr>
                    <tr v-for="topic in topics.data" :key="topic.id" class="hover:bg-neutral-50/50">
                        <td class="px-4 py-3">
                            <div class="font-bold text-neutral-900">
                                {{ topic.keyword }}
                            </div>
                            <div v-if="topic.title" class="text-xs text-neutral-500">
                                {{ topic.title }}
                            </div>
                            <div class="text-[11px] text-neutral-400">
                                /topik/{{ topic.slug }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                v-if="topic.category"
                                class="rounded-md bg-neutral-100 px-2 py-0.5 text-xs text-neutral-700"
                            >
                                {{ topic.category.name }}
                            </span>
                            <span v-else class="text-xs italic text-neutral-400">Belum diset</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="t in topic.themes"
                                    :key="t.id"
                                    class="rounded-md bg-[#edf8f0] px-1.5 py-0.5 text-[11px] text-[#185b3b]"
                                >
                                    {{ t.display_label }}
                                </span>
                                <span v-if="topic.themes.length === 0" class="text-xs italic text-neutral-400">
                                    0 tema
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs font-semibold text-neutral-700">
                            {{ topic.candidate_signal }}
                        </td>
                        <td class="px-4 py-3 text-xs text-neutral-500">
                            {{ topic.source }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <Link
                                    :href="admin.topics.edit(topic.id)"
                                    class="rounded-md border border-neutral-300 px-2.5 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-50"
                                >
                                    Edit
                                </Link>

                                <button
                                    v-if="topic.status === 'candidate'"
                                    type="button"
                                    title="Regenerate Draft LLM"
                                    class="inline-flex items-center rounded-md border border-neutral-300 p-1 text-neutral-600 hover:bg-neutral-50"
                                    @click="regenerate(topic.id)"
                                >
                                    <Bot class="size-3.5" />
                                </button>

                                <a
                                    v-if="topic.status === 'published'"
                                    :href="`/topik/${topic.slug}`"
                                    target="_blank"
                                    title="Lihat halaman publik"
                                    class="inline-flex items-center rounded-md border border-neutral-300 p-1 text-neutral-600 hover:bg-neutral-50"
                                >
                                    <ExternalLink class="size-3.5" />
                                </a>

                                <button
                                    v-if="topic.status === 'published'"
                                    type="button"
                                    class="rounded-md border border-neutral-300 px-2 py-1 text-xs text-amber-600 hover:bg-amber-50"
                                    @click="unpublish(topic.id)"
                                >
                                    Unpublish
                                </button>

                                <button
                                    v-if="topic.status !== 'rejected' && topic.status !== 'published'"
                                    type="button"
                                    title="Tolak permanen"
                                    class="rounded-md border border-neutral-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50"
                                    @click="reject(topic.id)"
                                >
                                    Tolak
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="topics.links.length > 3" class="flex justify-center gap-1 pt-4">
            <template v-for="(link, i) in topics.links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        'rounded-md px-3 py-1.5 text-xs font-medium transition',
                        link.active
                            ? 'bg-[#087f5b] text-white'
                            : 'border border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-50',
                    ]"
                    v-html="link.label"
                />
                <span
                    v-else
                    class="rounded-md px-3 py-1.5 text-xs text-neutral-400"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
