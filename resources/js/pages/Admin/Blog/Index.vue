<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ExternalLink,
    FileText,
    Image as ImageIcon,
    Plus,
    Search,
    Trash2,
    Undo2,
    UploadCloud,
} from '@lucide/vue';
import { ref } from 'vue';
import admin from '@/routes/admin';

interface PostItem {
    id: number;
    slug: string;
    title: string;
    status: string;
    status_label: string;
    published_at: string | null;
    cover_url: string | null;
    author_name: string;
    entities_count: number;
    updated_at: string | null;
}

const props = defineProps<{
    posts: {
        data: PostItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    counts: {
        all: number;
        draft: number;
        published: number;
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
            { title: 'Blog', href: '/admin/blog' },
        ],
    },
});

const search = ref(props.filters.q || '');

function onSearch() {
    router.get(
        admin.blog.index(),
        {
            status: props.currentStatus,
            q: search.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function switchTab(status: string) {
    router.get(
        admin.blog.index(),
        {
            status,
            q: search.value || undefined,
        },
        { preserveState: true },
    );
}

function unpublish(id: number) {
    if (
        confirm(
            'Yakin ingin menarik artikel ke draft? Halaman publik akan menghasilkan 404.',
        )
    ) {
        router.post(admin.blog.unpublish(id), {}, { preserveScroll: true });
    }
}

function publish(id: number) {
    router.post(admin.blog.publish(id), {}, { preserveScroll: true });
}

function destroy(id: number) {
    if (confirm('Yakin ingin menghapus draft artikel ini secara permanen?')) {
        router.delete(admin.blog.destroy(id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Blog - Admin" />

    <div class="space-y-6 p-6">
        <!-- Top bar -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-neutral-900">
                    Artikel Blog
                </h1>
                <p class="text-sm text-neutral-500">
                    Kelola artikel editorial, metodologi, dan rangkuman sentimen
                    publik SuaraNetijen.
                </p>
            </div>
            <Link
                :href="admin.blog.create()"
                class="inline-flex items-center gap-1.5 rounded-lg bg-[#087f5b] px-4 py-2 text-xs font-semibold text-white shadow-2xs transition-colors hover:bg-[#066347]"
            >
                <Plus class="size-4" />
                Tulis Artikel Baru
            </Link>
        </div>

        <!-- Filter Tabs & Search -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div
                class="flex rounded-lg border border-neutral-200 bg-neutral-100 p-1 text-xs"
            >
                <button
                    type="button"
                    :class="[
                        'rounded-md px-3 py-1.5 font-semibold transition-all',
                        currentStatus === 'all'
                            ? 'bg-white text-neutral-900 shadow-2xs'
                            : 'text-neutral-600 hover:text-neutral-900',
                    ]"
                    @click="switchTab('all')"
                >
                    Semua ({{ counts.all }})
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded-md px-3 py-1.5 font-semibold transition-all',
                        currentStatus === 'draft'
                            ? 'bg-white text-neutral-900 shadow-2xs'
                            : 'text-neutral-600 hover:text-neutral-900',
                    ]"
                    @click="switchTab('draft')"
                >
                    Draft ({{ counts.draft }})
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded-md px-3 py-1.5 font-semibold transition-all',
                        currentStatus === 'published'
                            ? 'bg-white text-neutral-900 shadow-2xs'
                            : 'text-neutral-600 hover:text-neutral-900',
                    ]"
                    @click="switchTab('published')"
                >
                    Terbit ({{ counts.published }})
                </button>
            </div>

            <form class="relative w-full max-w-xs" @submit.prevent="onSearch">
                <Search
                    class="absolute top-2.5 left-3 size-4 text-neutral-400"
                />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Cari judul atau slug..."
                    class="w-full rounded-lg border border-neutral-200 bg-white py-2 pr-3 pl-9 text-xs focus:border-[#087f5b] focus:outline-hidden"
                />
            </form>
        </div>

        <!-- Table -->
        <div
            class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xs"
        >
            <table class="w-full text-left text-xs text-neutral-600">
                <thead
                    class="border-b border-neutral-200 bg-neutral-50 text-[11px] font-bold text-neutral-700 uppercase"
                >
                    <tr>
                        <th class="px-4 py-3">Artikel</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Penulis</th>
                        <th class="px-4 py-3">Entitas Terkait</th>
                        <th class="px-4 py-3">Tanggal Terbit</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    <tr v-if="posts.data.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-12 text-center text-neutral-400"
                        >
                            <FileText
                                class="mx-auto mb-2 size-8 text-neutral-300"
                            />
                            Belum ada artikel di tab ini.
                        </td>
                    </tr>
                    <tr
                        v-for="post in posts.data"
                        :key="post.id"
                        class="transition-colors hover:bg-neutral-50/50"
                    >
                        <td class="px-4 py-3">
                            <div class="flex items-start gap-3">
                                <div
                                    class="size-12 shrink-0 overflow-hidden rounded-md border border-neutral-200 bg-neutral-100"
                                >
                                    <img
                                        v-if="post.cover_url"
                                        :src="post.cover_url"
                                        :alt="post.title"
                                        class="size-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex size-full items-center justify-center text-neutral-300"
                                    >
                                        <ImageIcon class="size-5" />
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <Link
                                        :href="admin.blog.edit(post.id)"
                                        class="line-clamp-1 font-semibold text-neutral-900 hover:text-[#087f5b]"
                                    >
                                        {{ post.title }}
                                    </Link>
                                    <p
                                        class="font-mono text-[11px] text-neutral-400"
                                    >
                                        /blog/{{ post.slug }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                v-if="post.status === 'published'"
                                class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700"
                            >
                                Terbit
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700"
                            >
                                Draft
                            </span>
                        </td>
                        <td class="px-4 py-3 text-neutral-700">
                            {{ post.author_name }}
                        </td>
                        <td class="px-4 py-3 text-neutral-700">
                            {{ post.entities_count }} entitas
                        </td>
                        <td class="px-4 py-3 text-neutral-500">
                            {{ post.published_at || '-' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a
                                    v-if="post.status === 'published'"
                                    :href="`/blog/${post.slug}`"
                                    target="_blank"
                                    rel="noopener"
                                    class="p-1 text-neutral-400 hover:text-neutral-700"
                                    title="Lihat halaman publik"
                                >
                                    <ExternalLink class="size-3.5" />
                                </a>

                                <button
                                    v-if="post.status === 'published'"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded border border-neutral-300 bg-white px-2 py-1 text-[11px] font-medium text-neutral-700 hover:bg-neutral-50"
                                    @click="unpublish(post.id)"
                                >
                                    <Undo2 class="size-3 text-neutral-500" />
                                    Tarik
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded border border-emerald-300 bg-emerald-50 px-2 py-1 text-[11px] font-medium text-emerald-800 hover:bg-emerald-100"
                                    @click="publish(post.id)"
                                >
                                    <UploadCloud
                                        class="size-3 text-emerald-600"
                                    />
                                    Terbitkan
                                </button>

                                <Link
                                    :href="admin.blog.edit(post.id)"
                                    class="inline-flex items-center rounded border border-neutral-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-neutral-700 hover:bg-neutral-50"
                                >
                                    Edit
                                </Link>

                                <button
                                    v-if="post.status === 'draft'"
                                    type="button"
                                    class="p-1 text-red-500 transition-colors hover:text-red-700"
                                    title="Hapus draft"
                                    @click="destroy(post.id)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div
                v-if="posts.links.length > 3"
                class="flex items-center justify-between border-t border-neutral-200 bg-neutral-50 px-4 py-3"
            >
                <div class="flex gap-1">
                    <template v-for="(link, i) in posts.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="[
                                'rounded px-2.5 py-1 text-xs font-semibold',
                                link.active
                                    ? 'bg-[#087f5b] text-white'
                                    : 'border border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-100',
                            ]"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="px-2.5 py-1 text-xs text-neutral-400"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
