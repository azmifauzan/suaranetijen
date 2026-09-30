<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, Bot, CheckCircle2, ExternalLink, Globe, Lock, Save, Trash2, X } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
import admin from '@/routes/admin';

interface CategoryOption {
    id: number;
    name: string;
}

interface ThemeItem {
    id: number;
    display_label: string;
}

const props = defineProps<{
    topic: {
        id: number;
        slug: string;
        keyword: string;
        title: string | null;
        meta_description: string | null;
        intro: string | null;
        category_id: number | null;
        status: string;
        source: string;
        candidate_signal: number;
        published_at: string | null;
        llm_drafted_at: string | null;
        themes: ThemeItem[];
    };
    categories: CategoryOption[];
    preview: {
        entity_count: number;
        is_indexable: boolean;
        qualifying_count: number;
        window: string;
    };
    isSlugLocked: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Topik Landing Pages', href: '/admin/topics' },
            { title: 'Edit Topik', href: '#' },
        ],
    },
});

const form = useForm({
    keyword: props.topic.keyword,
    slug: props.topic.slug,
    category_id: props.topic.category_id,
    title: props.topic.title || '',
    meta_description: props.topic.meta_description || '',
    intro: props.topic.intro || '',
    theme_ids: props.topic.themes.map((t) => t.id),
});

const selectedThemes = ref<ThemeItem[]>([...props.topic.themes]);
const themeSearchQuery = ref('');
const themeSearchResults = ref<ThemeItem[]>([]);
const isSearchingThemes = ref(false);

async function searchThemes() {
    isSearchingThemes.value = true;
    try {
        const params = new URLSearchParams();
        if (form.category_id) {
            params.set('category_id', String(form.category_id));
        }
        if (themeSearchQuery.value) {
            params.set('q', themeSearchQuery.value);
        }
        const res = await fetch(`/admin/topics-theme-search?${params.toString()}`);
        themeSearchResults.value = await res.json();
    } catch {
        themeSearchResults.value = [];
    } finally {
        isSearchingThemes.value = false;
    }
}

function addTheme(t: ThemeItem) {
    if (!selectedThemes.value.some((item) => item.id === t.id)) {
        selectedThemes.value.push(t);
        form.theme_ids = selectedThemes.value.map((item) => item.id);
    }
}

function removeTheme(id: number) {
    selectedThemes.value = selectedThemes.value.filter((t) => t.id !== id);
    form.theme_ids = selectedThemes.value.map((t) => t.id);
}

function save() {
    form.put(admin.topics.update.url(props.topic.id), {
        preserveScroll: true,
    });
}

function publish() {
    router.post(admin.topics.publish(props.topic.id), {}, { preserveScroll: true });
}

function unpublish() {
    if (confirm('Yakin ingin unpublish topik ini? URL publik akan jadi 404.')) {
        router.post(admin.topics.unpublish(props.topic.id), {}, { preserveScroll: true });
    }
}

function regenerate() {
    router.post(admin.topics.regenerate(props.topic.id), {}, { preserveScroll: true });
}

function reject() {
    if (confirm('Tolak topik ini secara permanen?')) {
        router.post(admin.topics.reject(props.topic.id), {}, { preserveScroll: true });
    }
}

onMounted(() => {
    searchThemes();
});

watch(() => form.category_id, () => {
    searchThemes();
});
</script>

<template>
    <Head :title="`Edit: ${topic.keyword} - Admin`" />

    <div class="space-y-6 p-6">
        <!-- Top bar with status and action buttons -->
        <div class="flex flex-col gap-4 border-b border-neutral-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-neutral-900">
                        {{ topic.keyword }}
                    </h1>
                    <span
                        :class="[
                            'rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase',
                            topic.status === 'published'
                                ? 'bg-[#edf8f0] text-[#185b3b]'
                                : topic.status === 'draft'
                                  ? 'bg-amber-100 text-amber-800'
                                  : topic.status === 'rejected'
                                    ? 'bg-red-100 text-red-800'
                                    : 'bg-neutral-100 text-neutral-700',
                        ]"
                    >
                        {{ topic.status }}
                    </span>
                </div>
                <p class="text-xs text-neutral-500">
                    Sumber: {{ topic.source }} • Sinyal: {{ topic.candidate_signal }}
                    <span v-if="topic.published_at">• Dipublish {{ topic.published_at }}</span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50"
                    @click="regenerate"
                >
                    <Bot class="size-3.5" />
                    Regenerate LLM
                </button>

                <a
                    v-if="topic.status === 'published'"
                    :href="`/topik/${topic.slug}`"
                    target="_blank"
                    class="inline-flex items-center gap-1 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50"
                >
                    <ExternalLink class="size-3.5" />
                    Lihat Publik
                </a>

                <button
                    v-if="topic.status === 'published'"
                    type="button"
                    class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                    @click="unpublish"
                >
                    Unpublish
                </button>

                <button
                    v-else
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg bg-[#087f5b] px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-[#076c4d]"
                    @click="publish"
                >
                    <Globe class="size-3.5" />
                    Publish Halaman
                </button>

                <button
                    v-if="topic.status !== 'rejected'"
                    type="button"
                    class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                    @click="reject"
                >
                    Tolak
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left: Form Edit (2 cols) -->
            <form class="space-y-5 rounded-xl border border-neutral-200 bg-white p-6 shadow-2xs lg:col-span-2" @submit.prevent="save">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <!-- Keyword -->
                    <div>
                        <label class="block text-xs font-bold text-neutral-700 uppercase">Keyword</label>
                        <input
                            v-model="form.keyword"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-[#087f5b] focus:outline-hidden"
                            required
                        />
                        <p v-if="form.errors.keyword" class="mt-1 text-xs text-red-600">
                            {{ form.errors.keyword }}
                        </p>
                    </div>

                    <!-- Slug -->
                    <div>
                        <label class="flex items-center gap-1 text-xs font-bold text-neutral-700 uppercase">
                            Slug URL
                            <span v-if="isSlugLocked" class="inline-flex items-center gap-0.5 text-[10px] text-amber-700 font-normal">
                                <Lock class="size-3" /> terkunci setelah publish
                            </span>
                        </label>
                        <input
                            v-model="form.slug"
                            type="text"
                            :disabled="isSlugLocked"
                            :class="[
                                'mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:outline-hidden',
                                isSlugLocked
                                    ? 'bg-neutral-100 text-neutral-500 border-neutral-200 cursor-not-allowed'
                                    : 'border-neutral-300 focus:border-[#087f5b]',
                            ]"
                            required
                        />
                        <p v-if="form.errors.slug" class="mt-1 text-xs text-red-600">
                            {{ form.errors.slug }}
                        </p>
                    </div>
                </div>

                <!-- Category -->
                <div>
                    <label class="block text-xs font-bold text-neutral-700 uppercase">Kategori (Wajib Saat Publish)</label>
                    <select
                        v-model="form.category_id"
                        class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-[#087f5b] focus:outline-hidden"
                    >
                        <option :value="null">-- Pilih Kategori --</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">
                            {{ c.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.category_id" class="mt-1 text-xs text-red-600">
                        {{ form.errors.category_id }}
                    </p>
                </div>

                <!-- Themes Picker -->
                <div>
                    <label class="block text-xs font-bold text-neutral-700 uppercase">Tema Terkait (>= 1 Tema Wajib Publish)</label>
                    <!-- Selected themes tags -->
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-for="t in selectedThemes"
                            :key="t.id"
                            class="inline-flex items-center gap-1 rounded-md bg-[#edf8f0] px-2.5 py-1 text-xs font-medium text-[#185b3b]"
                        >
                            {{ t.display_label }}
                            <button type="button" @click="removeTheme(t.id)">
                                <X class="size-3 hover:text-red-500" />
                            </button>
                        </span>
                        <span v-if="selectedThemes.length === 0" class="text-xs italic text-neutral-400">
                            Belum ada tema yang dipilih.
                        </span>
                    </div>

                    <!-- Async theme search input -->
                    <div class="mt-3 flex gap-2">
                        <input
                            v-model="themeSearchQuery"
                            type="text"
                            placeholder="Cari tema untuk ditambahkan..."
                            class="w-full rounded-lg border border-neutral-300 px-3 py-1.5 text-xs focus:border-[#087f5b] focus:outline-hidden"
                            @keyup.enter.prevent="searchThemes"
                        />
                        <button
                            type="button"
                            class="rounded-lg border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50"
                            @click="searchThemes"
                        >
                            Cari
                        </button>
                    </div>

                    <!-- Search results suggestions -->
                    <div v-if="themeSearchResults.length > 0" class="mt-2 flex flex-wrap gap-1.5 rounded-lg border border-neutral-200 bg-neutral-50 p-2.5">
                        <span class="w-full text-[11px] font-semibold text-neutral-500">Klik untuk menambah tema:</span>
                        <button
                            v-for="res in themeSearchResults"
                            :key="res.id"
                            type="button"
                            class="rounded-md border border-neutral-300 bg-white px-2 py-0.5 text-xs text-neutral-700 hover:bg-[#edf8f0] hover:text-[#185b3b]"
                            @click="addTheme(res)"
                        >
                            + {{ res.display_label }}
                        </button>
                    </div>
                </div>

                <!-- Title (Max 60 chars) -->
                <div>
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-neutral-700 uppercase">Judul (Title H1 & SEO, Max 60 Karakter)</label>
                        <span
                            :class="[
                                'text-xs',
                                form.title.length > 60 ? 'text-red-600 font-bold' : 'text-neutral-500',
                            ]"
                        >
                            {{ form.title.length }}/60
                        </span>
                    </div>
                    <input
                        v-model="form.title"
                        type="text"
                        maxlength="60"
                        placeholder="Contoh: Rekomendasi VPS Murah Pilihan Netizen"
                        class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-[#087f5b] focus:outline-hidden"
                    />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">
                        {{ form.errors.title }}
                    </p>
                </div>

                <!-- Meta Description (Max 155 chars) -->
                <div>
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-neutral-700 uppercase">Meta Description (Max 155 Karakter)</label>
                        <span
                            :class="[
                                'text-xs',
                                form.meta_description.length > 155 ? 'text-red-600 font-bold' : 'text-neutral-500',
                            ]"
                        >
                            {{ form.meta_description.length }}/155
                        </span>
                    </div>
                    <textarea
                        v-model="form.meta_description"
                        rows="2"
                        maxlength="155"
                        placeholder="Ringkasan singkat apa yang dibicarakan netizen..."
                        class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-[#087f5b] focus:outline-hidden"
                    />
                    <p v-if="form.errors.meta_description" class="mt-1 text-xs text-red-600">
                        {{ form.errors.meta_description }}
                    </p>
                </div>

                <!-- Intro (Max 1000 chars) -->
                <div>
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-neutral-700 uppercase">Intro Teks (2-3 Paragraf, Max 1000 Karakter)</label>
                        <span
                            :class="[
                                'text-xs',
                                form.intro.length > 1000 ? 'text-red-600 font-bold' : 'text-neutral-500',
                            ]"
                        >
                            {{ form.intro.length }}/1000
                        </span>
                    </div>
                    <textarea
                        v-model="form.intro"
                        rows="4"
                        maxlength="1000"
                        placeholder="Paragraf pembuka topik..."
                        class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-[#087f5b] focus:outline-hidden"
                    />
                    <p v-if="form.errors.intro" class="mt-1 text-xs text-red-600">
                        {{ form.errors.intro }}
                    </p>
                </div>

                <!-- Save button -->
                <div class="border-t border-neutral-100 pt-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#087f5b] px-4 py-2 text-sm font-semibold text-white hover:bg-[#076c4d]"
                    >
                        <Save class="size-4" />
                        Simpan Perubahan Draft
                    </button>
                </div>
            </form>

            <!-- Right: Preview Card & Indexability Info (1 col) -->
            <div class="space-y-6">
                <!-- Indexability Status Box -->
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-2xs">
                    <h3 class="text-xs font-bold text-neutral-500 uppercase tracking-wider">
                        Status Kelayakan SEO
                    </h3>

                    <div class="mt-3 flex items-start gap-3">
                        <CheckCircle2 v-if="preview.is_indexable" class="size-6 text-[#185b3b] shrink-0" />
                        <AlertCircle v-else class="size-6 text-amber-500 shrink-0" />

                        <div>
                            <div class="font-bold text-neutral-900">
                                {{ preview.is_indexable ? 'Layak Diindeks (Index)' : 'Belum Layak Index (Noindex)' }}
                            </div>
                            <p class="mt-1 text-xs text-neutral-500">
                                {{ preview.is_indexable
                                    ? 'Halaman memenuhi syarat minimal entitas & opini untuk terbit di Google dan sitemap.'
                                    : 'Kurang dari 3 entitas dengan minimal 3 opini tema. Halaman tetap aktif dengan meta noindex.' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2 border-t border-neutral-100 pt-3 text-xs text-neutral-600">
                        <div class="flex justify-between">
                            <span>Entitas tampil:</span>
                            <span class="font-semibold text-neutral-900">{{ preview.entity_count }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Entitas memenuhi syarat:</span>
                            <span class="font-semibold text-neutral-900">{{ preview.qualifying_count }} / 3</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Window data:</span>
                            <span class="font-semibold text-neutral-900">{{ preview.window }}</span>
                        </div>
                    </div>
                </div>

                <!-- Editorial Copy Guidelines Box -->
                <div class="rounded-xl border border-neutral-200 bg-[#fbfcf9] p-5 text-xs text-neutral-600">
                    <h4 class="font-bold text-[#18392d]">Pedoman Penulisan (Copy Guard)</h4>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        <li>Dilarang memakai kata superlatif: "terbaik", "terburuk".</li>
                        <li>Dilarang memakai angka persentase ("%") atau kata "persen".</li>
                        <li>Dilarang menyertakan akun mention (@) atau URL website.</li>
                        <li>Gunakan frasa: "paling sering dibicarakan netizen".</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
