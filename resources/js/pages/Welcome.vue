<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import CategoryBlock, {
    type CategoryBlockItem,
} from '@/components/CategoryBlock.vue';
import EntitySearch from '@/components/EntitySearch.vue';
import PublicSeo from '@/components/PublicSeo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import {
    getDirectWebsiteUrl,
    getFaviconUrl,
    trackSponsorClick,
} from '@/lib/sponsor';
import { methodology, sources } from '@/routes';
import { show as showEntity } from '@/routes/entities';
import { index as leaderboardPage } from '@/routes/leaderboard';
import { index as rankingIndex } from '@/routes/rankings';
import { index as searchPage } from '@/routes/search';
import { index as topicIndex, show as showTopic } from '@/routes/topics';
import { officialSocials } from '@/lib/socials';

interface SearchSuggestion {
    query: string;
    source: 'trending' | 'top_score' | 'fallback';
}

interface SponsorTeaserItem {
    id: number;
    rank: number;
    entity_id?: number;
    name: string;
    slug: string;
    type_label?: string;
    category_name: string;
    website_url?: string | null;
    description?: string | null;
    settled_total_amount: number;
    clicks_count?: number;
    views_count?: number;
    sentiment_score?: number | null;
    opinion_count?: number;
    rating_average?: number | null;
    rating_count?: number;
}

interface SponsorTeaser {
    period_key: string;
    period_name: string;
    total_settled_amount: number;
    is_empty: boolean;
    top_entry?: {
        name: string;
        settled_total_amount: number;
    };
    top_entries: SponsorTeaserItem[];
}

export interface PopularTopicItem {
    id: number;
    slug: string;
    title: string;
    keyword: string;
}

const props = withDefaults(
    defineProps<{
        categoryBlocks?: CategoryBlockItem[];
        popularTopics?: PopularTopicItem[];
        searchSuggestions?: SearchSuggestion[];
        sponsorTeaser?: SponsorTeaser | null;
    }>(),
    {
        categoryBlocks: () => [],
        popularTopics: () => [],
        searchSuggestions: () => [],
        sponsorTeaser: null,
    },
);

const page = usePage();
const seo = computed(
    () =>
        (page.props.seo as
            | { site_name?: string; site_url?: string }
            | undefined) ?? {},
);
const siteName = computed(() => seo.value.site_name || 'SuaraNetijen');
const siteUrl = computed(() =>
    (seo.value.site_url || 'https://suaranetijen.id').replace(/\/$/, ''),
);

const jsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@graph': [
            {
                '@type': 'WebSite',
                name: siteName.value,
                url: siteUrl.value,
            },
            {
                '@type': 'Organization',
                name: siteName.value,
                url: siteUrl.value,
                logo: `${siteUrl.value}/logo.svg`,
                sameAs: [
                    ...officialSocials.map((social) => social.url),
                    'https://www.linkedin.com/company/suaranetijen',
                    'https://github.com/azmifauzan/suaranetijen',
                ],
            },
        ],
    }),
);

const fallbackSuggestions: SearchSuggestion[] = [
    { query: 'IndiHome', source: 'fallback' },
    { query: 'Tokopedia', source: 'fallback' },
    { query: 'VPS Biznet', source: 'fallback' },
    { query: 'Samsung', source: 'fallback' },
];
const displayedSuggestions = computed(() =>
    props.searchSuggestions.length
        ? props.searchSuggestions
        : fallbackSuggestions,
);

function suggestionTitle(source: SearchSuggestion['source']): string {
    if (source === 'trending') return 'Paling banyak dicari';
    if (source === 'top_score') return 'Sentimen Netijen tinggi';

    return 'Contoh pencarian';
}

function formatRupiah(amount: number): string {
    return 'Rp' + amount.toLocaleString('id-ID');
}

const sponsorEntries = computed(() =>
    props.sponsorTeaser && !props.sponsorTeaser.is_empty
        ? props.sponsorTeaser.top_entries.slice(0, 10)
        : [],
);

function sponsorPlacement(rank: number): string {
    return rank <= 3 ? 'homepage_spotlight' : 'homepage_table';
}

function rebutUrl(entry: SponsorTeaserItem): string {
    return `/leaderboard?rebut_rank=${entry.rank}&target_name=${encodeURIComponent(entry.name)}&needed_amount=${entry.settled_total_amount + 1}#formSection`;
}

/** A real entity from the richest ranking, shown in the hero as a preview of what an entity page reports. */
const heroSample = computed(() => {
    const block = props.categoryBlocks[0];
    const entity = block?.top_entities[0];

    return block && entity ? { block, entity } : null;
});

const categoryGridClass = computed(() => {
    if (props.categoryBlocks.length === 1) return 'max-w-xl';
    if (props.categoryBlocks.length === 2 || props.categoryBlocks.length === 4)
        return 'md:grid-cols-2';

    return 'md:grid-cols-2 lg:grid-cols-3';
});
</script>

<template>
    <PublicLayout>
        <PublicSeo
            title="Sentimen Netizen Brand, Produk, dan Layanan di Indonesia"
            description="Indeks sentimen netizen tentang brand, produk, dan layanan di Indonesia. Lihat opini publik dan rating pengguna yang ditampilkan terpisah."
            canonical-path="/"
        />

        <Head>
            <component :is="'script'" type="application/ld+json">
                {{ jsonLd }}
            </component>
        </Head>

        <main>
            <section class="border-line bg-sage border-b">
                <div
                    class="mx-auto grid max-w-6xl items-center gap-10 px-5 py-10 sm:px-8 sm:py-16 lg:grid-cols-[minmax(0,1fr)_340px]"
                >
                    <div>
                        <h1
                            class="text-ink max-w-3xl text-[32px] leading-[1.1] font-bold tracking-[-1.5px] sm:text-5xl sm:tracking-[-2px]"
                        >
                            Sentimen netizen tentang brand, produk, dan layanan
                            di Indonesia
                        </h1>
                        <p
                            class="text-ink-muted mt-4 max-w-xl text-base leading-7"
                        >
                            Mau pilih brand, produk, atau layanan? Cari dulu,
                            lihat apa kata netizen.
                        </p>
                        <div class="mt-7 max-w-2xl"><EntitySearch /></div>

                        <div
                            class="text-ink-muted mt-3 flex flex-wrap items-center gap-2 text-sm"
                        >
                            <span class="mr-1">Coba cari:</span>
                            <Link
                                v-for="suggestion in displayedSuggestions"
                                :key="suggestion.query"
                                :href="
                                    searchPage({
                                        query: { q: suggestion.query },
                                    })
                                "
                                :title="suggestionTitle(suggestion.source)"
                                class="border-line-strong text-ink hover:border-brand/50 inline-flex min-h-11 items-center rounded-lg border bg-white/70 px-3 py-2 font-medium transition hover:bg-white"
                            >
                                {{ suggestion.query }}
                            </Link>
                        </div>
                    </div>

                    <aside
                        v-if="heroSample"
                        class="border-line-strong hidden rounded-2xl border bg-white p-6 lg:block"
                        aria-label="Contoh hasil"
                    >
                        <p class="text-ink-muted text-xs font-semibold">
                            Contoh dari peringkat {{ heroSample.block.name }}
                        </p>
                        <p class="text-ink mt-3 text-xl font-bold">
                            {{ heroSample.entity.name }}
                        </p>
                        <p class="text-ink-muted text-sm">
                            {{ heroSample.entity.category_name }}
                        </p>

                        <p class="text-ink mt-6 text-sm font-semibold">
                            Sentimen Netijen
                        </p>
                        <p class="flex items-baseline gap-1">
                            <span
                                class="text-brand text-5xl font-bold tracking-tight tabular-nums"
                            >
                                {{
                                    heroSample.entity.score.toLocaleString(
                                        'id-ID',
                                        {
                                            maximumFractionDigits: 1,
                                        },
                                    )
                                }}
                            </span>
                            <span class="text-ink-muted text-sm">/ 100</span>
                        </p>
                        <div
                            class="bg-line mt-3 h-2 overflow-hidden rounded-full"
                            aria-hidden="true"
                        >
                            <div
                                class="bg-score-high h-full rounded-full"
                                :style="{
                                    width: `${Math.min(100, Math.max(0, heroSample.entity.score))}%`,
                                }"
                            ></div>
                        </div>
                        <p class="text-ink-muted mt-3 text-sm">
                            Dihitung dari
                            {{
                                heroSample.entity.opinion_count.toLocaleString(
                                    'id-ID',
                                )
                            }}
                            opini netizen.
                        </p>

                        <Link
                            :href="showEntity(heroSample.entity.slug)"
                            class="bg-brand hover:bg-brand-strong mt-6 flex min-h-11 items-center justify-between rounded-lg px-4 text-sm font-bold text-white transition"
                        >
                            Lihat apa kata netizen
                        </Link>
                    </aside>
                </div>
            </section>

            <section
                v-if="popularTopics.length > 0"
                class="border-line border-b bg-white"
                aria-labelledby="topics-heading"
            >
                <div
                    class="mx-auto flex max-w-6xl flex-col gap-4 px-5 py-8 sm:px-8 lg:flex-row lg:items-start lg:gap-10"
                >
                    <h2
                        id="topics-heading"
                        class="text-ink shrink-0 text-lg font-bold lg:w-56 lg:pt-2"
                    >
                        Yang sering dibicarakan netizen
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            v-for="topic in popularTopics"
                            :key="topic.id"
                            :href="showTopic(topic.slug)"
                            class="border-line bg-paper text-ink hover:border-brand/50 hover:text-brand inline-flex min-h-11 items-center rounded-lg border px-3.5 py-2 text-sm font-medium transition hover:bg-white"
                        >
                            {{ topic.title }}
                        </Link>
                        <Link
                            :href="topicIndex()"
                            class="text-brand inline-flex min-h-11 items-center px-2 text-sm font-bold hover:underline"
                        >
                            Semua topik
                        </Link>
                    </div>
                </div>
            </section>

            <section
                v-if="categoryBlocks.length > 0"
                class="border-line bg-paper border-b"
                aria-labelledby="categories-heading"
            >
                <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8 sm:py-16">
                    <div
                        class="mb-8 flex flex-wrap items-end justify-between gap-4"
                    >
                        <div>
                            <h2
                                id="categories-heading"
                                class="text-ink text-2xl font-bold tracking-tight sm:text-3xl"
                            >
                                Sentimen netijen tertinggi per kategori
                            </h2>
                            <p
                                class="text-ink-muted mt-2 max-w-xl text-sm leading-6"
                            >
                                SuaraNetijen hanya memasukkan entitas dengan
                                minimal 100 opini ke peringkat.
                            </p>
                        </div>
                        <Link
                            :href="rankingIndex()"
                            class="text-brand inline-flex min-h-11 items-center gap-1.5 text-sm font-bold hover:underline"
                        >
                            Semua peringkat
                        </Link>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-5"
                        :class="categoryGridClass"
                    >
                        <CategoryBlock
                            v-for="block in categoryBlocks"
                            :key="block.id"
                            :block="block"
                        />
                    </div>
                </div>
            </section>

            <section
                class="border-paid-line bg-paid-tint border-b"
                aria-labelledby="sponsor-heading"
            >
                <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8 sm:py-14">
                    <div
                        class="mb-6 flex flex-wrap items-end justify-between gap-4"
                    >
                        <div>
                            <h2
                                id="sponsor-heading"
                                class="text-paid-ink text-2xl font-bold tracking-tight sm:text-3xl"
                            >
                                Papan Sponsor
                            </h2>
                            <p
                                class="text-paid-muted mt-2 max-w-2xl text-sm leading-6"
                            >
                                Urutan mengikuti nominal sponsor terkonfirmasi,
                                terpisah dari skor Sentimen Netijen. Periode
                                {{
                                    sponsorTeaser?.period_name || 'Minggu Ini'
                                }}.
                            </p>
                        </div>
                        <Link
                            :href="leaderboardPage()"
                            class="border-paid-line text-paid hover:border-star inline-flex min-h-11 items-center rounded-lg border bg-white px-4 text-sm font-bold transition"
                        >
                            Lihat papan & ikut sponsor
                        </Link>
                    </div>

                    <ol
                        v-if="sponsorEntries.length > 0"
                        class="divide-paid-line border-paid-line divide-y overflow-hidden rounded-2xl border bg-white"
                    >
                        <li
                            v-for="entry in sponsorEntries"
                            :key="entry.id"
                            class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:gap-4 sm:px-5"
                        >
                            <div
                                class="relative flex min-w-0 flex-1 items-start gap-3"
                            >
                                <span
                                    class="inline-flex size-7 shrink-0 items-center justify-center rounded-md text-xs font-black tabular-nums"
                                    :class="
                                        entry.rank <= 3
                                            ? 'bg-paid-soft text-paid'
                                            : 'bg-paper text-paid-muted'
                                    "
                                >
                                    {{ entry.rank }}
                                </span>
                                <img
                                    v-if="getFaviconUrl(entry.website_url)"
                                    :src="getFaviconUrl(entry.website_url)!"
                                    alt=""
                                    class="mt-0.5 size-6 shrink-0 rounded-md border border-black/10 bg-white object-contain p-0.5"
                                    loading="lazy"
                                    @error="
                                        (e) =>
                                            ((
                                                e.target as HTMLElement
                                            ).style.display = 'none')
                                    "
                                />
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="flex min-w-0 items-baseline gap-2"
                                    >
                                        <Link
                                            :href="showEntity(entry.slug)"
                                            class="text-ink hover:text-brand truncate text-sm font-bold after:absolute after:inset-0"
                                        >
                                            {{ entry.name }}
                                        </Link>
                                        <span
                                            class="text-paid-muted shrink-0 text-xs"
                                        >
                                            {{ entry.category_name }}
                                        </span>
                                    </p>
                                    <p
                                        v-if="entry.description"
                                        class="text-paid-muted truncate text-xs leading-5"
                                    >
                                        {{ entry.description }}
                                    </p>
                                    <p
                                        class="text-paid-muted mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs"
                                    >
                                        <span
                                            v-if="
                                                typeof entry.sentiment_score ===
                                                'number'
                                            "
                                            title="Sentimen Netijen"
                                        >
                                            Sentimen
                                            <strong class="text-brand-deep">{{
                                                entry.sentiment_score.toLocaleString(
                                                    'id-ID',
                                                    {
                                                        maximumFractionDigits: 1,
                                                    },
                                                )
                                            }}</strong>
                                        </span>
                                        <span v-else>Belum ada skor</span>
                                        <span
                                            class="inline-flex items-center gap-1 whitespace-nowrap"
                                            title="Kunjungan halaman"
                                        >
                                            {{
                                                (
                                                    entry.views_count || 0
                                                ).toLocaleString('id-ID')
                                            }}
                                            dilihat
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1 whitespace-nowrap"
                                            title="Klik ke situs resmi"
                                        >
                                            {{
                                                (
                                                    entry.clicks_count || 0
                                                ).toLocaleString('id-ID')
                                            }}
                                            klik
                                        </span>
                                        <span
                                            v-if="entry.rating_average"
                                            class="inline-flex items-center gap-1 whitespace-nowrap"
                                            title="Rating Netijen"
                                        >
                                            Rating
                                            {{
                                                entry.rating_average.toFixed(1)
                                            }}
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <div
                                class="flex items-center justify-between gap-3 pl-10 sm:justify-end sm:pl-0"
                            >
                                <span
                                    class="text-paid text-sm font-black tabular-nums"
                                >
                                    {{
                                        formatRupiah(entry.settled_total_amount)
                                    }}
                                </span>
                                <div class="flex items-center gap-1.5">
                                    <a
                                        :href="
                                            getDirectWebsiteUrl(
                                                entry.website_url,
                                                entry.slug,
                                                {
                                                    placement: sponsorPlacement(
                                                        entry.rank,
                                                    ),
                                                },
                                            )
                                        "
                                        :ping="`/api/sponsor/click/${entry.slug}`"
                                        target="_blank"
                                        rel="noopener"
                                        class="bg-brand-tint text-brand-deep hover:bg-brand-tint-strong inline-flex min-h-11 items-center rounded-lg px-3 text-xs font-bold transition"
                                        @click="
                                            trackSponsorClick(entry.slug, {
                                                placement: sponsorPlacement(
                                                    entry.rank,
                                                ),
                                                url:
                                                    entry.website_url ||
                                                    undefined,
                                            })
                                        "
                                    >
                                        Buka situs
                                    </a>
                                    <Link
                                        :href="rebutUrl(entry)"
                                        class="border-paid-line text-paid hover:bg-paid-soft inline-flex min-h-11 items-center rounded-lg border px-3 text-xs font-bold transition"
                                    >
                                        Rebut #{{ entry.rank }}
                                    </Link>
                                </div>
                            </div>
                        </li>
                    </ol>

                    <div
                        v-else
                        class="border-paid-line rounded-2xl border border-dashed bg-white p-6 text-center"
                    >
                        <p class="text-paid-ink text-base font-bold">
                            Papan Sponsor periode ini masih kosong
                        </p>
                        <p
                            class="text-paid-muted mx-auto mt-1 max-w-md text-sm leading-6"
                        >
                            Brand, produk, atau layanan pertama yang mendaftar
                            langsung menempati posisi #1.
                        </p>
                        <Link
                            :href="leaderboardPage()"
                            class="bg-paid-strong hover:bg-paid-deep mt-4 inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-bold text-white transition"
                        >
                            Sponsori sekarang
                        </Link>
                    </div>
                </div>
            </section>

            <section class="bg-white" aria-labelledby="metrics-heading">
                <div
                    class="mx-auto grid max-w-6xl gap-10 px-5 py-12 sm:px-8 sm:py-16 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]"
                >
                    <div>
                        <h2
                            id="metrics-heading"
                            class="text-ink text-2xl font-bold tracking-tight sm:text-3xl"
                        >
                            Tiga angka, dihitung terpisah
                        </h2>
                        <p
                            class="text-ink-muted mt-3 max-w-md text-sm leading-6"
                        >
                            SuaraNetijen tidak pernah menggabungkan opini publik
                            dengan rating pengguna. Cara hitung dan daftar
                            sumber terbuka, supaya kamu bisa menilai sendiri.
                        </p>
                        <div
                            class="mt-5 flex flex-wrap gap-x-5 gap-y-1 text-sm font-bold"
                        >
                            <Link
                                :href="methodology()"
                                class="text-brand inline-flex min-h-11 items-center hover:underline"
                            >
                                Baca metodologi
                            </Link>
                            <Link
                                :href="sources()"
                                class="text-brand inline-flex min-h-11 items-center hover:underline"
                            >
                                Lihat sumber data
                            </Link>
                        </div>
                    </div>

                    <dl class="divide-line border-line divide-y border-y">
                        <div
                            class="grid gap-1 py-5 sm:grid-cols-[180px_minmax(0,1fr)] sm:gap-6"
                        >
                            <dt class="text-ink font-bold">
                                Sentimen Netijen
                                <span
                                    class="text-ink-muted block text-xs font-medium"
                                    >skor 0 sampai 100</span
                                >
                            </dt>
                            <dd class="text-ink-muted text-sm leading-6">
                                Opini positif dihitung penuh, netral setengah,
                                negatif nol. Skor tampil setelah ada minimal 30
                                opini.
                            </dd>
                        </div>
                        <div
                            class="grid gap-1 py-5 sm:grid-cols-[180px_minmax(0,1fr)] sm:gap-6"
                        >
                            <dt class="text-ink font-bold">
                                Top Suara Netijen
                                <span
                                    class="text-ink-muted block text-xs font-medium"
                                    >tema terbanyak</span
                                >
                            </dt>
                            <dd class="text-ink-muted text-sm leading-6">
                                Hal yang paling sering dipuji atau dikeluhkan
                                netizen, diurutkan dari seberapa sering muncul.
                                Tanpa skor per tema.
                            </dd>
                        </div>
                        <div
                            class="grid gap-1 py-5 sm:grid-cols-[180px_minmax(0,1fr)] sm:gap-6"
                        >
                            <dt class="text-ink font-bold">
                                Rating Netijen
                                <span
                                    class="text-ink-muted block text-xs font-medium"
                                    >bintang 1 sampai 5</span
                                >
                            </dt>
                            <dd class="text-ink-muted text-sm leading-6">
                                Bintang yang diberikan pengguna SuaraNetijen
                                langsung, terpisah dari opini yang dikumpulkan
                                dari internet.
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>
        </main>
    </PublicLayout>
</template>
