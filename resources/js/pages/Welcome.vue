<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    AudioLines,
    Eye,
    MessageCircle,
    MousePointerClick,
    ShieldCheck,
    Star,
    TrendingUp,
    Trophy,
} from '@lucide/vue';
import { computed } from 'vue';
import CategoryBlock, { type CategoryBlockItem } from '@/components/CategoryBlock.vue';
import EntitySearch from '@/components/EntitySearch.vue';
import PublicSeo from '@/components/PublicSeo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { getDirectWebsiteUrl, getFaviconUrl, trackSponsorClick } from '@/lib/sponsor';
import { methodology, sources } from '@/routes';
import { show as showEntity } from '@/routes/entities';
import { index as leaderboardPage } from '@/routes/leaderboard';
import { index as rankingIndex } from '@/routes/rankings';
import { index as searchPage } from '@/routes/search';
import { index as topicIndex, show as showTopic } from '@/routes/topics';

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
const seo = computed(() => (page.props.seo as { site_name?: string; site_url?: string } | undefined) ?? {});
const siteName = computed(() => seo.value.site_name || 'SuaraNetijen');
const siteUrl = computed(() => (seo.value.site_url || 'https://suaranetijen.id').replace(/\/$/, ''));

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
            <!-- 1. Hero Section -->
            <section class="relative border-b border-[#e0e9dd] bg-[#eff7eb]">
                <div
                    class="pointer-events-none absolute inset-0 overflow-hidden"
                    aria-hidden="true"
                >
                    <div
                        class="absolute -top-40 -right-32 size-[520px] rounded-full border border-[#d5e5ce]"
                    ></div>
                    <div
                        class="absolute -top-20 -right-12 size-[360px] rounded-full border border-[#d5e5ce]"
                    ></div>
                    <div
                        class="absolute -bottom-44 -left-36 size-96 rounded-full border border-[#d5e5ce]"
                    ></div>
                    <div
                        class="absolute top-24 left-[8%] hidden -rotate-12 rounded-2xl border border-[#d7e6d2] bg-[#f9fcf6] p-4 lg:block"
                    >
                        <MessageCircle class="size-7 text-[#89b58c]" />
                    </div>
                    <div
                        class="absolute right-[8%] bottom-20 hidden rotate-12 rounded-2xl border border-[#d7e6d2] bg-[#f9fcf6] p-4 lg:block"
                    >
                        <AudioLines class="size-7 text-[#89b58c]" />
                    </div>
                </div>
                <div
                    class="relative mx-auto max-w-6xl px-5 pt-7 pb-7 text-center sm:px-8 sm:pt-9 sm:pb-9"
                >
                    <div
                        class="mb-3 inline-flex items-center gap-2 rounded-full border border-[#cddfc6] bg-white/65 px-3 py-1.5 text-[11px] font-semibold tracking-wide text-[#4c7050] sm:text-xs"
                    >
                        <span class="size-1.5 rounded-full bg-[#238b55]"></span>
                        INDEKS SENTIMEN PUBLIK INDONESIA
                    </div>
                    <h1
                        class="text-[30px] leading-[1.12] font-bold tracking-[-1.5px] text-[#193e2d] sm:text-5xl sm:tracking-[-2px] lg:text-5xl"
                    >
                        Sentimen netizen tentang brand, produk, dan layanan di Indonesia
                    </h1>
                    <p
                        class="mx-auto mt-3 max-w-2xl text-sm leading-6 text-[#61725f] sm:text-base lg:whitespace-nowrap"
                    >
                        Mau pilih brand, produk, atau layanan? Cari dulu, lihat apa kata netizen.
                    </p>
                    <div class="mx-auto mt-5 max-w-4xl"><EntitySearch /></div>

                    <!-- Search suggestions directly under search input -->
                    <div
                        class="mt-3 flex flex-wrap items-center justify-center gap-2 text-xs text-[#667861]"
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
                            class="flex min-h-11 items-center gap-1 rounded-full border border-[#d8e4d1] bg-white/65 px-3 py-2.5 transition hover:border-[#81ad83] hover:bg-white"
                        >
                            {{ suggestion.query }}
                            <ArrowUpRight class="size-3" />
                        </Link>
                    </div>

                    <!-- Top 3 Leaderboard Podium below search box -->
                    <div
                        v-if="sponsorTeaser && !sponsorTeaser.is_empty && sponsorTeaser.top_entries && sponsorTeaser.top_entries.length > 0"
                        class="mx-auto mt-4 max-w-4xl text-left"
                    >
                        <div class="mb-2 flex items-center justify-between gap-2 px-1">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-[#dfd3be] bg-[#fffcf5] px-2.5 py-0.5 text-[10px] font-bold tracking-wider text-[#92400e] uppercase">
                                    <Trophy class="size-3 text-[#d97706]" />
                                    Papan Sponsor · #1–#3
                                </span>
                                <span class="hidden text-[11px] text-neutral-500 sm:inline">
                                    Periode {{ sponsorTeaser.period_name || 'Minggu Ini' }}
                                </span>
                            </div>
                            <Link
                                :href="leaderboardPage()"
                                class="inline-flex min-h-11 items-center text-[11px] font-bold text-[#92400e] hover:text-[#d97706]"
                            >
                                Buka Papan Sponsor
                            </Link>
                        </div>

                        <p class="mb-2 px-1 text-[11px] text-neutral-500">
                            Urutan berdasarkan nominal sponsor terkonfirmasi, bukan skor Sentimen Netijen.
                        </p>

                        <div class="space-y-1.5">
                            <div
                                v-for="entry in sponsorTeaser.top_entries.slice(0, 3)"
                                :key="entry.id"
                                class="flex flex-col gap-2 rounded-xl border bg-white px-3 py-2 transition hover:border-[#8ab591] sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                :class="
                                    entry.rank === 1
                                        ? 'border-[#f2ddb3] bg-[#fffefb]'
                                        : entry.rank === 2
                                          ? 'border-[#dce4dd] bg-white'
                                          : 'border-[#e8dfd5] bg-white'
                                "
                            >
                                <!-- Left: Rank, Favicon, Name, Category, Description -->
                                <div class="flex min-w-0 flex-1 items-start gap-2.5">
                                    <span
                                        class="inline-flex size-6 shrink-0 items-center justify-center rounded-md text-xs font-black"
                                        :class="
                                            entry.rank === 1
                                                ? 'bg-[#fef3c7] text-[#92400e] border border-[#fde68a]'
                                                : entry.rank === 2
                                                  ? 'bg-[#e2e8f0] text-[#334155] border border-[#cbd5e1]'
                                                  : 'bg-[#ffedd5] text-[#9a3412] border border-[#fed7aa]'
                                        "
                                    >
                                        #{{ entry.rank }}
                                    </span>

                                    <img
                                        v-if="getFaviconUrl(entry.website_url)"
                                        :src="getFaviconUrl(entry.website_url)!"
                                        :alt="entry.name"
                                        class="size-6 shrink-0 rounded-md border border-black/10 bg-white object-contain p-0.5"
                                        loading="lazy"
                                        @error="(e) => ((e.target as HTMLElement).style.display = 'none')"
                                    />

                                    <div class="min-w-0 flex-1">
                                        <div class="flex min-w-0 items-center gap-1.5">
                                            <Link
                                                :href="showEntity(entry.slug)"
                                                class="truncate text-sm font-bold text-[#18392d] hover:text-[#087f5b]"
                                            >
                                                {{ entry.name }}
                                            </Link>
                                            <span class="shrink-0 rounded-md bg-black/5 px-2 py-0.5 text-[10px] font-medium text-[#5a6b60]">
                                                {{ entry.category_name }}
                                            </span>
                                        </div>
                                        <p
                                            v-if="entry.description"
                                            class="mt-0.5 truncate text-[11px] leading-4 text-[#55695a]"
                                        >
                                            {{ entry.description }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Right: Stats (Mobile & Desktop) + Total Sponsor + Actions -->
                                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5 border-t border-[#edf1ec] pt-1.5 sm:border-0 sm:pt-0 sm:justify-end">
                                    <div class="flex items-center gap-2 text-[11px] text-[#55695a]">
                                        <span
                                            v-if="typeof entry.sentiment_score === 'number'"
                                            class="inline-flex items-center gap-0.5 font-bold text-[#1e6b42]"
                                            title="Sentimen Netijen"
                                        >
                                            Sentimen {{ entry.sentiment_score.toLocaleString('id-ID', { maximumFractionDigits: 1 }) }}
                                        </span>
                                        <span v-else class="text-neutral-500">Tanpa skor</span>

                                        <span>•</span>

                                        <span class="inline-flex items-center gap-1" title="Kunjungan detail">
                                            <Eye class="size-3 text-[#2563eb]" />
                                            {{ (entry.views_count || 0).toLocaleString('id-ID') }}
                                        </span>

                                        <span>•</span>

                                        <span class="inline-flex items-center gap-1" title="Klik website resmi">
                                            <MousePointerClick class="size-3 text-[#087f5b]" />
                                            {{ (entry.clicks_count || 0).toLocaleString('id-ID') }} klik
                                        </span>

                                        <template v-if="entry.rating_average">
                                            <span>•</span>
                                            <span class="inline-flex items-center gap-1 text-[#92400e]" title="Rating Netijen">
                                                <Star class="size-3 fill-[#d97706] text-[#d97706]" />
                                                <strong class="font-bold">{{ entry.rating_average.toFixed(1) }}</strong>
                                            </span>
                                        </template>
                                    </div>

                                    <div class="text-right">
                                        <span class="text-xs font-black text-[#92400e] sm:text-sm">
                                            {{ formatRupiah(entry.settled_total_amount) }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-1.5">
                                        <a
                                            :href="getDirectWebsiteUrl(entry.website_url, entry.slug, { placement: 'homepage_spotlight' })"
                                            :ping="`/api/sponsor/click/${entry.slug}`"
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[#eaf7ee] px-2.5 py-2.5 text-xs font-bold text-[#145736] transition hover:bg-[#d6f0dd]"
                                            @click="trackSponsorClick(entry.slug, { placement: 'homepage_spotlight', url: entry.website_url || undefined })"
                                        >
                                            Buka Situs
                                        </a>
                                        <Link
                                            :href="`/leaderboard?rebut_rank=${entry.rank}&target_name=${encodeURIComponent(entry.name)}&needed_amount=${entry.settled_total_amount + 1}#formSection`"
                                            class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[#f59e0b] bg-[#fffbeb] px-2.5 py-2.5 text-xs font-bold text-[#92400e] transition hover:bg-[#fef3c7]"
                                        >
                                            Rebut #{{ entry.rank }}
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state if no sponsor -->
                    <div v-else class="mt-4">
                        <Link
                            :href="leaderboardPage()"
                            class="inline-flex min-h-11 items-center gap-2 rounded-full border border-[#edd5b1] bg-white/80 px-3.5 py-2.5 text-xs text-[#8a5d1a] shadow-xs transition hover:border-[#d97706] hover:bg-white"
                        >
                            <Trophy class="size-3.5 text-[#d97706]" />
                            <span class="font-bold">Leaderboard: Belum ada sponsor periode ini</span>
                            <span class="text-[#b45309] font-medium">— Jadilah #1 sekarang!</span>
                            <ArrowRight class="size-3 text-[#b45309]" />
                        </Link>
                    </div>
                </div>
            </section>

            <!-- Value props banner -->
            <div class="border-b border-[#e6e9e1] bg-white">
                <div
                    class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-8 gap-y-2 px-5 py-3 text-xs text-[#66746b] sm:px-8 sm:text-sm"
                >
                    <span class="flex items-center gap-2">
                        <MessageCircle class="size-4 text-[#087f5b]" />
                        Opini dari percakapan publik
                    </span>
                    <Link
                        :href="methodology()"
                        class="inline-flex min-h-11 items-center gap-2 py-2.5 hover:text-[#087f5b]"
                    >
                        <ShieldCheck class="size-4 text-[#087f5b]" />
                        Metodologi terbuka
                    </Link>
                    <span class="flex items-center gap-2">
                        <Star class="size-4 text-[#087f5b]" />
                        Sentimen & rating terpisah
                    </span>
                </div>
            </div>

            <!-- 2. Yang Sering Dibicarakan Netizen (Max 12 Published Indexable Topics) -->
            <section
                v-if="popularTopics && popularTopics.length > 0"
                class="border-b border-[#e5e9e0] bg-white"
                aria-labelledby="topics-heading"
            >
                <div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-12">
                    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="mb-1 flex items-center gap-1.5 text-xs font-bold tracking-wider text-[#69796c] uppercase">
                                <TrendingUp class="size-3.5 text-[#087f5b]" />
                                Topik Hangat
                            </p>
                            <h2
                                id="topics-heading"
                                class="text-2xl font-bold tracking-tight text-[#18392d] sm:text-3xl"
                            >
                                Yang sering dibicarakan netizen
                            </h2>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5">
                        <Link
                            v-for="topic in popularTopics"
                            :key="topic.id"
                            :href="showTopic(topic.slug)"
                            class="group inline-flex min-h-11 items-center gap-2 rounded-xl border border-[#dfe5dc] bg-[#f9fbf8] px-3.5 py-2.5 text-xs font-semibold text-[#1f3b28] transition hover:border-[#8ab591] hover:bg-white hover:text-[#087f5b] hover:shadow-xs"
                        >
                            <span>{{ topic.title }}</span>
                        </Link>

                        <Link
                            :href="topicIndex()"
                            class="inline-flex min-h-11 items-center gap-1 rounded-xl border border-dashed border-[#b8cbbd] px-3.5 py-2.5 text-xs font-bold text-[#087f5b] transition hover:border-[#087f5b] hover:bg-[#f0f8f2]"
                        >
                            Lihat Semua Topik
                            <ArrowRight class="size-3.5" />
                        </Link>
                    </div>
                </div>
            </section>

            <!-- 3. Dedicated Top 10 Leaderboard Section (#4 - #10) -->
            <section
                class="border-b border-[#d7e6d2] bg-[#f9fcf6]"
                aria-labelledby="top-leaderboard-heading"
            >
                <div class="mx-auto max-w-6xl px-5 pt-6 pb-6 sm:px-8 sm:pt-8 sm:pb-8">
                    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <div class="mb-1 inline-flex items-center gap-1.5 rounded-full border border-[#dfd3be] bg-[#fffcf5] px-2.5 py-0.5 text-[10px] font-bold tracking-wider text-[#92400e] uppercase">
                                <Trophy class="size-3 text-[#d97706]" />
                                Papan Sponsor
                            </div>
                            <h2
                                id="top-leaderboard-heading"
                                class="text-xl font-bold tracking-tight text-[#18392d] sm:text-2xl"
                            >
                                Posisi #4 – #10
                            </h2>
                            <p class="mt-0.5 text-xs text-[#61725f] sm:text-sm">
                                Urutan mengikuti nominal sponsor terkonfirmasi, terpisah dari ranking sentimen. Periode {{ sponsorTeaser?.period_name || 'Minggu Ini' }}.
                            </p>
                        </div>
                        <Link
                            :href="leaderboardPage()"
                            class="inline-flex min-h-11 items-center rounded-lg border border-[#dfcca9] bg-white px-3.5 py-2.5 text-xs font-bold text-[#92400e] shadow-2xs transition hover:border-[#d97706] hover:bg-[#fffbf2]"
                        >
                            Lihat Semua Peringkat & Ikut Sponsor
                        </Link>
                    </div>

                    <!-- Active Listings (#4 - #10) -->
                    <div
                        v-if="sponsorTeaser && !sponsorTeaser.is_empty && sponsorTeaser.top_entries.slice(3, 10).length > 0"
                        class="space-y-1.5"
                    >
                        <div
                            v-for="entry in sponsorTeaser.top_entries.slice(3, 10)"
                            :key="entry.id"
                            class="flex flex-col gap-2 rounded-xl border border-[#e2e7df] bg-white px-3 py-2 transition hover:border-[#b8cfbe] sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                        >
                            <div class="flex min-w-0 flex-1 items-start gap-2.5">
                                <span
                                    class="inline-flex size-6 shrink-0 items-center justify-center rounded-md bg-[#f1f5f0] text-xs font-black text-[#4d5e52] border border-[#e2e7df]"
                                >
                                    #{{ entry.rank }}
                                </span>
                                <img
                                    v-if="getFaviconUrl(entry.website_url)"
                                    :src="getFaviconUrl(entry.website_url)!"
                                    :alt="entry.name"
                                    class="size-6 shrink-0 rounded-md border border-black/10 bg-white object-contain p-0.5"
                                    loading="lazy"
                                    @error="(e) => ((e.target as HTMLElement).style.display = 'none')"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex min-w-0 items-center gap-1.5">
                                        <Link
                                            :href="showEntity(entry.slug)"
                                            class="truncate text-sm font-bold text-[#18392d] hover:text-[#087f5b]"
                                        >
                                            {{ entry.name }}
                                        </Link>
                                        <span class="shrink-0 rounded-md bg-black/5 px-2 py-0.5 text-[10px] font-medium text-[#5a6b60]">
                                            {{ entry.category_name }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="entry.description"
                                        class="mt-0.5 truncate text-[11px] leading-4 text-[#55695a]"
                                    >
                                        {{ entry.description }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5 border-t border-[#edf1ec] pt-1.5 sm:border-0 sm:pt-0 sm:justify-end">
                                <div class="flex items-center gap-2 text-[11px] text-[#55695a]">
                                    <span
                                        v-if="typeof entry.sentiment_score === 'number'"
                                        class="inline-flex items-center gap-0.5 font-bold text-[#1e6b42]"
                                        title="Sentimen Netijen"
                                    >
                                        Sentimen {{ entry.sentiment_score.toLocaleString('id-ID', { maximumFractionDigits: 1 }) }}
                                    </span>
                                    <span v-else class="text-neutral-500">Tanpa skor</span>

                                    <span>•</span>

                                    <span class="inline-flex items-center gap-1" title="Kunjungan detail">
                                        <Eye class="size-3 text-[#2563eb]" />
                                        {{ (entry.views_count || 0).toLocaleString('id-ID') }}
                                    </span>

                                    <span>•</span>

                                    <span class="inline-flex items-center gap-1" title="Klik website resmi">
                                        <MousePointerClick class="size-3 text-[#087f5b]" />
                                        {{ (entry.clicks_count || 0).toLocaleString('id-ID') }} klik
                                    </span>

                                    <template v-if="entry.rating_average">
                                        <span>•</span>
                                        <span class="inline-flex items-center gap-1 text-[#92400e]" title="Rating Netijen">
                                            <Star class="size-3 fill-[#d97706] text-[#d97706]" />
                                            <strong class="font-bold">{{ entry.rating_average.toFixed(1) }}</strong>
                                        </span>
                                    </template>
                                </div>

                                <div class="text-right">
                                    <span class="text-xs font-black text-[#92400e] sm:text-sm">
                                        {{ formatRupiah(entry.settled_total_amount) }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <a
                                        :href="getDirectWebsiteUrl(entry.website_url, entry.slug, { placement: 'homepage_table' })"
                                        :ping="`/api/sponsor/click/${entry.slug}`"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[#eaf7ee] px-2.5 py-2.5 text-xs font-bold text-[#145736] transition hover:bg-[#d6f0dd]"
                                        @click="trackSponsorClick(entry.slug, { placement: 'homepage_table', url: entry.website_url || undefined })"
                                    >
                                        Buka Situs
                                    </a>
                                    <Link
                                        :href="`/leaderboard?rebut_rank=${entry.rank}&target_name=${encodeURIComponent(entry.name)}&needed_amount=${entry.settled_total_amount + 1}#formSection`"
                                        class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[#f59e0b] bg-[#fffbeb] px-2.5 py-2.5 text-xs font-bold text-[#92400e] transition hover:bg-[#fef3c7]"
                                    >
                                        Rebut #{{ entry.rank }}
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Spot #4 - #10 Open State -->
                    <div
                        v-else-if="sponsorTeaser && !sponsorTeaser.is_empty"
                        class="rounded-xl border border-dashed border-[#dfcca9] bg-white p-5 text-center sm:p-6"
                    >
                        <h3 class="text-sm font-bold text-[#2d2212] sm:text-base">
                            Posisi #4 – #10 Masih Terbuka
                        </h3>
                        <p class="mx-auto mt-1 max-w-md text-xs leading-relaxed text-[#7c694e] sm:text-sm">
                            Baru ada {{ sponsorTeaser.top_entries.length }} sponsor di papan peringkat. Daftarkan brand, produk, atau websitemu sekarang untuk langsung mengamankan posisi di leaderboard!
                        </p>
                        <div class="mt-3">
                            <Link
                                :href="leaderboardPage()"
                                class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[#9a3412] px-4 py-2.5 text-xs font-bold text-white shadow-2xs transition hover:bg-[#7c2d12]"
                            >
                                Amankan Posisi Sekarang
                            </Link>
                        </div>
                    </div>

                    <!-- Empty State Invite -->
                    <div
                        v-else
                        class="rounded-xl border border-dashed border-[#dfcca9] bg-white p-5 text-center sm:p-6"
                    >
                        <h3 class="text-sm font-bold text-[#2d2212] sm:text-base">
                            Papan Sponsor periode ini masih kosong
                        </h3>
                        <p class="mx-auto mt-1 max-w-md text-xs leading-relaxed text-[#7c694e] sm:text-sm">
                            Jadilah brand, produk, atau layanan pertama yang tampil di Papan Sponsor periode ini.
                        </p>
                        <div class="mt-3">
                            <Link
                                :href="leaderboardPage()"
                                class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[#9a3412] px-4 py-2.5 text-xs font-bold text-white shadow-2xs transition hover:bg-[#7c2d12]"
                            >
                                Sponsori Sekarang
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 4. Jelajahi Per Kategori (max 6 ranked root category blocks, docs/29) -->
            <section
                v-if="categoryBlocks.length > 0"
                class="border-b border-[#e5e9e0] bg-[#f3f5ef]"
                aria-labelledby="categories-heading"
            >
                <div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-14">
                    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="mb-1 text-xs font-bold tracking-wider text-[#69796c] uppercase">
                                Indeks Kategori
                            </p>
                            <h2
                                id="categories-heading"
                                class="text-2xl font-bold tracking-tight text-[#18392d] sm:text-3xl"
                            >
                                Jelajahi per kategori
                            </h2>
                        </div>
                        <Link
                            :href="rankingIndex()"
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-[#087f5b] hover:underline"
                        >
                            Semua peringkat sentimen
                            <ArrowRight class="size-3.5" />
                        </Link>
                    </div>

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                        <CategoryBlock
                            v-for="block in categoryBlocks"
                            :key="block.id"
                            :block="block"
                        />
                    </div>
                </div>
            </section>

            <!-- 5. Tiga Metrik SuaraNetijen (Diringkas per docs/29) -->
            <section
                class="border-b border-[#e5e9e0] bg-white"
                aria-labelledby="metrics-heading"
            >
                <div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-14">
                    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="mb-1 text-xs font-bold tracking-wider text-[#69796c] uppercase">
                                Metodologi SuaraNetijen
                            </p>
                            <h2
                                id="metrics-heading"
                                class="text-2xl font-bold tracking-tight text-[#18392d] sm:text-3xl"
                            >
                                Tiga metrik objektif, tanpa kompromi
                            </h2>
                            <p class="mt-1 max-w-xl text-xs text-[#55695a] sm:text-sm">
                                Setiap angka dan kesimpulan berdiri di atas metrik terpisah yang tidak pernah digabung.
                            </p>
                        </div>
                        <Link
                            :href="methodology()"
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-[#087f5b] hover:underline"
                        >
                            Pelajari metodologi selengkapnya
                            <ArrowUpRight class="size-3.5" />
                        </Link>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-[#dfe5dc] bg-white p-5 sm:p-6">
                            <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-[#eaf4e4]">
                                <AudioLines class="size-5 text-[#3b662d]" />
                            </div>
                            <h3 class="text-base font-bold text-[#18392d]">Sentimen Netijen</h3>
                            <p class="mt-2 text-xs leading-5 text-[#5e7061] sm:text-sm">
                                Skor 0–100 hasil agregasi opini positif, netral, dan negatif dari percakapan publik di forum dan media sosial.
                            </p>
                        </div>

                        <div class="rounded-2xl border border-[#dfe5dc] bg-white p-5 sm:p-6">
                            <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-[#edf0fb]">
                                <MessageCircle class="size-5 text-[#4b588c]" />
                            </div>
                            <h3 class="text-base font-bold text-[#18392d]">Top Suara Netijen</h3>
                            <p class="mt-2 text-xs leading-5 text-[#5e7061] sm:text-sm">
                                Tema dan topik yang paling sering disukai atau dikeluhkan netizen, diukur dari frekuensi kemunculan opini.
                            </p>
                        </div>

                        <div class="rounded-2xl border border-[#dfe5dc] bg-white p-5 sm:p-6">
                            <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-[#fbf0df]">
                                <Star class="size-5 text-[#946c24]" />
                            </div>
                            <h3 class="text-base font-bold text-[#18392d]">Rating Netijen</h3>
                            <p class="mt-2 text-xs leading-5 text-[#5e7061] sm:text-sm">
                                Rating bintang 1–5 dari pengguna langsung di SuaraNetijen, dihitung independen dari crawling percakapan publik.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 6. CTA Sumber Data -->
            <section class="bg-[#f3f5ef]">
                <div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-14">
                    <div
                        class="flex flex-col justify-between gap-6 rounded-2xl border border-[#dbe7d2] bg-[#eaf3df] p-7 sm:flex-row sm:items-center sm:p-9"
                    >
                        <div class="flex items-start gap-4">
                            <ShieldCheck
                                class="mt-1 hidden size-9 shrink-0 text-[#6d8c56] sm:block"
                            />
                            <div>
                                <h2 class="text-xl font-bold tracking-tight text-[#18392d]">
                                    Ada data di balik setiap suara.
                                </h2>
                                <p
                                    class="mt-2 max-w-lg text-sm leading-6 text-[#6d7c61]"
                                >
                                    Kenali dari mana opini berasal dan bagaimana
                                    kami mengolahnya. Terbuka, supaya kamu bisa
                                    menilai sendiri.
                                </p>
                            </div>
                        </div>
                        <Link
                            :href="sources()"
                            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-full border border-[#b8cba9] bg-white/60 px-5 py-3 text-sm font-semibold transition hover:bg-white"
                        >
                            Kenali sumber data
                            <ArrowUpRight class="size-4" />
                        </Link>
                    </div>
                </div>
            </section>
        </main>
    </PublicLayout>
</template>
