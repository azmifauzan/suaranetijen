<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home } from '@/routes';
import { show as showEntity } from '@/routes/entities';
import { show as showCategory } from '@/routes/categories';
import { show as showRanking } from '@/routes/rankings';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PublicSeo from '@/components/PublicSeo.vue';

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
        router.get(
            showCategory.url(props.category.slug),
            { q: query.value.trim() },
            { preserveState: true },
        );
    } else {
        router.get(
            showCategory.url(props.category.slug),
            {},
            { preserveState: true },
        );
    }
}
</script>

<template>
    <PublicLayout>
        <PublicSeo
            :title="`${category.name}: Sentimen Netizen dan Review`"
            :description="`Lihat sentimen publik, opini netizen, dan entitas dalam kategori ${category.name} di SuaraNetijen.`"
            :canonical-path="`/category/${category.slug}`"
            :robots="searchQuery ? 'noindex, follow' : 'index, follow'"
        />

        <!-- Main Content -->
        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <!-- Breadcrumbs -->
            <nav class="mb-4 flex items-center gap-2 text-xs text-neutral-500">
                <Link :href="home()" class="hover:underline">Beranda</Link>
                <span>/</span>
                <span>Kategori</span>
                <span>/</span>
                <span class="font-medium text-neutral-800">{{
                    category.name
                }}</span>
            </nav>

            <!-- Category Header Card -->
            <div
                class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8"
            >
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div
                            class="text-xs font-semibold tracking-wider text-emerald-600 uppercase"
                        >
                            Kategori
                        </div>
                        <h1
                            class="mt-1 text-2xl font-black tracking-tight text-neutral-900 sm:text-3xl"
                        >
                            {{ category.name }}
                        </h1>
                        <p class="mt-2 text-sm text-neutral-600">
                            Indeks sentimen dan opini publik dari netizen untuk
                            {{ category.total_entities }} entitas dalam kategori
                            {{ category.name }}.
                        </p>
                    </div>
                    <Link
                        :href="showRanking.url(category.slug)"
                        class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                    >
                        Lihat Ranking Lengkap →
                    </Link>
                </div>

                <!-- Search/Filter Input within category (docs/04) -->
                <div class="mt-6 border-t border-neutral-100 pt-6">
                    <form
                        @submit.prevent="handleSearch"
                        class="relative max-w-lg"
                    >
                        <div class="relative flex items-center">
                            <input
                                v-model="query"
                                type="text"
                                :placeholder="`Cari dalam ${category.name}...`"
                                class="h-12 min-h-[48px] w-full rounded-xl border border-neutral-300 bg-neutral-50 pr-24 pl-4 text-sm text-neutral-900 shadow-xs focus:border-emerald-500 focus:bg-white focus:outline-none"
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
                <h2
                    class="text-sm font-bold tracking-wider text-neutral-500 uppercase"
                >
                    Hasil Filter untuk "{{ searchQuery }}" ({{
                        filteredEntities.length
                    }})
                </h2>
                <div
                    v-if="filteredEntities.length > 0"
                    class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2"
                >
                    <Link
                        v-for="item in filteredEntities"
                        :key="item.id"
                        :href="showEntity.url(item.slug)"
                        class="rounded-xl border border-neutral-200 bg-white p-4 transition hover:border-emerald-400"
                    >
                        <div class="font-bold text-neutral-900">
                            {{ item.name }}
                        </div>
                        <p
                            v-if="item.description"
                            class="mt-1 line-clamp-2 text-xs text-neutral-500"
                        >
                            {{ item.description }}
                        </p>
                    </Link>
                </div>
                <div
                    v-else
                    class="mt-4 rounded-xl border border-dashed border-neutral-300 bg-white p-8 text-center text-sm text-neutral-500"
                >
                    Tidak ditemukan entitas dengan nama tersebut di kategori
                    ini.
                </div>
            </div>

            <!-- Standard Category Sections per docs/04 -->
            <template v-else>
                <!-- 1. Top Sentimen -->
                <div class="mt-8">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-neutral-900">
                                Top Sentimen Netijen
                            </h2>
                            <p class="text-xs text-neutral-500">
                                Entitas dengan sentimen tertinggi di
                                {{ category.name }}
                            </p>
                        </div>
                        <Link
                            :href="showRanking.url(category.slug)"
                            class="text-xs font-semibold text-emerald-600 hover:underline"
                        >
                            Semua Ranking →
                        </Link>
                    </div>

                    <div v-if="topSentimen.length > 0" class="space-y-3">
                        <div
                            v-for="item in topSentimen"
                            :key="item.id"
                            class="flex flex-col gap-3 rounded-xl border border-neutral-200 bg-white p-4 transition hover:border-emerald-400 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-neutral-100 text-xs font-black text-neutral-700"
                                >
                                    {{ item.rank }}
                                </span>
                                <div>
                                    <Link
                                        :href="showEntity.url(item.slug)"
                                        class="font-bold text-neutral-900 hover:text-emerald-600 hover:underline"
                                    >
                                        {{ item.name }}
                                    </Link>
                                    <div class="text-xs text-neutral-400">
                                        {{ item.type_label }} •
                                        {{ item.opinion_count }} opini
                                    </div>
                                </div>
                            </div>
                            <div
                                class="flex items-center justify-between gap-2 sm:justify-end"
                            >
                                <span class="text-xs text-neutral-400 sm:hidden"
                                    >Sentimen Netijen</span
                                >
                                <span
                                    class="inline-flex items-center rounded-lg px-2.5 py-1 text-sm font-black"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800':
                                            item.score >= 70,
                                        'bg-amber-100 text-amber-800':
                                            item.score >= 50 && item.score < 70,
                                        'bg-rose-100 text-rose-800':
                                            item.score < 50,
                                    }"
                                >
                                    {{ item.score }} / 100
                                </span>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="rounded-xl border border-dashed border-neutral-300 bg-white p-6 text-center text-xs text-neutral-500"
                    >
                        Belum ada entitas di kategori ini yang memenuhi batas
                        minimal 30 opini netizen.
                    </div>
                </div>

                <!-- 2. Most Discussed & 3. Recently Updated (2 cols) -->
                <div class="mt-8 grid gap-6 sm:grid-cols-2">
                    <!-- Most Discussed (Paling Banyak Dibahas) -->
                    <div>
                        <h2 class="text-base font-bold text-neutral-900">
                            Paling Banyak Dibahas
                        </h2>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            Volume opini publik terbesar dalam 1 tahun terakhir
                        </p>

                        <div class="mt-4 space-y-2.5">
                            <Link
                                v-for="item in mostDiscussed"
                                :key="item.id"
                                :href="showEntity.url(item.slug)"
                                class="flex items-center justify-between rounded-xl border border-neutral-200 bg-white p-3.5 transition hover:border-emerald-400"
                            >
                                <div>
                                    <div
                                        class="text-sm font-bold text-neutral-900"
                                    >
                                        {{ item.name }}
                                    </div>
                                    <span
                                        class="text-[10px] text-neutral-400 uppercase"
                                    >
                                        {{ item.type_label }}
                                    </span>
                                </div>
                                <div class="text-right text-xs">
                                    <div class="font-semibold text-neutral-700">
                                        {{ item.opinion_count }} opini
                                    </div>
                                    <div
                                        v-if="item.score !== null"
                                        class="text-[10px] text-neutral-400"
                                    >
                                        skor: {{ item.score }}
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>

                    <!-- Recently Updated (Pembaruan Terkini) -->
                    <div>
                        <h2 class="text-base font-bold text-neutral-900">
                            Pembaruan Terkini
                        </h2>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            Observasi yang baru diolah oleh crawler
                        </p>

                        <div class="mt-4 space-y-2.5">
                            <Link
                                v-for="item in recentlyUpdated"
                                :key="item.id"
                                :href="showEntity.url(item.slug)"
                                class="flex items-center justify-between rounded-xl border border-neutral-200 bg-white p-3.5 transition hover:border-emerald-400"
                            >
                                <div>
                                    <div
                                        class="text-sm font-bold text-neutral-900"
                                    >
                                        {{ item.name }}
                                    </div>
                                    <span
                                        class="text-[10px] text-neutral-400 uppercase"
                                    >
                                        {{ item.type_label }}
                                    </span>
                                </div>
                                <div class="text-right text-xs">
                                    <div
                                        v-if="item.score !== null"
                                        class="font-semibold text-emerald-600"
                                    >
                                        {{ item.score }}/100
                                    </div>
                                    <div
                                        v-if="item.updated_at"
                                        class="text-[10px] text-neutral-400"
                                    >
                                        {{ item.updated_at }}
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Other Categories -->
            <div class="mt-12 border-t border-neutral-200 pt-8">
                <div
                    class="text-xs font-semibold tracking-wider text-neutral-400 uppercase"
                >
                    Kategori Lainnya:
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Link
                        v-for="cat in otherCategories"
                        :key="cat.id"
                        :href="showCategory.url(cat.slug)"
                        class="rounded-lg bg-neutral-200/80 px-3 py-1.5 text-xs font-medium text-neutral-700 transition hover:bg-neutral-300"
                    >
                        {{ cat.name }}
                    </Link>
                </div>
            </div>
        </main>
    </PublicLayout>
</template>
