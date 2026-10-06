<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicSeo from '@/components/PublicSeo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home, methodology } from '@/routes';
import { show as showCategory } from '@/routes/categories';
import { show as showEntity } from '@/routes/entities';

interface Theme {
    id: number;
    display_label: string;
    observation_count: number;
}

interface Side {
    name: string;
    slug: string;
    type_label: string;
    category: string;
    category_slug: string;
    score: number;
    opinion_count: number;
    distribution: { positive_pct: number; neutral_pct: number; negative_pct: number };
    positive_themes: Theme[];
    negative_themes: Theme[];
}

const props = defineProps<{
    pair: string;
    indexable: boolean;
    comparison: { sides: Side[]; verdict: string; same_category: boolean };
    comparisonSeo: {
        title: string;
        meta_description: string;
        faq: Array<{ question: string; answer: string }>;
        breadcrumb_json_ld: Record<string, unknown>;
        faq_json_ld: Record<string, unknown>;
    };
}>();

// Inertia reuses this component between two comparisons, so derive from props instead of reading them once.
const a = computed(() => props.comparison.sides[0]);
const b = computed(() => props.comparison.sides[1]);
const robots = computed(() => (props.indexable ? 'index, follow' : 'noindex, follow'));
const formatCount = (value: number): string => value.toLocaleString('id-ID');
</script>

<template>
    <PublicLayout>
        <PublicSeo
            :title="comparisonSeo.title"
            :description="comparisonSeo.meta_description"
            :canonical-path="`/banding/${pair}`"
            :robots="robots"
        >
            <component :is="'script'" type="application/ld+json">
                {{ JSON.stringify(comparisonSeo.breadcrumb_json_ld) }}
            </component>
            <component :is="'script'" type="application/ld+json">
                {{ JSON.stringify(comparisonSeo.faq_json_ld) }}
            </component>
        </PublicSeo>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <nav class="mb-4 flex flex-wrap items-center gap-2 text-xs text-neutral-500">
                <Link :href="home()" class="hover:underline">Beranda</Link>
                <span>/</span>
                <Link :href="showCategory.url(a.category_slug)" class="hover:underline">{{ a.category }}</Link>
                <span>/</span>
                <span class="font-medium text-neutral-800">{{ a.name }} vs {{ b.name }}</span>
            </nav>

            <h1 class="text-2xl font-black tracking-tight text-neutral-900 sm:text-3xl">
                {{ a.name }} vs {{ b.name }}
            </h1>
            <p class="mt-2 text-sm text-neutral-600 sm:text-base">
                Bagus mana menurut netizen? Perbandingan dari {{ formatCount(a.opinion_count) }} dan
                {{ formatCount(b.opinion_count) }} opini publik.
            </p>

            <p class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 text-sm font-medium text-emerald-900">
                {{ comparison.verdict }}
            </p>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <section
                    v-for="side in comparison.sides"
                    :key="side.slug"
                    class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6"
                    :aria-label="side.name"
                >
                    <h2 class="text-lg font-bold text-neutral-900">
                        <Link :href="showEntity.url(side.slug)" class="hover:underline">{{ side.name }}</Link>
                    </h2>
                    <p class="text-xs text-neutral-500">{{ side.type_label }} · {{ side.category }}</p>

                    <div class="mt-4 flex items-baseline gap-1">
                        <span class="text-5xl font-black text-emerald-700">{{ Math.round(side.score) }}</span>
                        <span class="text-sm text-neutral-500">/100 Sentimen Netijen</span>
                    </div>
                    <p class="mt-1 text-sm font-semibold text-neutral-800">
                        {{ formatCount(side.opinion_count) }} opini netizen
                    </p>

                    <div class="mt-3 flex h-3 overflow-hidden rounded-full bg-neutral-100" role="img"
                        :aria-label="`${side.distribution.positive_pct}% positif, ${side.distribution.neutral_pct}% netral, ${side.distribution.negative_pct}% negatif`">
                        <div class="bg-emerald-600" :style="{ width: `${side.distribution.positive_pct}%` }" />
                        <div class="bg-neutral-400" :style="{ width: `${side.distribution.neutral_pct}%` }" />
                        <div class="bg-rose-500" :style="{ width: `${side.distribution.negative_pct}%` }" />
                    </div>
                    <p class="mt-1.5 text-xs text-neutral-600">
                        {{ Math.round(side.distribution.positive_pct) }}% positif ·
                        {{ Math.round(side.distribution.neutral_pct) }}% netral ·
                        {{ Math.round(side.distribution.negative_pct) }}% negatif
                    </p>

                    <h3 class="mt-5 text-xs font-bold tracking-wider text-neutral-500 uppercase">
                        Sering dipuji
                    </h3>
                    <ul v-if="side.positive_themes.length > 0" class="mt-2 flex flex-wrap gap-1.5">
                        <li v-for="theme in side.positive_themes" :key="theme.id"
                            class="rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800">
                            {{ theme.display_label }} (disebut {{ theme.observation_count }} kali)
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-xs text-neutral-500">Belum ada tema positif yang dominan.</p>

                    <h3 class="mt-4 text-xs font-bold tracking-wider text-neutral-500 uppercase">
                        Sering dikeluhkan
                    </h3>
                    <ul v-if="side.negative_themes.length > 0" class="mt-2 flex flex-wrap gap-1.5">
                        <li v-for="theme in side.negative_themes" :key="theme.id"
                            class="rounded-md bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-800">
                            {{ theme.display_label }} (disebut {{ theme.observation_count }} kali)
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-xs text-neutral-500">Belum ada keluhan berulang yang terdeteksi.</p>

                    <Link :href="showEntity.url(side.slug)"
                        class="mt-5 inline-flex min-h-11 items-center text-sm font-semibold text-emerald-700 hover:underline">
                        Lihat semua opini tentang {{ side.name }} →
                    </Link>
                </section>
            </div>

            <p class="mt-4 text-xs text-neutral-500">
                Dua skor ini dihitung terpisah dari opini publik masing-masing dan tidak digabung.
                Selisih skor menggambarkan sebaran opini, bukan kualitas produk.
                <Link :href="methodology()" class="font-semibold text-emerald-700 hover:underline">Cara menghitung</Link>
            </p>

            <section class="mt-8 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-bold text-neutral-900">Pertanyaan seputar {{ a.name }} vs {{ b.name }}</h2>
                <div class="mt-4 space-y-3">
                    <div v-for="(item, index) in comparisonSeo.faq" :key="index"
                        class="rounded-xl border border-neutral-100 bg-neutral-50/70 p-4">
                        <h3 class="text-sm font-bold text-neutral-900">{{ item.question }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-neutral-600">{{ item.answer }}</p>
                    </div>
                </div>
            </section>
        </main>
    </PublicLayout>
</template>
