<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useDebounceFn, onClickOutside } from '@vueuse/core';
import { dashboard, login, register } from '@/routes';

interface CategoryItem {
    id: number;
    name: string;
    slug: string;
}

interface AutocompleteItem {
    id: number;
    name: string;
    slug: string;
    type_label: string;
    category: {
        name: string;
    };
    match_detail: string | null;
    url: string;
}

defineProps<{
    categories: CategoryItem[];
}>();

const searchQuery = ref('');
const suggestions = ref<AutocompleteItem[]>([]);
const isSearching = ref(false);
const showDropdown = ref(false);
const searchContainerRef = ref<HTMLElement | null>(null);

onClickOutside(searchContainerRef, () => {
    showDropdown.value = false;
});

// Below this length, skip the request entirely: a 1-char autocomplete lookup
// is noise for the search_queries growth-loop log (docs/23) and never useful
// as a suggestion anyway.
const MIN_SUGGESTION_QUERY_LENGTH = 2;

const fetchSuggestions = useDebounceFn(async (q: string) => {
    const trimmed = q.trim();
    if (trimmed.length < MIN_SUGGESTION_QUERY_LENGTH) {
        suggestions.value = [];
        showDropdown.value = false;
        return;
    }

    isSearching.value = true;
    try {
        const response = await fetch(
            `/api/search?q=${encodeURIComponent(trimmed)}&limit=6`,
        );
        if (response.ok) {
            const data = await response.json();
            suggestions.value = data.data || [];
            showDropdown.value = suggestions.value.length > 0;
        }
    } catch {
        suggestions.value = [];
    } finally {
        isSearching.value = false;
    }
}, 200);

const onInput = () => {
    fetchSuggestions(searchQuery.value);
};

const submitSearch = () => {
    showDropdown.value = false;
    if (searchQuery.value.trim()) {
        router.get('/search', { q: searchQuery.value.trim() });
    } else {
        router.get('/search');
    }
};

const selectSuggestion = (url: string) => {
    showDropdown.value = false;
    router.visit(url);
};

const trendingKeywords = [
    { label: 'samsng a57', query: 'samsng a57' },
    { label: 'vps biznet', query: 'vps biznet' },
    { label: 'indihome', query: 'indihome' },
    { label: 'tokopedia', query: 'tokopedia' },
    { label: 'idcloudhost', query: 'idcloudhost' },
    { label: 'samsung galaxy', query: 'samsung galaxy' },
];
</script>

<template>
    <Head
        title="SuaraNetijen - Apa Kata Netijen? Indeks Sentimen Publik Indonesia"
    >
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div
        class="flex min-h-screen flex-col justify-between bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <!-- Top Navigation -->
        <header
            class="w-full border-b border-neutral-200/80 bg-white/80 backdrop-blur-sm dark:border-neutral-800/80 dark:bg-neutral-900/80"
        >
            <div
                class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3.5 sm:px-6"
            >
                <Link
                    href="/"
                    class="flex items-center gap-2 text-xl font-black tracking-tight text-emerald-600 dark:text-emerald-400"
                >
                    <span>SUARANETIJEN</span>
                </Link>

                <nav class="flex items-center gap-3 text-sm">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-lg border border-neutral-200 bg-white px-3.5 py-1.5 font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-lg px-3 py-1.5 font-medium text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100"
                        >
                            Masuk
                        </Link>
                        <Link
                            :href="register()"
                            class="rounded-lg bg-emerald-600 px-3.5 py-1.5 font-medium text-white hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600"
                        >
                            Daftar
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Main Hero & Search Section -->
        <main
            class="mx-auto flex w-full max-w-4xl flex-col items-center justify-center px-4 py-16 sm:px-6 sm:py-24"
        >
            <div class="w-full max-w-2xl text-center">
                <!-- Branding per docs/04 -->
                <span
                    class="mb-2 inline-block text-xs font-bold tracking-widest text-emerald-600 uppercase dark:text-emerald-400"
                >
                    SUARANETIJEN
                </span>
                <h1 class="text-3xl font-black tracking-tight sm:text-5xl">
                    Apa kata netijen?
                </h1>
                <p
                    class="mt-3 text-base text-neutral-600 sm:text-lg dark:text-neutral-400"
                >
                    Indeks opini dan sentimen publik berbasis data agregasi
                    crawler untuk brand, produk, dan layanan di Indonesia.
                </p>

                <!-- Search Input Container with Autocomplete -->
                <div ref="searchContainerRef" class="relative mt-8 text-left">
                    <form @submit.prevent="submitSearch" class="relative">
                        <div class="relative flex items-center">
                            <svg
                                class="absolute left-4 h-5 w-5 text-neutral-400 dark:text-neutral-500"
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
                                v-model="searchQuery"
                                @input="onInput"
                                @focus="onInput"
                                type="search"
                                placeholder="Cari brand, produk, atau layanan..."
                                class="h-14 min-h-[48px] w-full rounded-2xl border border-neutral-300 bg-white pr-28 pl-12 text-base text-neutral-900 shadow-sm transition-all placeholder:text-neutral-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/15 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800 dark:text-white dark:placeholder:text-neutral-500 dark:focus:border-emerald-400"
                            />
                            <button
                                type="submit"
                                class="absolute right-2.5 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-xs hover:bg-emerald-700 focus:outline-none dark:bg-emerald-500 dark:hover:bg-emerald-600"
                            >
                                Cari
                            </button>
                        </div>
                    </form>

                    <!-- Autocomplete Dropdown -->
                    <div
                        v-if="showDropdown && suggestions.length > 0"
                        class="absolute top-full right-0 left-0 z-50 mt-2 overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-xl dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <div class="p-2">
                            <div
                                class="px-3 py-1.5 text-[11px] font-semibold tracking-wider text-neutral-400 uppercase dark:text-neutral-500"
                            >
                                Rekomendasi Entitas
                            </div>
                            <button
                                v-for="item in suggestions"
                                :key="item.id"
                                type="button"
                                @click="selectSuggestion(item.url)"
                                class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-800"
                            >
                                <div class="space-y-0.5">
                                    <div
                                        class="flex items-center gap-2 font-medium text-neutral-900 dark:text-neutral-100"
                                    >
                                        <span>{{ item.name }}</span>
                                        <span
                                            class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400"
                                        >
                                            {{ item.type_label }}
                                        </span>
                                    </div>
                                    <div
                                        class="flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400"
                                    >
                                        <span>{{ item.category.name }}</span>
                                        <template
                                            v-if="
                                                item.match_detail &&
                                                item.match_detail.toLowerCase() !==
                                                    item.name.toLowerCase()
                                            "
                                        >
                                            <span>•</span>
                                            <span
                                                >alias:
                                                {{ item.match_detail }}</span
                                            >
                                        </template>
                                    </div>
                                </div>
                                <svg
                                    class="h-4 w-4 text-neutral-400"
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
                            </button>
                        </div>
                        <div
                            class="dark:bg-neutral-850 border-t border-neutral-100 bg-neutral-50 p-2.5 text-center text-xs dark:border-neutral-800"
                        >
                            <button
                                type="button"
                                @click="submitSearch"
                                class="font-medium text-emerald-600 hover:underline dark:text-emerald-400"
                            >
                                Lihat semua hasil untuk "{{ searchQuery }}" →
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Trending Searches -->
                <div
                    class="mt-6 flex flex-wrap items-center justify-center gap-2 text-xs text-neutral-500"
                >
                    <span
                        class="font-medium text-neutral-400 dark:text-neutral-500"
                        >Pencarian populer:</span
                    >
                    <button
                        v-for="kw in trendingKeywords"
                        :key="kw.query"
                        type="button"
                        @click="
                            searchQuery = kw.query;
                            submitSearch();
                        "
                        class="rounded-full border border-neutral-200 bg-white px-3 py-1 text-neutral-700 transition-colors hover:border-emerald-300 hover:text-emerald-600 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:border-emerald-700 dark:hover:text-emerald-400"
                    >
                        {{ kw.label }}
                    </button>
                </div>
            </div>

            <!-- Categories Section per docs/04 -->
            <div class="mt-16 w-full">
                <div class="mb-4 flex items-center justify-between">
                    <h2
                        class="text-sm font-semibold tracking-wider text-neutral-400 uppercase dark:text-neutral-500"
                    >
                        Kategori Populer
                    </h2>
                    <Link
                        href="/search"
                        class="text-xs font-medium text-emerald-600 hover:underline dark:text-emerald-400"
                    >
                        Semua Entitas →
                    </Link>
                </div>

                <div
                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4"
                >
                    <Link
                        v-for="cat in categories"
                        :key="cat.id"
                        :href="`/search?category=${cat.slug}`"
                        class="flex flex-col justify-between rounded-xl border border-neutral-200 bg-white p-4 transition-all hover:border-emerald-300 hover:shadow-sm dark:border-neutral-800 dark:bg-neutral-900 dark:hover:border-emerald-700"
                    >
                        <span
                            class="text-sm font-medium text-neutral-900 dark:text-neutral-100"
                        >
                            {{ cat.name }}
                        </span>
                        <span
                            class="mt-2 text-xs text-emerald-600 dark:text-emerald-400"
                        >
                            Jelajahi →
                        </span>
                    </Link>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer
            class="border-t border-neutral-200 bg-white py-6 text-center text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400"
        >
            <div
                class="mx-auto flex max-w-5xl flex-col items-center justify-between gap-2 px-4 sm:flex-row sm:px-6"
            >
                <div>
                    © 2026 SuaraNetijen. Platform Indeks Sentimen Publik
                    Indonesia.
                </div>
                <div class="flex items-center gap-4">
                    <Link href="/search" class="hover:underline"
                        >Pencarian</Link
                    >
                    <span>•</span>
                    <span class="text-neutral-400">Metodologi</span>
                </div>
            </div>
        </footer>
    </div>
</template>
