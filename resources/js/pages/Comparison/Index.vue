<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicSeo from '@/components/PublicSeo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home, methodology } from '@/routes';
import { show as showCategory } from '@/routes/categories';
import { show as showComparison } from '@/routes/comparisons';

interface Side {
    name: string;
    slug: string;
    score: number;
    opinion_count: number;
}

interface Group {
    category: string;
    category_slug: string;
    pairs: Array<{ pair: string; label: string; sides: Side[] }>;
}

const props = defineProps<{ groups: Group[]; total: number }>();

const page = usePage();
const siteUrl = computed(() => {
    const seo = page.props.seo as { site_url?: string } | undefined;

    return (seo?.site_url ?? '').replace(/\/$/, '');
});

const formatCount = (value: number): string => value.toLocaleString('id-ID');

const jsonLd = computed(() => ({
    '@context': 'https://schema.org',
    '@graph': [
        {
            '@type': 'BreadcrumbList',
            itemListElement: [
                { '@type': 'ListItem', position: 1, name: 'Beranda', item: `${siteUrl.value}/` },
                { '@type': 'ListItem', position: 2, name: 'Perbandingan', item: `${siteUrl.value}/banding` },
            ],
        },
        {
            '@type': 'ItemList',
            itemListElement: props.groups
                .flatMap((group) => group.pairs)
                .map((item, index) => ({
                    '@type': 'ListItem',
                    position: index + 1,
                    name: item.label,
                    url: `${siteUrl.value}/banding/${item.pair}`,
                })),
        },
    ],
}));
</script>

<template>
    <PublicLayout>
        <PublicSeo
            title="Perbandingan Sentimen Netizen: Bagus Mana?"
            description="Bandingkan sentimen netizen dua merek berdampingan, dari HP sampai mobil. Skor, jumlah opini, dan tema yang sering dipuji atau dikeluhkan untuk tiap sisi."
            canonical-path="/banding"
            :robots="total > 0 ? 'index, follow' : 'noindex, follow'"
        >
            <component :is="'script'" type="application/ld+json">
                {{ JSON.stringify(jsonLd) }}
            </component>
        </PublicSeo>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <nav class="mb-4 flex items-center gap-2 text-xs text-neutral-500">
                <Link :href="home()" class="hover:underline">Beranda</Link>
                <span>/</span>
                <span class="font-medium text-neutral-800">Perbandingan</span>
            </nav>

            <h1 class="text-2xl font-black tracking-tight text-neutral-900 sm:text-3xl">
                Perbandingan Sentimen Netizen
            </h1>
            <p class="mt-2 max-w-2xl text-sm text-neutral-600 sm:text-base">
                Dua merek, satu halaman: skor Sentimen Netijen, jumlah opini, dan tema yang paling sering dipuji atau
                dikeluhkan netizen, berdampingan. Tiap skor dihitung sendiri dan tidak digabung.
                <Link :href="methodology()" class="font-semibold text-emerald-700 hover:underline">Cara menghitung</Link>
            </p>

            <p v-if="total === 0" class="mt-8 rounded-2xl border border-dashed border-neutral-300 bg-white p-6 text-center text-sm text-neutral-500">
                Belum ada perbandingan yang memenuhi syarat minimal 30 opini untuk kedua sisinya.
            </p>

            <section v-for="group in groups" :key="group.category_slug" class="mt-8" :aria-labelledby="`cat-${group.category_slug}`">
                <h2 :id="`cat-${group.category_slug}`" class="text-lg font-bold text-neutral-900">
                    {{ group.category }}
                    <Link :href="showCategory.url(group.category_slug)" class="ml-2 text-xs font-semibold text-emerald-700 hover:underline">
                        Semua {{ group.category }} →
                    </Link>
                </h2>

                <ul class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <li v-for="item in group.pairs" :key="item.pair">
                        <Link
                            :href="showComparison.url(item.pair)"
                            class="block min-h-11 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50/40"
                        >
                            <span class="text-sm font-bold text-neutral-900">{{ item.label }}</span>
                            <span class="mt-3 grid grid-cols-2 gap-3 text-xs text-neutral-600">
                                <span v-for="side in item.sides" :key="side.slug" class="block">
                                    <span class="block truncate font-semibold text-neutral-800">{{ side.name }}</span>
                                    <span class="mt-0.5 block">
                                        <span class="text-xl font-black text-emerald-700">{{ Math.round(side.score) }}</span>
                                        <span class="text-neutral-500">/100</span>
                                    </span>
                                    <span class="block text-neutral-500">{{ formatCount(side.opinion_count) }} opini</span>
                                </span>
                            </span>
                        </Link>
                    </li>
                </ul>
            </section>
        </main>
    </PublicLayout>
</template>
