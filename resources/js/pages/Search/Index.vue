<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { index as searchPage } from '@/routes/search';
import { show as showEntity } from '@/routes/entities';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import PublicSeo from '@/components/PublicSeo.vue';

interface CategoryItem {
    id: number;
    name: string;
    slug: string;
}

interface ParentItem {
    id: number;
    name: string;
    slug: string;
}

interface SearchResultItem {
    id: number;
    name: string;
    slug: string;
    type: string;
    type_label: string;
    description: string | null;
    category: CategoryItem;
    parent: ParentItem | null;
    url: string;
    score: number | null;
    opinion_count: number;
    rating: number | null;
    rating_count: number;
    priority_tier: string;
    priority_rank: number;
    match_detail: string | null;
}

interface SearchMeta {
    query: string;
    normalized_query: string;
    total: number;
}

const props = defineProps<{
    query: string;
    results: SearchResultItem[];
    meta: SearchMeta;
    categories: CategoryItem[];
    selectedCategory: string | null;
}>();

const searchInput = ref(props.query || '');
const currentCategory = ref<string | null>(props.selectedCategory || null);

const performSearch = (newQuery?: string, newCategory?: string | null) => {
    const q = newQuery !== undefined ? newQuery : searchInput.value;
    const cat = newCategory !== undefined ? newCategory : currentCategory.value;

    router.get(
        searchPage.url(),
        {
            q: q.trim() || undefined,
            category: cat || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const debouncedSearch = useDebounceFn((val: string) => {
    performSearch(val, currentCategory.value);
}, 300);

// Below this length, wait for more input: a 1-char query is not a useful
// result set and is noise for the search_queries growth-loop log (docs/23).
// An empty (cleared) query is exempt so clearing the box still browses all.
const MIN_LIVE_SEARCH_QUERY_LENGTH = 2;

const handleInput = () => {
    const trimmed = searchInput.value.trim();
    if (trimmed.length > 0 && trimmed.length < MIN_LIVE_SEARCH_QUERY_LENGTH) {
        return;
    }
    debouncedSearch(searchInput.value);
};

const handleCategoryChange = (slug: string | null) => {
    currentCategory.value = slug;
    performSearch(searchInput.value, slug);
};

const handleFormSubmit = (e: Event) => {
    e.preventDefault();
    performSearch(searchInput.value, currentCategory.value);
};

const clearSearch = () => {
    searchInput.value = '';
    performSearch('', currentCategory.value);
};
</script>

<template>
    <PublicLayout>
        <PublicSeo
            :title="
                query ? `Pencarian ${query}` : 'Cari Brand, Produk, dan Layanan'
            "
            :description="
                query
                    ? `Hasil pencarian ${query} di SuaraNetijen: temukan entitas dan ringkasan opini netizen.`
                    : 'Cari brand, produk, dan layanan di Indonesia untuk melihat sentimen publik dan opini netizen.'
            "
            canonical-path="/search"
            :robots="
                query || selectedCategory ? 'noindex, follow' : 'index, follow'
            "
        />

        <!-- Search Header Section -->
        <div class="border-b border-neutral-200 bg-[#eff7eb] py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <p
                    class="mb-2 text-xs font-semibold tracking-widest text-emerald-600 uppercase"
                >
                    Sebelum pilih, cek kata netizen.
                </p>
                <h2 class="mb-5 text-2xl font-bold tracking-tight sm:text-3xl">
                    Temukan yang ingin kamu kenali.
                </h2>
                <form role="search" @submit="handleFormSubmit" class="relative">
                    <div class="relative flex items-center">
                        <svg
                            class="absolute left-4 h-5 w-5 text-neutral-400"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            />
                        </svg>
                        <input
                            v-model="searchInput"
                            aria-label="Cari brand, produk, atau layanan"
                            @input="handleInput"
                            type="search"
                            autofocus
                            placeholder="Cari brand, produk, atau layanan..."
                            class="h-12 min-h-[56px] w-full rounded-xl border border-neutral-300 bg-neutral-50 pr-32 pl-11 text-base text-neutral-900 transition-all placeholder:text-neutral-400 focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:outline-none"
                        />
                        <div class="absolute right-2 flex items-center gap-1">
                            <button
                                v-if="searchInput"
                                type="button"
                                @click="clearSearch"
                                class="rounded-lg p-2 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600"
                                aria-label="Hapus pencarian"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                            <button
                                type="submit"
                                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700"
                            >
                                Cari
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Category Filters -->
                <div
                    class="no-scrollbar mt-4 flex items-center gap-2 overflow-x-auto pb-1 text-sm"
                >
                    <button
                        type="button"
                        @click="handleCategoryChange(null)"
                        :class="[
                            'rounded-full px-3.5 py-1.5 text-xs font-medium whitespace-nowrap transition-colors',
                            currentCategory === null
                                ? 'bg-emerald-600 text-white'
                                : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200',
                        ]"
                    >
                        Semua Kategori
                    </button>
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        type="button"
                        @click="handleCategoryChange(cat.slug)"
                        :class="[
                            'rounded-full px-3.5 py-1.5 text-xs font-medium whitespace-nowrap transition-colors',
                            currentCategory === cat.slug
                                ? 'bg-emerald-600 text-white'
                                : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200',
                        ]"
                    >
                        {{ cat.name }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Search Results Content -->
        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <!-- Results Header & Counter -->
            <div class="mb-6 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">
                        <span v-if="query"
                            >Hasil pencarian untuk "{{ query }}"</span
                        >
                        <span v-else-if="selectedCategory"
                            >Kategori:
                            {{
                                categories.find(
                                    (c) => c.slug === selectedCategory,
                                )?.name || selectedCategory
                            }}</span
                        >
                        <span v-else>Jelajahi Semua Entitas</span>
                    </h1>
                    <p class="mt-1 text-sm text-neutral-500">
                        Ditemukan {{ meta.total }} entitas publik
                    </p>
                </div>
            </div>

            <!-- Results Grid: Single column on mobile <= 640px per docs/04 -->
            <div v-if="results.length > 0" class="grid grid-cols-1 gap-4">
                <div
                    v-for="item in results"
                    :key="item.id"
                    class="group relative flex cursor-pointer flex-col justify-between rounded-xl border border-neutral-200 bg-white p-5 shadow-xs transition-all hover:border-emerald-300 hover:shadow-md"
                >
                    <div
                        class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start"
                    >
                        <div class="space-y-1.5">
                            <!-- Type & Category Badges -->
                            <div
                                class="flex flex-wrap items-center gap-2 text-xs"
                            >
                                <span
                                    class="rounded-md bg-neutral-100 px-2 py-0.5 font-medium text-neutral-700"
                                >
                                    {{ item.type_label }}
                                </span>
                                <span class="text-neutral-400">•</span>
                                <span class="font-medium text-neutral-600">
                                    {{ item.category.name }}
                                </span>
                                <template v-if="item.parent">
                                    <span class="text-neutral-400">•</span>
                                    <span class="text-neutral-500">
                                        Brand:
                                        <Link
                                            :href="
                                                showEntity.url(item.parent.slug)
                                            "
                                            class="hover:underline"
                                            >{{ item.parent.name }}</Link
                                        >
                                    </span>
                                </template>
                            </div>

                            <!-- Entity Title -->
                            <h2
                                class="text-lg font-semibold transition-colors group-hover:text-emerald-600"
                            >
                                <Link
                                    :href="item.url"
                                    class="focus-visible:underline"
                                >
                                    <span
                                        class="absolute inset-0"
                                        aria-hidden="true"
                                    ></span>
                                    {{ item.name }}
                                </Link>
                            </h2>

                            <!-- Description -->
                            <p
                                v-if="item.description"
                                class="line-clamp-2 text-sm text-neutral-600"
                            >
                                {{ item.description }}
                            </p>

                            <!-- Alias match notice if applicable -->
                            <p
                                v-if="
                                    item.match_detail &&
                                    item.match_detail.toLowerCase() !==
                                        item.name.toLowerCase()
                                "
                                class="text-xs text-neutral-500"
                            >
                                Cocok dengan alias:
                                <span class="font-medium text-neutral-700">{{
                                    item.match_detail
                                }}</span>
                            </p>
                        </div>

                        <!-- Public Metrics Cards (Sentimen & Rating) -->
                        <div
                            class="flex shrink-0 items-center justify-between gap-2 border-t border-neutral-100 pt-3 sm:flex-col sm:items-end sm:border-t-0 sm:pt-0"
                        >
                            <!-- Sentimen Netijen Indicator -->
                            <div class="flex flex-col sm:items-end">
                                <div
                                    class="text-[11px] font-medium tracking-wider text-neutral-400 uppercase"
                                >
                                    Sentimen Netijen
                                </div>
                                <div class="mt-0.5 flex items-center gap-1.5">
                                    <span
                                        v-if="item.score !== null"
                                        class="text-lg font-bold text-emerald-600"
                                    >
                                        {{ item.score
                                        }}<span
                                            class="text-xs font-normal text-neutral-400"
                                            >/100</span
                                        >
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-md bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600"
                                    >
                                        Belum cukup opini
                                    </span>
                                </div>
                                <div class="text-[11px] text-neutral-400">
                                    {{
                                        item.opinion_count > 0
                                            ? `${item.opinion_count} opini`
                                            : '0 opini dianalisis'
                                    }}
                                </div>
                            </div>

                            <!-- Arrow indicator -->
                            <div
                                class="hidden items-center gap-1 text-xs font-medium text-emerald-600 transition-transform group-hover:translate-x-0.5 sm:flex"
                            >
                                <span>Lihat detail</span>
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 5l7 7-7 7"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="rounded-2xl border border-dashed border-neutral-300 bg-white p-12 text-center"
            >
                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-neutral-100"
                >
                    <svg
                        class="h-6 w-6 text-neutral-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </div>
                <h3 class="mt-4 text-base font-semibold text-neutral-900">
                    Tidak ada hasil ditemukan
                </h3>
                <p class="mx-auto mt-1 max-w-md text-sm text-neutral-500">
                    <span v-if="query">
                        Tidak ada entitas yang cocok dengan kata kunci "{{
                            query
                        }}". Permintaan ini telah dicatat untuk penambahan
                        entitas baru.
                    </span>
                    <span v-else>
                        Silakan masukkan kata kunci pencarian untuk menemukan
                        brand, produk, atau layanan.
                    </span>
                </p>
                <div
                    class="mt-6 flex flex-wrap items-center justify-center gap-2 text-xs"
                >
                    <span class="text-neutral-500">Coba cari:</span>
                    <button
                        type="button"
                        @click="
                            searchInput = 'samsng a57';
                            performSearch('samsng a57');
                        "
                        class="rounded-lg bg-neutral-100 px-2.5 py-1 text-neutral-700 hover:bg-neutral-200"
                    >
                        samsng a57
                    </button>
                    <button
                        type="button"
                        @click="
                            searchInput = 'vps biznet';
                            performSearch('vps biznet');
                        "
                        class="rounded-lg bg-neutral-100 px-2.5 py-1 text-neutral-700 hover:bg-neutral-200"
                    >
                        vps biznet
                    </button>
                    <button
                        type="button"
                        @click="
                            searchInput = 'indihome';
                            performSearch('indihome');
                        "
                        class="rounded-lg bg-neutral-100 px-2.5 py-1 text-neutral-700 hover:bg-neutral-200"
                    >
                        indihome
                    </button>
                </div>
            </div>
        </main>
    </PublicLayout>
</template>
