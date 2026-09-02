<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

interface CategoryData {
    id: number;
    name: string;
    slug: string;
}

interface ParentData {
    id: number;
    name: string;
    slug: string;
}

interface AliasData {
    id: number;
    alias: string;
    alias_type: string;
}

interface EntityData {
    id: number;
    name: string;
    slug: string;
    type: string;
    type_label: string;
    description: string | null;
    searchable: boolean;
    rankable: boolean;
    category: CategoryData;
    parent: ParentData | null;
    aliases: AliasData[];
}

interface DistributionData {
    positive_pct: number;
    neutral_pct: number;
    negative_pct: number;
}

interface SentimentData {
    is_eligible: boolean;
    score: number | null;
    opinion_count: number;
    positive_count: number;
    neutral_count: number;
    negative_count: number;
    distribution: DistributionData | null;
    model_version?: string;
    formula_version?: string;
    empty_state_message?: string;
}

interface ThemeItem {
    id: number;
    slug: string;
    display_label: string;
    observation_count: number;
    positive_count?: number;
    neutral_count?: number;
    negative_count?: number;
}

interface ThemesData {
    has_enough_data: boolean;
    empty_state_message: string | null;
    opinion_count: number;
    top_themes: ThemeItem[];
    positive_themes: ThemeItem[];
    negative_themes: ThemeItem[];
}

interface RelatedEntity {
    id: number;
    name: string;
    slug: string;
    type: string;
    type_label: string;
}

const props = defineProps<{
    entity: EntityData;
    period: string;
    availablePeriods: string[];
    sentiment: SentimentData;
    themes: ThemesData;
    relatedEntities: RelatedEntity[];
}>();

const periodLabels: Record<string, string> = {
    '30d': '30 Hari',
    '90d': '90 Hari',
    '365d': '1 Tahun',
    'all': 'Semua Waktu',
};

function switchPeriod(p: string) {
    router.get(`/e/${props.entity.slug}`, { period: p }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${entity.name} - Sentimen Netijen & Review`" />

    <div class="min-h-screen bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <!-- Header / Navigation -->
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
                <Link :href="`/top/${entity.category.slug}`" class="hover:underline">{{ entity.category.name }}</Link>
                <template v-if="entity.parent">
                    <span>/</span>
                    <Link :href="`/e/${entity.parent.slug}`" class="hover:underline">
                        {{ entity.parent.name }}
                    </Link>
                </template>
                <span>/</span>
                <span class="font-medium text-neutral-800 dark:text-neutral-200">{{ entity.name }}</span>
            </nav>

            <!-- Entity Header Card -->
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8 dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-black tracking-tight text-neutral-900 sm:text-3xl dark:text-neutral-100">
                                {{ entity.name }}
                            </h1>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold tracking-wider uppercase"
                                :class="{
                                    'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300': entity.type === 'brand',
                                    'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300': entity.type === 'product',
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300': entity.type === 'service',
                                }"
                            >
                                {{ entity.type_label }}
                            </span>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-4 text-sm text-neutral-500 dark:text-neutral-400">
                            <div>
                                Kategori:
                                <Link :href="`/top/${entity.category.slug}`" class="font-medium text-neutral-700 hover:text-emerald-600 hover:underline dark:text-neutral-300 dark:hover:text-emerald-400">
                                    {{ entity.category.name }}
                                </Link>
                            </div>
                            <div v-if="entity.parent">
                                Brand / Induk:
                                <Link :href="`/e/${entity.parent.slug}`" class="font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                                    {{ entity.parent.name }}
                                </Link>
                            </div>
                        </div>

                        <p v-if="entity.description" class="mt-4 text-sm leading-relaxed text-neutral-600 dark:text-neutral-300">
                            {{ entity.description }}
                        </p>
                    </div>

                    <!-- Period Selector -->
                    <div class="inline-flex rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
                        <button
                            v-for="p in availablePeriods"
                            :key="p"
                            type="button"
                            @click="switchPeriod(p)"
                            class="rounded-md px-3 py-1 text-xs font-medium transition-colors"
                            :class="{
                                'bg-white text-neutral-900 shadow-sm dark:bg-neutral-900 dark:text-neutral-100': period === p,
                                'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-200': period !== p
                            }"
                        >
                            {{ periodLabels[p] || p }}
                        </button>
                    </div>
                </div>

                <!-- Aliases list -->
                <div v-if="entity.aliases.length > 0" class="mt-6 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                    <div class="text-xs font-medium tracking-wider text-neutral-400 uppercase">
                        Nama Alternatif / Alias:
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <span
                            v-for="alias in entity.aliases"
                            :key="alias.id"
                            class="rounded-md bg-neutral-100 px-2.5 py-1 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300"
                        >
                            {{ alias.alias }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Sentimen Netijen Card -->
            <div class="mt-6 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8 dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-center justify-between border-b border-neutral-100 pb-4 dark:border-neutral-800">
                    <h2 class="text-lg font-bold text-neutral-900 dark:text-neutral-100">
                        Sentimen Netijen
                    </h2>
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">
                        Periode: {{ periodLabels[period] || period }}
                    </span>
                </div>

                <!-- Above threshold score view -->
                <div v-if="sentiment.is_eligible && sentiment.score !== null && sentiment.distribution" class="mt-6">
                    <div class="grid gap-6 sm:grid-cols-2">
                        <!-- Score Display -->
                        <div class="flex flex-col justify-center rounded-xl bg-neutral-50 p-6 dark:bg-neutral-800/50">
                            <span class="text-xs font-semibold text-neutral-500 uppercase tracking-wider dark:text-neutral-400">
                                Skor Agregat Publik
                            </span>
                            <div class="mt-2 flex items-baseline gap-2">
                                <span
                                    class="text-5xl font-black tracking-tight"
                                    :class="{
                                        'text-emerald-600 dark:text-emerald-400': sentiment.score >= 70,
                                        'text-amber-600 dark:text-amber-400': sentiment.score >= 50 && sentiment.score < 70,
                                        'text-rose-600 dark:text-rose-400': sentiment.score < 50,
                                    }"
                                >
                                    {{ sentiment.score }}
                                </span>
                                <span class="text-lg font-medium text-neutral-400">/100</span>
                            </div>
                            <div class="mt-3 text-xs text-neutral-500 dark:text-neutral-400">
                                <strong>{{ sentiment.opinion_count.toLocaleString() }}</strong> opini netijen dianalisis
                            </div>
                        </div>

                        <!-- Sentiment Distribution -->
                        <div class="flex flex-col justify-center rounded-xl bg-neutral-50 p-6 dark:bg-neutral-800/50">
                            <span class="text-xs font-semibold text-neutral-500 uppercase tracking-wider dark:text-neutral-400">
                                Distribusi Sentimen
                            </span>

                            <!-- Distribution bar -->
                            <div class="mt-3 flex h-3 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                                <div class="bg-emerald-500" :style="{ width: `${sentiment.distribution.positive_pct}%` }" />
                                <div class="bg-neutral-400" :style="{ width: `${sentiment.distribution.neutral_pct}%` }" />
                                <div class="bg-rose-500" :style="{ width: `${sentiment.distribution.negative_pct}%` }" />
                            </div>

                            <!-- Legend -->
                            <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                                <div class="rounded-lg bg-emerald-50 p-2 dark:bg-emerald-950/30">
                                    <div class="font-bold text-emerald-700 dark:text-emerald-300">
                                        {{ sentiment.distribution.positive_pct }}%
                                    </div>
                                    <div class="text-[10px] text-emerald-600/80 dark:text-emerald-400">
                                        Positif ({{ sentiment.positive_count }})
                                    </div>
                                </div>
                                <div class="rounded-lg bg-neutral-100 p-2 dark:bg-neutral-800">
                                    <div class="font-bold text-neutral-700 dark:text-neutral-300">
                                        {{ sentiment.distribution.neutral_pct }}%
                                    </div>
                                    <div class="text-[10px] text-neutral-500">
                                        Netral ({{ sentiment.neutral_count }})
                                    </div>
                                </div>
                                <div class="rounded-lg bg-rose-50 p-2 dark:bg-rose-950/30">
                                    <div class="font-bold text-rose-700 dark:text-rose-300">
                                        {{ sentiment.distribution.negative_pct }}%
                                    </div>
                                    <div class="text-[10px] text-rose-600/80 dark:text-rose-400">
                                        Negatif ({{ sentiment.negative_count }})
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Below threshold empty state notice -->
                <div v-else class="mt-6 rounded-xl border border-dashed border-neutral-300 bg-neutral-50 p-6 text-center dark:border-neutral-700 dark:bg-neutral-800/40">
                    <div class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                        Sentimen Netijen Belum Tersedia
                    </div>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        {{ sentiment.empty_state_message || 'Crawler opini publik belum mengumpulkan minimal 30 opini netijen untuk entitas ini. Skor agregat publik akan dihitung otomatis saat pipeline observasi aktif.' }}
                    </p>
                </div>
            </div>

            <!-- Top Suara Netijen (Theme Index per docs/25) -->
            <div class="mt-6 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8 dark:border-neutral-800 dark:bg-neutral-900">
                <div class="border-b border-neutral-100 pb-4 dark:border-neutral-800">
                    <h2 class="text-lg font-bold text-neutral-900 dark:text-neutral-100">
                        Top Suara Netijen
                    </h2>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                        Tema dan kata kunci yang paling sering dibahas netijen mengenai entitas ini (frekuensi tema, bukan skor numerik).
                    </p>
                </div>

                <!-- Above threshold Top 5 Themes -->
                <div v-if="themes.has_enough_data" class="mt-6 space-y-6">
                    <!-- Ranked List -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            Top 5 Tema Paling Sering Muncul
                        </h3>
                        <div class="mt-3 space-y-2.5">
                            <div
                                v-for="(theme, index) in themes.top_themes"
                                :key="theme.id"
                                class="flex items-center justify-between rounded-lg bg-neutral-50 px-4 py-2.5 dark:bg-neutral-800/60"
                            >
                                <div class="flex items-center gap-3">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-neutral-200 text-xs font-bold text-neutral-700 dark:bg-neutral-700 dark:text-neutral-300">
                                        {{ index + 1 }}
                                    </span>
                                    <span class="text-sm font-semibold text-neutral-800 dark:text-neutral-200">
                                        {{ theme.display_label }}
                                    </span>
                                </div>
                                <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ theme.observation_count }} opini
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Groups: Paling Suka & Sering Dikeluhkan -->
                    <div class="grid gap-4 sm:grid-cols-2 pt-2">
                        <!-- Netijen Paling Suka -->
                        <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/40 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                            <div class="text-xs font-bold text-emerald-800 dark:text-emerald-300">
                                Netijen Paling Suka
                            </div>
                            <div v-if="themes.positive_themes.length > 0" class="mt-2.5 flex flex-wrap gap-1.5">
                                <span
                                    v-for="t in themes.positive_themes"
                                    :key="t.id"
                                    class="inline-flex items-center rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200"
                                >
                                    {{ t.display_label }} ({{ t.observation_count }})
                                </span>
                            </div>
                            <div v-else class="mt-2 text-xs text-neutral-400">
                                Belum ada tema positif yang dominan.
                            </div>
                        </div>

                        <!-- Paling Sering Dikeluhkan -->
                        <div class="rounded-xl border border-rose-200/80 bg-rose-50/40 p-4 dark:border-rose-900/40 dark:bg-rose-950/20">
                            <div class="text-xs font-bold text-rose-800 dark:text-rose-300">
                                Paling Sering Dikeluhkan
                            </div>
                            <div v-if="themes.negative_themes.length > 0" class="mt-2.5 flex flex-wrap gap-1.5">
                                <span
                                    v-for="t in themes.negative_themes"
                                    :key="t.id"
                                    class="inline-flex items-center rounded-md bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-800 dark:bg-rose-900/60 dark:text-rose-200"
                                >
                                    {{ t.display_label }} ({{ t.observation_count }})
                                </span>
                            </div>
                            <div v-else class="mt-2 text-xs text-neutral-400">
                                Belum ada keluhan berulang yang terdeteksi.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Below threshold empty state -->
                <div v-else class="mt-6 rounded-xl border border-dashed border-neutral-300 bg-neutral-50 p-6 text-center dark:border-neutral-700 dark:bg-neutral-800/40">
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        {{ themes.empty_state_message || 'Belum cukup opini untuk merangkum Suara Netijen.' }}
                    </p>
                </div>
            </div>

            <!-- Related Entities -->
            <div v-if="relatedEntities.length > 0" class="mt-6 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <h3 class="text-sm font-bold text-neutral-900 dark:text-neutral-100">
                    Entitas Terkait dalam Kategori {{ entity.category.name }}
                </h3>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Link
                        v-for="rel in relatedEntities"
                        :key="rel.id"
                        :href="`/e/${rel.slug}`"
                        class="rounded-xl border border-neutral-100 bg-neutral-50 p-3 text-center transition hover:border-emerald-500 hover:bg-emerald-50/20 dark:border-neutral-800 dark:bg-neutral-800/40"
                    >
                        <div class="text-xs font-bold text-neutral-800 hover:text-emerald-600 dark:text-neutral-200 dark:hover:text-emerald-400">
                            {{ rel.name }}
                        </div>
                        <span class="mt-1 inline-block text-[10px] text-neutral-400 uppercase">
                            {{ rel.type_label }}
                        </span>
                    </Link>
                </div>
            </div>
        </main>
    </div>
</template>
