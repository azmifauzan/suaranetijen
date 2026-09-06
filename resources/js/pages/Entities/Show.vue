<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home, methodology, sources } from '@/routes';
import { show as showEntity } from '@/routes/entities';
import { show as showRanking } from '@/routes/rankings';
import { Link, router, useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { login } from '@/routes';
import PublicSeo from '@/components/PublicSeo.vue';
import {
    destroy as deleteRating,
    update as updateRating,
} from '@/routes/api/entities/rating';

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

interface RatingData {
    rating_count: number;
    rating_average: number | null;
    user_rating: number | null;
}

interface RatingMutationResponse {
    data: {
        rating: number | null;
        rating_count: number;
        rating_average: number | null;
    };
}

interface RelatedEntity {
    id: number;
    name: string;
    slug: string;
    type: string;
    type_label: string;
}

interface SpecsItem {
    label: string;
    value: string;
}

interface SpecsData {
    title: string;
    items: SpecsItem[];
}

interface TrendPoint {
    date: string;
    label: string;
    score: number | null;
    opinion_count: number;
    positive_count: number;
    neutral_count: number;
    negative_count: number;
}

const props = defineProps<{
    entity: EntityData;
    period: string;
    availablePeriods: string[];
    sentiment: SentimentData;
    rating: RatingData;
    themes: ThemesData;
    relatedEntities: RelatedEntity[];
    trend?: TrendPoint[];
    specs: SpecsData | null;
}>();

const ratingData = ref<RatingData>({ ...props.rating });
const ratingForm = useHttp<{ rating: number }, RatingMutationResponse>({
    rating: props.rating.user_rating ?? 0,
});

const pageTitle = computed(
    () => `${props.entity.name}: Sentimen dan Rating Netizen`,
);
const metaDescription = computed(() => {
    if (props.sentiment.is_eligible && props.sentiment.score !== null) {
        return `Skor Sentimen Netizen untuk ${props.entity.name} adalah ${props.sentiment.score}/100 berdasarkan analisis ${props.sentiment.opinion_count} opini publik. Simak rangkuman sentimen dan rating pengguna di SuaraNetijen.`;
    }
    return `Indeks sentimen dan opini netizen untuk ${props.entity.name} di SuaraNetijen.`;
});

const jsonLd = computed(() => {
    const data: Record<string, unknown> = {
        '@context': 'https://schema.org',
        '@type':
            props.entity.type === 'service'
                ? 'Service'
                : props.entity.type === 'brand'
                  ? 'Organization'
                  : 'Product',
        name: props.entity.name,
        description:
            props.entity.description || `${props.entity.name} di SuaraNetijen`,
    };

    // AggregateRating schema used ONLY for first-party rating, NEVER for Sentimen Netijen (docs/13, ADR-007)
    if (
        ratingData.value.rating_count > 0 &&
        ratingData.value.rating_average !== null
    ) {
        data.aggregateRating = {
            '@type': 'AggregateRating',
            ratingValue: ratingData.value.rating_average,
            reviewCount: ratingData.value.rating_count,
            bestRating: 5,
            worstRating: 1,
        };
    }

    return data;
});

const periodLabels: Record<string, string> = {
    '30d': '30 Hari',
    '90d': '90 Hari',
    '365d': '1 Tahun',
    all: 'Semua Waktu',
};

function switchPeriod(p: string) {
    router.get(
        showEntity.url(props.entity.slug),
        { period: p },
        { preserveScroll: true },
    );
}

function updateRatingData(response: RatingMutationResponse): void {
    ratingData.value = {
        rating_count: response.data.rating_count,
        rating_average: response.data.rating_average,
        user_rating: response.data.rating,
    };
    ratingForm.rating = response.data.rating ?? 0;
}

async function submitRating(): Promise<void> {
    if (ratingForm.rating < 1 || ratingForm.rating > 5) {
        return;
    }

    ratingForm.clearErrors();
    try {
        await ratingForm.put(updateRating.url(props.entity.id), {
            onSuccess: updateRatingData,
            onHttpException: (response) => {
                ratingForm.setError(
                    'rating',
                    response.status === 429
                        ? 'Terlalu banyak percobaan rating. Coba lagi nanti.'
                        : 'Rating gagal disimpan. Coba lagi.',
                );
            },
        });
    } catch {
        if (!ratingForm.hasErrors) {
            ratingForm.setError('rating', 'Rating gagal disimpan. Coba lagi.');
        }
    }
}

async function removeRating(): Promise<void> {
    ratingForm.clearErrors();
    try {
        await ratingForm.delete(deleteRating.url(props.entity.id), {
            onSuccess: updateRatingData,
            onHttpException: (response) => {
                ratingForm.setError(
                    'rating',
                    response.status === 429
                        ? 'Terlalu banyak percobaan rating. Coba lagi nanti.'
                        : 'Rating gagal dihapus. Coba lagi.',
                );
            },
        });
    } catch {
        if (!ratingForm.hasErrors) {
            ratingForm.setError('rating', 'Rating gagal dihapus. Coba lagi.');
        }
    }
}
</script>

<template>
    <PublicLayout>
        <PublicSeo
            :title="pageTitle"
            :description="metaDescription"
            :canonical-path="`/e/${entity.slug}`"
            :robots="
                sentiment.is_eligible ? 'index, follow' : 'noindex, follow'
            "
        >
            <component :is="'script'" type="application/ld+json">
                {{ JSON.stringify(jsonLd) }}
            </component>
        </PublicSeo>

        <!-- Main Content -->
        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <!-- Breadcrumbs -->
            <nav class="mb-4 flex items-center gap-2 text-xs text-neutral-500">
                <Link :href="home()" class="hover:underline">Beranda</Link>
                <span>/</span>
                <Link
                    :href="showRanking.url(entity.category.slug)"
                    class="hover:underline"
                    >{{ entity.category.name }}</Link
                >
                <template v-if="entity.parent">
                    <span>/</span>
                    <Link
                        :href="showEntity.url(entity.parent.slug)"
                        class="hover:underline"
                    >
                        {{ entity.parent.name }}
                    </Link>
                </template>
                <span>/</span>
                <span class="font-medium text-neutral-800">{{
                    entity.name
                }}</span>
            </nav>

            <!-- Entity Header Card -->
            <div
                class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <h1
                                class="text-2xl font-black tracking-tight text-neutral-900 sm:text-3xl"
                            >
                                {{ entity.name }}
                            </h1>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold tracking-wider uppercase"
                                :class="{
                                    'bg-blue-100 text-blue-800':
                                        entity.type === 'brand',
                                    'bg-purple-100 text-purple-800':
                                        entity.type === 'product',
                                    'bg-amber-100 text-amber-800':
                                        entity.type === 'service',
                                }"
                            >
                                {{ entity.type_label }}
                            </span>
                        </div>

                        <div
                            class="mt-2 flex flex-wrap items-center gap-4 text-sm text-neutral-500"
                        >
                            <div>
                                Kategori:
                                <Link
                                    :href="
                                        showRanking.url(entity.category.slug)
                                    "
                                    class="font-medium text-neutral-700 hover:text-emerald-600 hover:underline"
                                >
                                    {{ entity.category.name }}
                                </Link>
                            </div>
                            <div v-if="entity.parent">
                                Brand / Induk:
                                <Link
                                    :href="showEntity.url(entity.parent.slug)"
                                    class="font-medium text-emerald-600 hover:underline"
                                >
                                    {{ entity.parent.name }}
                                </Link>
                            </div>
                        </div>

                        <p
                            v-if="entity.description"
                            class="mt-4 text-sm leading-relaxed text-neutral-600"
                        >
                            {{ entity.description }}
                        </p>
                    </div>

                    <!-- Period Selector -->
                    <div class="inline-flex rounded-lg bg-neutral-100 p-1">
                        <button
                            v-for="p in availablePeriods"
                            :key="p"
                            type="button"
                            @click="switchPeriod(p)"
                            class="rounded-md px-3 py-1 text-xs font-medium transition-colors"
                            :class="{
                                'bg-white text-neutral-900 shadow-sm':
                                    period === p,
                                'text-neutral-600 hover:text-neutral-900':
                                    period !== p,
                            }"
                        >
                            {{ periodLabels[p] || p }}
                        </button>
                    </div>
                </div>

                <!-- Aliases list -->
                <div
                    v-if="entity.aliases.length > 0"
                    class="mt-6 border-t border-neutral-100 pt-4"
                >
                    <div
                        class="text-xs font-medium tracking-wider text-neutral-400 uppercase"
                    >
                        Nama Alternatif / Alias:
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <span
                            v-for="alias in entity.aliases"
                            :key="alias.id"
                            class="rounded-md bg-neutral-100 px-2.5 py-1 text-xs text-neutral-600"
                        >
                            {{ alias.alias }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Spesifikasi Card: manually curated reference data, kept separate from
                 the Sentimen/Rating cards below since it is not derived from sentiment
                 (docs/03, ADR-008 clarification) -->
            <div
                v-if="specs"
                class="mt-6 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8"
            >
                <h2 class="border-b border-neutral-100 pb-4 text-lg font-bold text-neutral-900">
                    {{ specs.title }}
                </h2>
                <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div v-for="item in specs.items" :key="item.label" class="flex justify-between border-b border-neutral-50 pb-2 text-sm sm:justify-start sm:gap-2">
                        <dt class="text-neutral-500">{{ item.label }}</dt>
                        <dd class="font-medium text-neutral-900">{{ item.value }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-[11px] text-neutral-400">
                    Data referensi diisi manual, bukan bagian dari Sentimen Netijen.
                </p>
            </div>

            <!-- Sentimen Netijen Card -->
            <div
                class="mt-6 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8"
            >
                <div
                    class="flex items-center justify-between border-b border-neutral-100 pb-4"
                >
                    <h2 class="text-lg font-bold text-neutral-900">
                        Sentimen Netijen
                    </h2>
                    <span class="text-xs text-neutral-500">
                        Periode: {{ periodLabels[period] || period }}
                    </span>
                </div>

                <!-- Above threshold score view -->
                <div
                    v-if="
                        sentiment.is_eligible &&
                        sentiment.score !== null &&
                        sentiment.distribution
                    "
                    class="mt-6"
                >
                    <div class="grid gap-6 sm:grid-cols-2">
                        <!-- Score Display -->
                        <div
                            class="flex flex-col justify-center rounded-xl bg-neutral-50 p-6"
                        >
                            <span
                                class="text-xs font-semibold tracking-wider text-neutral-500 uppercase"
                            >
                                Skor Agregat Publik
                            </span>
                            <div class="mt-2 flex items-baseline gap-2">
                                <span
                                    class="text-5xl font-black tracking-tight"
                                    :class="{
                                        'text-emerald-600':
                                            sentiment.score >= 70,
                                        'text-amber-600':
                                            sentiment.score >= 50 &&
                                            sentiment.score < 70,
                                        'text-rose-600': sentiment.score < 50,
                                    }"
                                >
                                    {{ sentiment.score }}
                                </span>
                                <span
                                    class="text-lg font-medium text-neutral-400"
                                    >/100</span
                                >
                            </div>
                            <div class="mt-3 text-xs text-neutral-500">
                                <strong>{{
                                    sentiment.opinion_count.toLocaleString()
                                }}</strong>
                                opini netizen dianalisis
                            </div>
                        </div>

                        <!-- Sentiment Distribution -->
                        <div
                            class="flex flex-col justify-center rounded-xl bg-neutral-50 p-6"
                        >
                            <span
                                class="text-xs font-semibold tracking-wider text-neutral-500 uppercase"
                            >
                                Distribusi Sentimen
                            </span>

                            <!-- Distribution bar -->
                            <div
                                class="mt-3 flex h-3 overflow-hidden rounded-full bg-neutral-200"
                            >
                                <div
                                    class="bg-emerald-500"
                                    :style="{
                                        width: `${sentiment.distribution.positive_pct}%`,
                                    }"
                                />
                                <div
                                    class="bg-neutral-400"
                                    :style="{
                                        width: `${sentiment.distribution.neutral_pct}%`,
                                    }"
                                />
                                <div
                                    class="bg-rose-500"
                                    :style="{
                                        width: `${sentiment.distribution.negative_pct}%`,
                                    }"
                                />
                            </div>

                            <!-- Legend -->
                            <div
                                class="mt-4 grid grid-cols-3 gap-2 text-center text-xs"
                            >
                                <div class="rounded-lg bg-emerald-50 p-2">
                                    <div class="font-bold text-emerald-700">
                                        {{
                                            sentiment.distribution.positive_pct
                                        }}%
                                    </div>
                                    <div
                                        class="text-[10px] text-emerald-600/80"
                                    >
                                        Positif ({{ sentiment.positive_count }})
                                    </div>
                                </div>
                                <div class="rounded-lg bg-neutral-100 p-2">
                                    <div class="font-bold text-neutral-700">
                                        {{
                                            sentiment.distribution.neutral_pct
                                        }}%
                                    </div>
                                    <div class="text-[10px] text-neutral-500">
                                        Netral ({{ sentiment.neutral_count }})
                                    </div>
                                </div>
                                <div class="rounded-lg bg-rose-50 p-2">
                                    <div class="font-bold text-rose-700">
                                        {{
                                            sentiment.distribution.negative_pct
                                        }}%
                                    </div>
                                    <div class="text-[10px] text-rose-600/80">
                                        Negatif ({{ sentiment.negative_count }})
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Below threshold empty state notice -->
                <div
                    v-else
                    class="mt-6 rounded-xl border border-dashed border-neutral-300 bg-neutral-50 p-6 text-center"
                >
                    <div class="text-sm font-semibold text-neutral-700">
                        Sentimen Netijen Belum Tersedia
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">
                        {{
                            sentiment.empty_state_message ||
                            'Crawler opini publik belum mengumpulkan minimal 30 opini netizen untuk entitas ini. Skor agregat publik akan dihitung otomatis saat pipeline observasi aktif.'
                        }}
                    </p>
                </div>

                <!-- Methodology & Source Transparency Disclosure per docs/04 -->
                <div
                    class="mt-6 flex flex-wrap items-center justify-between gap-2 border-t border-neutral-100 pt-4 text-xs text-neutral-500"
                >
                    <div>
                        Skor independen dihitung agregat tanpa bobot sponsor.
                    </div>
                    <div class="flex items-center gap-3 font-medium">
                        <Link
                            :href="methodology()"
                            class="text-emerald-600 hover:underline"
                        >
                            Metodologi Skor →
                        </Link>
                        <span>•</span>
                        <Link
                            :href="sources()"
                            class="text-emerald-600 hover:underline"
                        >
                            Sumber Data →
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Trend Chart Sederhana (Element 8 per docs/04) -->
            <div
                class="mt-6 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8"
            >
                <div
                    class="flex items-center justify-between border-b border-neutral-100 pb-4"
                >
                    <div>
                        <h2 class="text-lg font-bold text-neutral-900">
                            Tren Sentimen Harian
                        </h2>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            Riwayat skor dan volume opini netizen dari waktu ke
                            waktu.
                        </p>
                    </div>
                    <span
                        v-if="trend && trend.length > 0"
                        class="rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-600"
                    >
                        {{ trend.length }} hari tercatat
                    </span>
                </div>

                <div v-if="trend && trend.length > 0" class="mt-6">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div
                            v-for="pt in trend.slice(-8)"
                            :key="pt.date"
                            class="rounded-xl border border-neutral-100 bg-neutral-50 p-3"
                        >
                            <div
                                class="text-[11px] font-medium text-neutral-400"
                            >
                                {{ pt.label }}
                            </div>
                            <div class="mt-1 flex items-baseline gap-1">
                                <span
                                    class="text-lg font-black"
                                    :class="{
                                        'text-emerald-600':
                                            (pt.score ?? 0) >= 70,
                                        'text-amber-600':
                                            (pt.score ?? 0) >= 50 &&
                                            (pt.score ?? 0) < 70,
                                        'text-rose-600': (pt.score ?? 0) < 50,
                                    }"
                                >
                                    {{ pt.score !== null ? pt.score : '—' }}
                                </span>
                                <span
                                    v-if="pt.score !== null"
                                    class="text-[10px] text-neutral-400"
                                    >/100</span
                                >
                            </div>
                            <div class="mt-1 text-[10px] text-neutral-500">
                                {{ pt.opinion_count }} opini ({{
                                    pt.positive_count
                                }}
                                pos / {{ pt.negative_count }} neg)
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="mt-6 rounded-xl border border-dashed border-neutral-300 bg-neutral-50 p-6 text-center"
                >
                    <p class="text-xs text-neutral-500">
                        Data tren harian sedang dikumpulkan oleh pipeline
                        crawler.
                    </p>
                </div>
            </div>

            <!-- Rating Netijen Card -->
            <div
                class="mt-6 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-neutral-900">
                            Rating Netijen
                        </h2>
                        <p class="mt-1 text-xs text-neutral-500">
                            Rating dari pengguna SuaraNetijen, terpisah dari
                            Sentimen Netijen.
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-black text-amber-500">
                            <template v-if="ratingData.rating_average !== null">
                                {{ ratingData.rating_average.toFixed(1) }}
                            </template>
                            <span v-else>—</span>
                            <span class="text-base font-medium text-neutral-400"
                                >/5</span
                            >
                        </div>
                        <div class="text-xs text-neutral-500">
                            {{ ratingData.rating_count.toLocaleString() }}
                            rating
                        </div>
                    </div>
                </div>

                <div
                    v-if="$page.props.auth.user"
                    class="mt-6 border-t border-neutral-100 pt-5"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-4"
                    >
                        <div>
                            <div
                                id="rating-label"
                                class="text-sm font-semibold text-neutral-800"
                            >
                                {{
                                    ratingData.user_rating === null
                                        ? 'Beri rating Anda'
                                        : 'Rating Anda'
                                }}
                            </div>
                            <div
                                role="group"
                                aria-labelledby="rating-label"
                                class="mt-2 flex gap-1"
                            >
                                <button
                                    v-for="star in 5"
                                    :key="star"
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-lg text-2xl transition-colors focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                    :class="
                                        star <= ratingForm.rating
                                            ? 'text-amber-500'
                                            : 'text-neutral-300'
                                    "
                                    :aria-label="`Beri rating ${star} dari 5`"
                                    :aria-pressed="star === ratingForm.rating"
                                    @click="ratingForm.rating = star"
                                >
                                    ★
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="min-h-10 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="
                                    ratingForm.processing ||
                                    ratingForm.rating < 1
                                "
                                @click="submitRating"
                            >
                                {{
                                    ratingForm.processing
                                        ? 'Menyimpan...'
                                        : 'Simpan rating'
                                }}
                            </button>
                            <button
                                v-if="ratingData.user_rating !== null"
                                type="button"
                                class="min-h-10 rounded-lg border border-neutral-200 px-4 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="ratingForm.processing"
                                @click="removeRating"
                            >
                                Hapus rating
                            </button>
                        </div>
                    </div>
                    <p
                        v-if="ratingForm.errors.rating"
                        class="mt-2 text-xs text-rose-600"
                    >
                        {{ ratingForm.errors.rating }}
                    </p>
                </div>
                <div
                    v-else
                    class="mt-6 border-t border-neutral-100 pt-5 text-sm text-neutral-600"
                >
                    <Link
                        :href="login()"
                        class="font-semibold text-emerald-600 hover:underline"
                    >
                        Masuk untuk memberi rating
                    </Link>
                </div>
            </div>

            <!-- Top Suara Netijen (Theme Index per docs/25) -->
            <div
                class="mt-6 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8"
            >
                <div class="border-b border-neutral-100 pb-4">
                    <h2 class="text-lg font-bold text-neutral-900">
                        Top Suara Netijen
                    </h2>
                    <p class="mt-0.5 text-xs text-neutral-500">
                        Tema dan kata kunci yang paling sering dibahas netizen
                        mengenai entitas ini (frekuensi tema, bukan skor
                        numerik).
                    </p>
                </div>

                <!-- Above threshold Top 5 Themes -->
                <div v-if="themes.has_enough_data" class="mt-6 space-y-6">
                    <!-- Ranked List -->
                    <div>
                        <h3
                            class="text-xs font-semibold tracking-wider text-neutral-500 uppercase"
                        >
                            Top 5 Tema Paling Sering Muncul
                        </h3>
                        <div class="mt-3 space-y-2.5">
                            <div
                                v-for="(theme, index) in themes.top_themes"
                                :key="theme.id"
                                class="flex items-center justify-between rounded-lg bg-neutral-50 px-4 py-2.5"
                            >
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-neutral-200 text-xs font-bold text-neutral-700"
                                    >
                                        {{ index + 1 }}
                                    </span>
                                    <span
                                        class="text-sm font-semibold text-neutral-800"
                                    >
                                        {{ theme.display_label }}
                                    </span>
                                </div>
                                <span class="text-xs text-neutral-500">
                                    {{ theme.observation_count }} opini
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Groups: Paling Suka & Sering Dikeluhkan -->
                    <div class="grid gap-4 pt-2 sm:grid-cols-2">
                        <!-- Netijen Paling Suka -->
                        <div
                            class="rounded-xl border border-emerald-200/80 bg-emerald-50/40 p-4"
                        >
                            <div class="text-xs font-bold text-emerald-800">
                                Netizen Paling Suka
                            </div>
                            <div
                                v-if="themes.positive_themes.length > 0"
                                class="mt-2.5 flex flex-wrap gap-1.5"
                            >
                                <span
                                    v-for="t in themes.positive_themes"
                                    :key="t.id"
                                    class="inline-flex items-center rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800"
                                >
                                    {{ t.display_label }} ({{
                                        t.observation_count
                                    }})
                                </span>
                            </div>
                            <div v-else class="mt-2 text-xs text-neutral-400">
                                Belum ada tema positif yang dominan.
                            </div>
                        </div>

                        <!-- Paling Sering Dikeluhkan -->
                        <div
                            class="rounded-xl border border-rose-200/80 bg-rose-50/40 p-4"
                        >
                            <div class="text-xs font-bold text-rose-800">
                                Paling Sering Dikeluhkan
                            </div>
                            <div
                                v-if="themes.negative_themes.length > 0"
                                class="mt-2.5 flex flex-wrap gap-1.5"
                            >
                                <span
                                    v-for="t in themes.negative_themes"
                                    :key="t.id"
                                    class="inline-flex items-center rounded-md bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-800"
                                >
                                    {{ t.display_label }} ({{
                                        t.observation_count
                                    }})
                                </span>
                            </div>
                            <div v-else class="mt-2 text-xs text-neutral-400">
                                Belum ada keluhan berulang yang terdeteksi.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Below threshold empty state -->
                <div
                    v-else
                    class="mt-6 rounded-xl border border-dashed border-neutral-300 bg-neutral-50 p-6 text-center"
                >
                    <p class="text-xs text-neutral-500">
                        {{
                            themes.empty_state_message ||
                            'Belum cukup opini untuk merangkum Suara Netijen.'
                        }}
                    </p>
                </div>
            </div>

            <!-- Related Entities -->
            <div
                v-if="relatedEntities.length > 0"
                class="mt-6 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm"
            >
                <h3 class="text-sm font-bold text-neutral-900">
                    Entitas Terkait dalam Kategori {{ entity.category.name }}
                </h3>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Link
                        v-for="rel in relatedEntities"
                        :key="rel.id"
                        :href="showEntity.url(rel.slug)"
                        class="rounded-xl border border-neutral-100 bg-neutral-50 p-3 text-center transition hover:border-emerald-500 hover:bg-emerald-50/20"
                    >
                        <div
                            class="text-xs font-bold text-neutral-800 hover:text-emerald-600"
                        >
                            {{ rel.name }}
                        </div>
                        <span
                            class="mt-1 inline-block text-[10px] text-neutral-400 uppercase"
                        >
                            {{ rel.type_label }}
                        </span>
                    </Link>
                </div>
            </div>
        </main>
    </PublicLayout>
</template>
