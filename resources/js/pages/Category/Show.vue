<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface CategoryData {
    id: number;
    name: string;
    slug: string;
    total_entities: number;
}

interface RankedItem {
    rank: number;
    id: number;
    name: string;
    slug: string;
    type: string;
    type_label: string;
    score: number;
    opinion_count: number;
}

interface DiscussedItem {
    id: number;
    name: string;
    slug: string;
    type: string;
    type_label: string;
    opinion_count: number;
    score: number | null;
}

interface RecentItem {
    id: number;
    name: string;
    slug: string;
    type: string;
    type_label: string;
    opinion_count: number;
    score: number | null;
    updated_at?: string;
}

interface FilteredItem {
    id: number;
    name: string;
    slug: string;
    type: string;
    description: string | null;
}

interface OtherCategory {
    id: number;
    name: string;
    slug: string;
}

const props = defineProps<{
    category: CategoryData;
    topSentimen: RankedItem[];
    mostDiscussed: DiscussedItem[];
    recentlyUpdated: RecentItem[];
    filteredEntities: FilteredItem[] | null;
    otherCategories: OtherCategory[];
    searchQuery: string | null;
}>();

const query = ref(props.searchQuery || '');

function handleSearch() {
    if (query.value.trim()) {
        router.get(`/category/${props.category.slug}`, { q: query.value.trim() }, { preserveState: true });
    } else {
        router.get(`/category/${props.category.slug}`, {}, { preserveState: true });
    }
}
</script>

<template>
    <Head :title="`${category.name}: Sentimen Netijen & Review`">
        <meta
            name="description"
            :content="`Indeks sentimen dan opini netijen publik untuk brand, produk, dan layanan dalam kategori ${category.name} di SuaraNetijen.`"
        />
    </Head>

    <div class="min-h-screen bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <!-- Header -->
        <header class="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4 sm:px-6">
                <Link href="/" class="flex items-center gap-2 text-lg font-bold text-emerald-600 dark:text-emerald-400">
                    <span>SuaraNetijen</span>
                </Link>
                <div class="flex items-center gap-4 text-sm">
                    <Link
                        href="/search"
                        class="flex items-center gap-1.5 text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <span>Cari Entitas</span>
                    </Link>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            <!-- Breadcrumbs -->
            <nav class="mb-4 flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                <Link href="/" class="hover:underline">Beranda</Link>
                <span>/</span>
                <span>Kategori</span>
                <span>/</span>
                <span class="font-medium text-neutral-800 dark:text-neutral-200">{{ category.name }}</span>
            </nav>

            <!-- Category Header Card -->
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8 dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold tracking-wider text-emerald-600 uppercase dark:text-emerald-400">
                            Kategori
                        </div>
                        <h1 class="mt-1 text-2xl font-black tracking-tight sm:text-3xl text-neutral-900 dark:text-neutral-100">
                            {{ category.name }}
                        </h1>
                        <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
                            Indeks sentimen dan opini netijen publik untuk {{ category.total_entities }} entitas dalam kategori {{ category.name }}.
                        </p>
                    </div>
                    <Link
                        :href="`/top/${category.slug}`"
                        class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600"
                    >
                        Lihat Ranking Lengkap →
                    </Link>
                </div>

                <!-- Search/Filter Input within category (docs/04) -->
                <div class="mt-6 border-t border-neutral-100 pt-6 dark:border-neutral-800">
                    <form @submit.prevent="handleSearch" class="relative max-w-lg">
                        <div class="relative flex items-center">
                            <input
                                v-model="query"
                                type="text"
                                :placeholder="`Cari dalam ${category.name}...`"
                                class="h-12 min-h-[48px] w-full rounded-xl border border-neutral-300 bg-neutral-50 pr-24 pl-4 text-sm text-neutral-900 shadow-xs focus:border-emerald-500 focus:bg-white focus:outline-none dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                            />
                            <button
                                type="submit"
                                class="absolute right-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700"
                            >
                                Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Filtered Search Results (if user searched) -->
            <div v-if="filteredEntities !== null" class="mt-8">
                <h2 class="text-sm font-bold tracking-wider text-neutral-500 uppercase dark:text-neutral-400">
                    Hasil Filter untuk "{{ searchQuery }}" ({{ filteredEntities.length }})
                </h2>
                <div v-if="filteredEntities.length > 0" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Link
                        v-for="item in filteredEntities"
                        :key="item.id"
                        :href="`/e/${item.slug}`"
                        class="rounded-xl border border-neutral-200 bg-white p-4 transition hover:border-emerald-400 dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <div class="font-bold text-neutral-900 dark:text-neutral-100">{{ item.name }}</div>
                        <p v-if="item.description" class="mt-1 text-xs text-neutral-500 line-clamp-2">{{ item.description }}</p>
                    </Link>
                </div>
                <div v-else class="mt-4 rounded-xl border border-dashed border-neutral-300 bg-white p-8 text-center text-sm text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900">
                    Tidak ditemukan entitas dengan nama tersebut di kategori ini.
                </div>
            </div>

            <!-- Standard Category Sections per docs/04 -->
            <template v-else>
                <!-- 1. Top Sentimen -->
                <div class="mt-8">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">
                                Top Sentimen Netijen
                            </h2>
                            <p class="text-xs text-neutral-500">
                                Entitas dengan sentimen tertinggi di {{ category.name }}
                            </p>
                        </div>
                        <Link :href="`/top/${category.slug}`" class="text-xs font-semibold text-emerald-600 hover:underline dark:text-emerald-400">
                            Semua Ranking →
                        </Link>
                    </div>

                    <div v-if="topSentimen.length > 0" class="space-y-3">
                        <div
                            v-for="item in topSentimen"
                            :key="item.id"
                            class="flex flex-col gap-3 rounded-xl border border-neutral-200 bg-white p-4 transition hover:border-emerald-400 sm:flex-row sm:items-center sm:justify-between dark:border-neutral-800 dark:bg-neutral-900"
                        >
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-neutral-100 text-xs font-black text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                    {{ item.rank }}
                                </span>
                                <div>
                                    <Link :href="`/e/${item.slug}`" class="font-bold text-neutral-900 hover:text-emerald-600 hover:underline dark:text-neutral-100 dark:hover:text-emerald-400">
                                        {{ item.name }}
                                    </Link>
                                    <div class="text-xs text-neutral-400">
                                        {{ item.type_label }} • {{ item.opinion_count }} opini
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between sm:justify-end gap-2">
                                <span class="text-xs text-neutral-400 sm:hidden">Sentimen Netijen</span>
                                <span
                                    class="inline-flex items-center rounded-lg px-2.5 py-1 text-sm font-black"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300': item.score >= 70,
                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300': item.score >= 50 && item.score < 70,
                                        'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300': item.score < 50,
                                    }"
                                >
                                    {{ item.score }} / 100
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="rounded-xl border border-dashed border-neutral-300 bg-white p-6 text-center text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900">
                        Belum ada entitas di kategori ini yang memenuhi batas minimal 30 opini netijen.
                    </div>
                </div>

                <!-- 2. Most Discussed & 3. Recently Updated (2 cols) -->
                <div class="mt-8 grid gap-6 sm:grid-cols-2">
                    <!-- Most Discussed (Paling Banyak Dibahas) -->
                    <div>
                        <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">
                            Paling Banyak Dibahas
                        </h2>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            Volume opini publik terbesar dalam 1 tahun terakhir
                        </p>

                        <div class="mt-4 space-y-2.5">
                            <Link
                                v-for="item in mostDiscussed"
                                :key="item.id"
                                :href="`/e/${item.slug}`"
                                class="flex items-center justify-between rounded-xl border border-neutral-200 bg-white p-3.5 transition hover:border-emerald-400 dark:border-neutral-800 dark:bg-neutral-900"
                            >
                                <div>
                                    <div class="text-sm font-bold text-neutral-900 dark:text-neutral-100">
                                        {{ item.name }}
                                    </div>
                                    <span class="text-[10px] text-neutral-400 uppercase">
                                        {{ item.type_label }}
                                    </span>
                                </div>
                                <div class="text-right text-xs">
                                    <div class="font-semibold text-neutral-700 dark:text-neutral-300">
                                        {{ item.opinion_count }} opini
                                    </div>
                                    <div v-if="item.score !== null" class="text-[10px] text-neutral-400">
                                        skor: {{ item.score }}
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>

                    <!-- Recently Updated (Pembaruan Terkini) -->
                    <div>
                        <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">
                            Pembaruan Terkini
                        </h2>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            Observasi yang baru diolah oleh crawler
                        </p>

                        <div class="mt-4 space-y-2.5">
                            <Link
                                v-for="item in recentlyUpdated"
                                :key="item.id"
                                :href="`/e/${item.slug}`"
                                class="flex items-center justify-between rounded-xl border border-neutral-200 bg-white p-3.5 transition hover:border-emerald-400 dark:border-neutral-800 dark:bg-neutral-900"
                            >
                                <div>
                                    <div class="text-sm font-bold text-neutral-900 dark:text-neutral-100">
                                        {{ item.name }}
                                    </div>
                                    <span class="text-[10px] text-neutral-400 uppercase">
                                        {{ item.type_label }}
                                    </span>
                                </div>
                                <div class="text-right text-xs">
                                    <div v-if="item.score !== null" class="font-semibold text-emerald-600 dark:text-emerald-400">
                                        {{ item.score }}/100
                                    </div>
                                    <div v-if="item.updated_at" class="text-[10px] text-neutral-400">
                                        {{ item.updated_at }}
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Other Categories -->
            <div class="mt-12 border-t border-neutral-200 pt-8 dark:border-neutral-800">
                <div class="text-xs font-semibold tracking-wider text-neutral-400 uppercase dark:text-neutral-500">
                    Kategori Lainnya:
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Link
                        v-for="cat in otherCategories"
                        :key="cat.id"
                        :href="`/category/${cat.slug}`"
                        class="rounded-lg bg-neutral-200/80 px-3 py-1.5 text-xs font-medium text-neutral-700 transition hover:bg-neutral-300 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700"
                    >
                        {{ cat.name }}
                    </Link>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="mt-16 border-t border-neutral-200 bg-white py-8 text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400">
            <div class="mx-auto flex max-w-5xl flex-col items-center justify-between gap-4 px-4 sm:flex-row sm:px-6">
                <div>
                    © 2026 SuaraNetijen. Indeks Sentimen Publik Indonesia.
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <Link href="/search" class="hover:underline">Pencarian</Link>
                    <Link href="/methodology" class="hover:underline">Metodologi</Link>
                    <Link href="/sources" class="hover:underline">Sumber Data</Link>
                    <Link href="/about" class="hover:underline">Tentang Kami</Link>
                    <Link href="/terms" class="hover:underline">Ketentuan</Link>
                    <Link href="/privacy" class="hover:underline">Privasi</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
