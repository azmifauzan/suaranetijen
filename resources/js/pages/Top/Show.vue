<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home } from '@/routes';
import { show as showEntity } from '@/routes/entities';
import { show as showRanking } from '@/routes/rankings';
import { Head, Link, router } from '@inertiajs/vue3';

interface CategoryData {
    id: number;
    name: string;
    slug: string;
}

interface DistributionData {
    positive: number;
    neutral: number;
    negative: number;
    positive_pct: number;
    neutral_pct: number;
    negative_pct: number;
}

interface RankedEntityData {
    rank: number;
    entity: {
        id: number;
        name: string;
        slug: string;
        type: string;
        type_label: string;
    };
    score: number;
    opinion_count: number;
    distribution: DistributionData;
}

const props = defineProps<{
    category: CategoryData;
    period: string;
    rankings: RankedEntityData[];
    otherCategories: CategoryData[];
}>();

const periods = [
    { key: '30d', label: '30 Hari' },
    { key: '90d', label: '90 Hari' },
    { key: '365d', label: '1 Tahun' },
    { key: 'all', label: 'Semua Waktu' },
];

function switchPeriod(p: string) {
    router.get(
        showRanking.url(props.category.slug),
        { period: p },
        { preserveScroll: true },
    );
}
</script>

<template>
    <PublicLayout>
        <Head :title="`${category.name} dengan Sentimen Netijen Tertinggi`" />

        <!-- Main Content -->
        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <!-- Breadcrumbs -->
            <nav class="mb-4 flex items-center gap-2 text-xs text-neutral-500">
                <Link :href="home()" class="hover:underline">Beranda</Link>
                <span>/</span>
                <span>Ranking</span>
                <span>/</span>
                <span class="font-medium text-neutral-800">{{
                    category.name
                }}</span>
            </nav>

            <!-- Page Title -->
            <div class="mb-6">
                <h1
                    class="text-2xl font-black tracking-tight text-neutral-900 sm:text-3xl"
                >
                    {{ category.name }} dengan Sentimen Netijen Tertinggi
                </h1>
                <p class="mt-2 text-sm text-neutral-600">
                    Daftar entitas dalam kategori {{ category.name }} yang
                    diurutkan berdasarkan agregat opini netijen publik (minimal
                    100 opini dianalisis).
                </p>
            </div>

            <!-- Controls: Category Pills & Period Selector -->
            <div
                class="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-neutral-200 pb-4"
            >
                <!-- Category Pills -->
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-neutral-500"
                        >Kategori:</span
                    >
                    <span
                        class="rounded-full bg-emerald-600 px-3 py-1 text-xs font-semibold text-white"
                    >
                        {{ category.name }}
                    </span>
                    <Link
                        v-for="other in otherCategories"
                        :key="other.id"
                        :href="showRanking.url(other.slug)"
                        class="rounded-full bg-neutral-200/80 px-3 py-1 text-xs text-neutral-700 hover:bg-neutral-300"
                    >
                        {{ other.name }}
                    </Link>
                </div>

                <!-- Period Selector -->
                <div class="inline-flex rounded-lg bg-neutral-200/70 p-1">
                    <button
                        v-for="p in periods"
                        :key="p.key"
                        type="button"
                        @click="switchPeriod(p.key)"
                        class="rounded-md px-3 py-1 text-xs font-medium transition-colors"
                        :class="{
                            'bg-white text-neutral-900 shadow-sm':
                                period === p.key,
                            'text-neutral-600 hover:text-neutral-900':
                                period !== p.key,
                        }"
                    >
                        {{ p.label }}
                    </button>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-if="rankings.length === 0"
                class="rounded-2xl border border-dashed border-neutral-300 bg-white p-12 text-center shadow-sm"
            >
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-neutral-100"
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
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                        />
                    </svg>
                </div>
                <h3 class="mt-4 text-base font-semibold text-neutral-900">
                    Belum Ada Ranking untuk Kategori Ini
                </h3>
                <p class="mt-1 text-sm text-neutral-500">
                    Belum ada entitas di kategori {{ category.name }} yang
                    memenuhi batas minimal 100 opini netijen untuk ranking
                    publik.
                </p>
            </div>

            <!-- Rankings List / Stacked Cards -->
            <div v-else class="space-y-3">
                <div
                    v-for="item in rankings"
                    :key="item.entity.id"
                    class="flex flex-col gap-4 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm transition hover:border-emerald-500/50 sm:flex-row sm:items-center sm:justify-between sm:p-5"
                >
                    <!-- Left: Rank & Entity Details -->
                    <div
                        class="flex items-start gap-3 sm:items-center sm:gap-4"
                    >
                        <div
                            class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-sm font-black"
                            :class="{
                                'bg-amber-400/20 text-amber-600':
                                    item.rank === 1,
                                'bg-neutral-300/40 text-neutral-600':
                                    item.rank === 2,
                                'bg-amber-700/20 text-amber-800':
                                    item.rank === 3,
                                'bg-neutral-100 text-neutral-500':
                                    item.rank > 3,
                            }"
                        >
                            {{ item.rank }}
                        </div>

                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    :href="showEntity.url(item.entity.slug)"
                                    class="text-base font-bold text-neutral-900 hover:text-emerald-600 hover:underline"
                                >
                                    {{ item.entity.name }}
                                </Link>
                                <span
                                    class="rounded bg-neutral-100 px-2 py-0.5 text-[10px] font-semibold text-neutral-600 uppercase"
                                >
                                    {{ item.entity.type_label }}
                                </span>
                            </div>
                            <div class="mt-1 text-xs text-neutral-500">
                                {{ item.opinion_count.toLocaleString() }} opini
                                dianalisis
                            </div>
                        </div>
                    </div>

                    <!-- Right: Score & Distribution -->
                    <div
                        class="flex items-center justify-between gap-6 border-t border-neutral-100 pt-3 sm:border-0 sm:pt-0"
                    >
                        <!-- Distribution Bar -->
                        <div class="w-36">
                            <div
                                class="flex h-2 overflow-hidden rounded-full bg-neutral-100"
                            >
                                <div
                                    class="bg-emerald-500"
                                    :style="{
                                        width: `${item.distribution.positive_pct}%`,
                                    }"
                                />
                                <div
                                    class="bg-neutral-400"
                                    :style="{
                                        width: `${item.distribution.neutral_pct}%`,
                                    }"
                                />
                                <div
                                    class="bg-rose-500"
                                    :style="{
                                        width: `${item.distribution.negative_pct}%`,
                                    }"
                                />
                            </div>
                            <div
                                class="mt-1 flex justify-between text-[10px] text-neutral-400"
                            >
                                <span
                                    >{{ item.distribution.positive_pct }}%
                                    pos</span
                                >
                                <span
                                    >{{ item.distribution.negative_pct }}%
                                    neg</span
                                >
                            </div>
                        </div>

                        <!-- Score Pill -->
                        <div class="flex flex-col items-end">
                            <div
                                class="inline-flex items-center rounded-lg px-3 py-1.5 text-lg font-black"
                                :class="{
                                    'bg-emerald-100 text-emerald-800':
                                        item.score >= 70,
                                    'bg-amber-100 text-amber-800':
                                        item.score >= 50 && item.score < 70,
                                    'bg-rose-100 text-rose-800':
                                        item.score < 50,
                                }"
                            >
                                {{ item.score }}
                                <span
                                    class="ml-1 text-[11px] font-normal text-neutral-500"
                                    >/100</span
                                >
                            </div>
                            <span class="mt-0.5 text-[10px] text-neutral-400"
                                >Sentimen Netijen</span
                            >
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </PublicLayout>
</template>
