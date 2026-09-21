<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    AudioLines,
    CarFront,
    ChevronRight,
    CircleHelp,
    Coffee,
    Compass,
    Globe,
    Heart,
    Laptop,
    MessageCircle,
    ShieldCheck,
    ShoppingBag,
    Smartphone,
    Sparkles,
    Star,
    Trophy,
    Wifi,
    Zap,
} from '@lucide/vue';
import { computed } from 'vue';
import EntitySearch from '@/components/EntitySearch.vue';
import PublicEntityCard from '@/components/PublicEntityCard.vue';
import PublicSeo from '@/components/PublicSeo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { methodology, sources } from '@/routes';
import { show as showCategory } from '@/routes/categories';
import { show as showEntity } from '@/routes/entities';
import { index as leaderboardPage } from '@/routes/leaderboard';
import { index as searchPage } from '@/routes/search';

interface CategoryItem {
    id: number;
    name: string;
    slug: string;
    entities_count?: number;
}

interface EntityItem {
    id: number;
    name: string;
    slug: string;
    type_label: string;
    category_name: string;
    score: number | null;
    opinion_count: number;
    updated_at?: string;
}

interface SearchSuggestion {
    query: string;
    source: 'trending' | 'top_score' | 'fallback';
}

interface SponsorTeaserItem {
    id: number;
    rank: number;
    name: string;
    slug: string;
    category_name: string;
    website_url?: string | null;
    description?: string | null;
    settled_total_amount: number;
    clicks_count?: number;
    views_count?: number;
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

const props = withDefaults(
    defineProps<{
        categories: CategoryItem[];
        searchSuggestions?: SearchSuggestion[];
        topEntities?: EntityItem[];
        recentEntities?: EntityItem[];
        sponsorTeaser?: SponsorTeaser | null;
    }>(),
    {
        searchSuggestions: () => [],
        topEntities: () => [],
        recentEntities: () => [],
        sponsorTeaser: null,
    },
);

const featuredCategories = computed(() => props.categories.slice(0, 8));
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
const categoryIcons = [
    { pattern: /phone|ponsel|gadget/i, icon: Smartphone },
    { pattern: /hosting|cloud|software|teknologi|laptop/i, icon: Laptop },
    { pattern: /internet|telekom|provider/i, icon: Wifi },
    { pattern: /otomotif|mobil|motor/i, icon: CarFront },
    { pattern: /belanja|commerce|marketplace|retail/i, icon: ShoppingBag },
    { pattern: /makanan|minuman|kuliner/i, icon: Coffee },
    { pattern: /kesehatan|kecantikan/i, icon: Heart },
    { pattern: /travel|wisata|perjalanan/i, icon: Globe },
];

function categoryIcon(name: string) {
    return (
        categoryIcons.find(({ pattern }) => pattern.test(name))?.icon ?? Compass
    );
}

function suggestionTitle(source: SearchSuggestion['source']): string {
    if (source === 'trending') return 'Paling banyak dicari';
    if (source === 'top_score') return 'Sentimen Netijen tinggi';

    return 'Contoh pencarian';
}
</script>

<template>
    <PublicLayout>
        <PublicSeo
            title="Sentimen Publik Brand, Produk, Tokoh dan Layanan Indonesia"
            description="Cari tahu opini netizen tentang brand, produk, tokoh dan layanan di Indonesia lewat sentimen publik dan rating pengguna, plus papan peringkat sponsor untuk mendukung favoritmu di SuaraNetijen."
            canonical-path="/"
        />
        <main>
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
                    class="relative mx-auto max-w-6xl px-5 pt-12 pb-12 text-center sm:px-8 sm:pt-16 sm:pb-16"
                >
                    <div
                        class="mb-6 inline-flex items-center gap-2 rounded-full border border-[#cddfc6] bg-white/65 px-3.5 py-2 text-[11px] font-semibold tracking-wide text-[#4c7050] sm:text-xs"
                    >
                        <span class="size-1.5 rounded-full bg-[#238b55]"></span>
                        INDEKS SENTIMEN PUBLIK INDONESIA
                    </div>
                    <h1
                        class="text-[42px] leading-[1.08] font-bold tracking-[-2px] text-[#193e2d] sm:text-6xl sm:tracking-[-3px] lg:text-7xl"
                    >
                        Sudah tahu belum,<br /><span class="text-[#087f5b]"
                            >Apa kata Netizen?</span
                        >
                    </h1>
                    <p
                        class="mx-auto mt-6 max-w-lg text-base leading-7 text-[#61725f] sm:text-lg"
                    >
                        Mau pilih brand, produk, tokoh atau layanan?<br />
                        Cari dulu, lihat apa kata netijen.
                    </p>
                    <div class="mx-auto mt-8 max-w-4xl"><EntitySearch /></div>

                    <!-- Top 3 Leaderboard Podium below search box -->
                    <div
                        v-if="sponsorTeaser && !sponsorTeaser.is_empty && sponsorTeaser.top_entries && sponsorTeaser.top_entries.length > 0"
                        class="mx-auto mt-6 max-w-4xl text-left"
                    >
                        <div class="mb-3 flex items-center justify-between px-1">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-[#f3d39e] bg-[#fffaf0] px-2.5 py-0.5 text-[11px] font-bold tracking-wider text-[#92400e] uppercase">
                                    <Trophy class="size-3 text-[#d97706]" />
                                    Top 3 Teratas Leaderboard
                                </span>
                                <span class="text-xs text-[#6e8072]">
                                    Periode {{ sponsorTeaser.period_name || 'Minggu Ini' }}
                                </span>
                            </div>
                            <Link
                                :href="leaderboardPage()"
                                class="inline-flex items-center gap-1 text-xs font-bold text-[#92400e] hover:text-[#d97706]"
                            >
                                Buka Leaderboard <ArrowRight class="size-3" />
                            </Link>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div
                                v-for="entry in sponsorTeaser.top_entries.slice(0, 3)"
                                :key="entry.id"
                                class="relative flex flex-col justify-between rounded-2xl border p-4 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                                :class="
                                    entry.rank === 1
                                        ? 'border-[#ecd5aa] bg-gradient-to-b from-[#fffbf0] via-white to-white shadow-xs ring-1 ring-[#f4d9a6]'
                                        : entry.rank === 2
                                          ? 'border-[#d6e0d8] bg-gradient-to-b from-[#f9faf9] via-white to-white shadow-xs'
                                          : 'border-[#e6dccf] bg-gradient-to-b from-[#fdfbf9] via-white to-white shadow-xs'
                                "
                            >
                                <div>
                                    <!-- Top: Rank badge & Category -->
                                    <div class="flex items-center justify-between">
                                        <span
                                            class="inline-flex size-7 items-center justify-center rounded-lg text-xs font-black shadow-xs"
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
                                        <span class="truncate rounded-md bg-black/5 px-2 py-0.5 text-[10px] font-medium text-[#5a6b60]">
                                            {{ entry.category_name }}
                                        </span>
                                    </div>

                                    <!-- Entity Name -->
                                    <div class="mt-2.5">
                                        <Link
                                            :href="showEntity(entry.slug)"
                                            class="line-clamp-1 font-bold text-sm text-[#18392d] hover:text-[#087f5b] sm:text-base"
                                        >
                                            {{ entry.name }}
                                        </Link>
                                    </div>

                                    <!-- Sponsor Amount -->
                                    <div class="mt-2 flex items-baseline justify-between">
                                        <span class="text-[10px] font-semibold text-[#8a7251] uppercase">Sponsor:</span>
                                        <span class="text-xs font-extrabold text-[#92400e] sm:text-sm">
                                            Rp{{ entry.settled_total_amount.toLocaleString('id-ID') }}
                                        </span>
                                    </div>

                                    <!-- Views & Clicks Stats -->
                                    <div class="mt-2 flex items-center justify-between rounded-lg bg-neutral-50 px-2 py-1 text-[11px] text-[#637568]">
                                        <span title="Kunjungan detail">👁️ {{ entry.views_count || 0 }}</span>
                                        <span>•</span>
                                        <span title="Klik website">🔗 {{ entry.clicks_count || 0 }} klik</span>
                                    </div>
                                </div>

                                <!-- Action buttons: Buka Situs & Rebut Posisi -->
                                <div class="mt-3 flex flex-col gap-1.5 border-t border-neutral-100 pt-2.5">
                                    <a
                                        :href="`/go/${entry.slug}`"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="flex w-full items-center justify-center gap-1 rounded-lg bg-[#d5f5df] py-1.5 text-[11px] font-bold text-[#145736] transition hover:bg-[#bceccb]"
                                    >
                                        Buka Situs <ArrowUpRight class="size-3" />
                                    </a>
                                    <Link
                                        :href="`/leaderboard?rebut_rank=${entry.rank}&target_name=${encodeURIComponent(entry.name)}&needed_amount=${entry.settled_total_amount + 1000}#formSection`"
                                        class="flex w-full items-center justify-center gap-1 rounded-lg border border-[#f59e0b] bg-[#fffbeb] py-1.5 text-[11px] font-bold text-[#92400e] transition hover:bg-[#fef3c7]"
                                    >
                                        <Zap class="size-3 text-[#d97706]" />
                                        Rebut Posisi #{{ entry.rank }}
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="mt-4">
                        <Link
                            :href="leaderboardPage()"
                            class="inline-flex items-center gap-2 rounded-full border border-[#edd5b1] bg-white/80 px-3.5 py-1.5 text-xs text-[#8a5d1a] shadow-xs transition hover:border-[#d97706] hover:bg-white"
                        >
                            <Trophy class="size-3.5 text-[#d97706]" />
                            <span class="font-bold">Leaderboard: Belum ada sponsor periode ini</span>
                            <span class="text-[#b45309] font-medium">— Jadilah #1 sekarang!</span>
                            <ArrowRight class="size-3 text-[#b45309]" />
                        </Link>
                    </div>

                    <div
                        class="mt-5 flex flex-wrap items-center justify-center gap-2 text-xs text-[#667861]"
                    >
                        <span class="mr-1">Coba cari:</span
                        ><Link
                            v-for="suggestion in displayedSuggestions"
                            :key="suggestion.query"
                            :href="
                                searchPage({
                                    query: { q: suggestion.query },
                                })
                            "
                            :title="suggestionTitle(suggestion.source)"
                            class="flex items-center gap-1 rounded-full border border-[#d8e4d1] bg-white/65 px-3 py-2 transition hover:border-[#81ad83] hover:bg-white"
                            >{{ suggestion.query }}<ArrowUpRight class="size-3"
                        /></Link>
                    </div>
                </div>
            </section>

            <div class="border-b border-[#e6e9e1] bg-white">
                <div
                    class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-10 gap-y-3 px-5 py-5 text-xs text-[#66746b] sm:px-8 sm:text-sm"
                >
                    <span class="flex items-center gap-2"
                        ><MessageCircle class="size-4 text-[#087f5b]" /> Opini
                        dari percakapan publik</span
                    ><Link
                        :href="methodology()"
                        class="flex items-center gap-2 hover:text-[#087f5b]"
                        ><ShieldCheck class="size-4 text-[#087f5b]" />
                        Metodologi terbuka</Link
                    ><span class="flex items-center gap-2"
                        ><Star class="size-4 text-[#087f5b]" /> Sentimen &
                        rating terpisah</span
                    >
                </div>
            </div>

            <!-- Dedicated Top 10 Leaderboard Section (#4 - #13) (above categories) -->
            <section
                class="mx-auto max-w-6xl px-5 pt-10 pb-4 sm:px-8 sm:pt-14 sm:pb-6"
                aria-labelledby="top-leaderboard-heading"
            >
                <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div
                            class="mb-2 inline-flex items-center gap-1.5 rounded-full border border-[#edd7b6] bg-[#fffaf0] px-3 py-1 text-[11px] font-bold tracking-wider text-[#92400e] uppercase"
                        >
                            <Trophy class="size-3.5 text-[#d97706]" />
                            Leaderboard SuaraNetijen
                        </div>
                        <h2
                            id="top-leaderboard-heading"
                            class="text-2xl font-bold tracking-tight text-[#18392d] sm:text-3xl"
                        >
                            Leaderboard (#4 – #13)
                        </h2>
                        <p class="mt-1 text-xs text-[#61725f] sm:text-sm">
                            Brand, produk, dan layanan di peringkat #4 sampai #13 periode {{ sponsorTeaser?.period_name || 'Minggu Ini' }}.
                        </p>
                    </div>
                    <Link
                        :href="leaderboardPage()"
                        class="inline-flex items-center gap-1.5 rounded-full border border-[#dfcca9] bg-white px-4 py-2 text-xs font-bold text-[#92400e] shadow-xs transition hover:border-[#d97706] hover:bg-[#fffbf2]"
                    >
                        Lihat Semua Peringkat & Ikut Sponsor <ArrowRight class="size-3.5" />
                    </Link>
                </div>

                <!-- Active Listings (#4 - #13) -->
                <div
                    v-if="sponsorTeaser && !sponsorTeaser.is_empty && sponsorTeaser.top_entries.slice(3, 13).length > 0"
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-2"
                >
                    <div
                        v-for="entry in sponsorTeaser.top_entries.slice(3, 13)"
                        :key="entry.id"
                        class="group relative flex flex-col justify-between rounded-2xl border border-[#e2e7df] bg-white p-4 transition-all duration-200 hover:border-[#b8cfbe] hover:shadow-md sm:p-5"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-[#f1f5f0] text-xs font-black text-[#4d5e52] shadow-xs sm:size-9 sm:text-sm"
                                >
                                    #{{ entry.rank }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <Link
                                            :href="showEntity(entry.slug)"
                                            class="truncate font-bold text-sm text-[#18392d] hover:text-[#087f5b] sm:text-base"
                                        >
                                            {{ entry.name }}
                                        </Link>
                                        <span class="rounded-md bg-neutral-100 px-2 py-0.5 text-[10px] font-medium text-neutral-600">
                                            {{ entry.category_name }}
                                        </span>
                                    </div>
                                    <p v-if="entry.description" class="mt-1 line-clamp-1 text-xs text-[#6e7f73]">
                                        {{ entry.description }}
                                    </p>
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <div class="text-xs font-extrabold text-[#92400e] sm:text-sm">
                                    Rp{{ entry.settled_total_amount.toLocaleString('id-ID') }}
                                </div>
                                <span class="text-[10px] font-medium tracking-wide text-[#9a6a24] uppercase">
                                    Total Sponsor
                                </span>
                            </div>
                        </div>

                        <!-- Stats & Action buttons -->
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-neutral-100 pt-3 text-xs">
                            <div class="flex items-center gap-3 text-[11px] text-[#637568]">
                                <span title="Jumlah kunjungan halaman detail">
                                    👁️ <strong class="text-[#203a29]">{{ entry.views_count || 0 }}</strong> kunjungan
                                </span>
                                <span>•</span>
                                <span title="Jumlah klik langsung ke situs">
                                    🔗 <strong class="text-[#203a29]">{{ entry.clicks_count || 0 }}</strong> klik
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="showEntity(entry.slug)"
                                    class="rounded-lg border border-[#cfd9ce] px-2.5 py-1 text-[11px] font-semibold text-[#324b3c] hover:bg-[#f0f6f1]"
                                >
                                    Detail
                                </Link>
                                <a
                                    :href="`/go/${entry.slug}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 rounded-lg bg-[#d5f5df] px-2.5 py-1 text-[11px] font-bold text-[#145736] transition hover:bg-[#bceccb]"
                                >
                                    Buka Situs <ArrowUpRight class="size-3" />
                                </a>
                                <Link
                                    :href="`/leaderboard?rebut_rank=${entry.rank}&target_name=${encodeURIComponent(entry.name)}&needed_amount=${entry.settled_total_amount + 1000}#formSection`"
                                    class="inline-flex items-center gap-1 rounded-lg border border-[#f59e0b] bg-[#fffbeb] px-2.5 py-1 text-[11px] font-bold text-[#92400e] transition hover:bg-[#fef3c7]"
                                >
                                    <Zap class="size-3 text-[#d97706]" />
                                    Rebut Posisi
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Spot #4 - #13 Open State (when <= 3 entries exist on the board) -->
                <div
                    v-else-if="sponsorTeaser && !sponsorTeaser.is_empty"
                    class="rounded-3xl border border-dashed border-[#e6cb9d] bg-gradient-to-r from-[#fffaf2] to-[#fffdfa] p-8 text-center sm:p-10"
                >
                    <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-[#fef3c7] text-[#b45309]">
                        <Sparkles class="size-7" />
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-[#3d2c14]">
                        Posisi #4 – #13 Masih Terbuka
                    </h3>
                    <p class="mx-auto mt-1 max-w-md text-xs leading-relaxed text-[#7c694e] sm:text-sm">
                        Baru ada {{ sponsorTeaser.top_entries.length }} sponsor di papan peringkat. Daftarkan brand, produk, atau websitemu sekarang untuk langsung mengamankan posisi di leaderboard!
                    </p>
                    <div class="mt-5">
                        <Link
                            :href="leaderboardPage()"
                            class="inline-flex items-center gap-2 rounded-full bg-[#d97706] px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#b45309]"
                        >
                            Amankan Posisi Sekarang <ArrowRight class="size-4" />
                        </Link>
                    </div>
                </div>

                <!-- Empty State Invite -->
                <div
                    v-else
                    class="rounded-3xl border border-dashed border-[#e6cb9d] bg-gradient-to-r from-[#fffaf2] to-[#fffdfa] p-8 text-center sm:p-10"
                >
                    <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-[#fef3c7] text-[#b45309]">
                        <Trophy class="size-7" />
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-[#3d2c14]">
                        Leaderboard periode ini masih kosong
                    </h3>
                    <p class="mx-auto mt-1 max-w-md text-xs leading-relaxed text-[#7c694e] sm:text-sm">
                        Jadilah brand, produk, atau layanan pertama yang tampil di peringkat teratas SuaraNetijen dan dapatkan exposure langsung ke ribuan pengunjung.
                    </p>
                    <div class="mt-5">
                        <Link
                            :href="leaderboardPage()"
                            class="inline-flex items-center gap-2 rounded-full bg-[#d97706] px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#b45309]"
                        >
                            Sponsori Sekarang <ArrowRight class="size-4" />
                        </Link>
                    </div>
                </div>
            </section>

            <section
                class="mx-auto max-w-6xl px-5 py-12 sm:px-8 sm:py-16"
                aria-labelledby="categories-heading"
            >
                <div class="mb-7 flex items-end justify-between gap-5">
                    <div>
                        <p
                            class="mb-2 text-xs font-semibold tracking-[2px] text-[#71826d] uppercase"
                        >
                            Mulai dari yang kamu cari
                        </p>
                        <h2
                            id="categories-heading"
                            class="text-2xl font-bold tracking-tight sm:text-3xl"
                        >
                            Banyak pilihan. Biar lebih yakin.
                        </h2>
                    </div>
                    <Link
                        :href="searchPage()"
                        class="hidden items-center gap-2 text-sm font-semibold text-[#087f5b] hover:underline sm:flex"
                        >Semua kategori <ArrowRight class="size-4"
                    /></Link>
                </div>
                <div
                    v-if="featuredCategories.length"
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <Link
                        v-for="(category, index) in featuredCategories"
                        :key="category.id"
                        :href="showCategory(category.slug)"
                        class="group flex items-center gap-3 rounded-xl border border-[#e0e5dc] bg-white p-4 transition hover:border-[#9cbea0] hover:shadow-sm"
                        ><span
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl"
                            :class="
                                [
                                    'bg-[#eff4e8] text-[#698347]',
                                    'bg-[#edf3fc] text-[#6885b1]',
                                    'bg-[#fcf0e6] text-[#b98c60]',
                                    'bg-[#f4eef9] text-[#a18ab5]',
                                ][index % 4]
                            "
                            ><component
                                :is="categoryIcon(category.name)"
                                class="size-5" /></span
                        ><span class="min-w-0 flex-1"
                            ><span class="block text-sm font-semibold">{{
                                category.name
                            }}</span
                            ><span
                                v-if="category.entities_count !== undefined"
                                class="mt-1 block text-xs text-[#7d887d]"
                                >{{ category.entities_count }} brand, produk &
                                layanan</span
                            ></span
                        ><ChevronRight
                            class="size-4 shrink-0 text-[#99a692] transition group-hover:translate-x-0.5"
                    /></Link>
                </div>
                <p
                    v-else
                    class="rounded-xl border border-dashed border-[#d1ddcd] p-6 text-sm text-[#66746b]"
                >
                    Kategori sedang disiapkan. Gunakan pencarian untuk menemukan
                    yang kamu cari.
                </p>
                <Link
                    :href="searchPage()"
                    class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-[#087f5b] sm:hidden"
                    >Semua kategori <ArrowRight class="size-4"
                /></Link>
            </section>

            <section
                class="border-y border-[#e5e9e0] bg-[#f3f5ef]"
                aria-labelledby="sentiment-heading"
            >
                <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8 sm:py-14">
                    <div
                        class="mb-7 flex flex-wrap items-end justify-between gap-4"
                    >
                        <div>
                            <p
                                class="mb-2 flex items-center gap-2 text-xs font-semibold tracking-[2px] text-[#71826d] uppercase"
                            >
                                <Sparkles class="size-4" /> Dari suara yang
                                terkumpul
                            </p>
                            <h2
                                id="sentiment-heading"
                                class="text-2xl font-bold tracking-tight sm:text-3xl"
                            >
                                Sentimen positif, jadi bahan pertimbangan.
                            </h2>
                            <p class="mt-3 text-sm leading-6 text-[#73806e]">
                                Gambaran opini publik dalam 12 bulan terakhir.
                                Keputusan tetap di tanganmu.
                            </p>
                        </div>
                        <Link
                            :href="methodology()"
                            class="flex items-center gap-1.5 text-xs font-medium text-[#627a5f] hover:underline"
                            ><CircleHelp class="size-4" /> Bagaimana skor
                            dihitung?</Link
                        >
                    </div>
                    <div
                        v-if="topEntities.length"
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <PublicEntityCard
                            v-for="entity in topEntities"
                            :key="entity.id"
                            :entity="entity"
                        />
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center rounded-2xl border border-dashed border-[#cbdac5] bg-white/65 px-6 py-10 text-center"
                    >
                        <span
                            class="flex size-14 items-center justify-center rounded-full bg-[#e8f2e2]"
                            ><MessageCircle class="size-6 text-[#62815b]"
                        /></span>
                        <h3 class="mt-4 text-lg font-semibold">
                            Setiap suara butuh cukup cerita.
                        </h3>
                        <p
                            class="mt-2 max-w-md text-sm leading-6 text-[#73806e]"
                        >
                            Belum ada entitas dengan data yang cukup untuk
                            ditampilkan di sini. Kamu tetap bisa menjelajahi
                            brand, produk, dan layanan.
                        </p>
                        <Link
                            :href="searchPage()"
                            class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-[#087f5b]"
                            >Mulai jelajahi <ArrowRight class="size-4"
                        /></Link>
                    </div>
                </div>
            </section>

            <section
                class="mx-auto max-w-6xl px-5 py-14 sm:px-8 sm:py-18"
                aria-labelledby="how-heading"
            >
                <div
                    class="grid items-start gap-10 lg:grid-cols-[0.85fr_1.5fr] lg:gap-16"
                >
                    <div>
                        <p
                            class="mb-3 text-xs font-semibold tracking-[2px] text-[#71826d] uppercase"
                        >
                            Kenalan dengan SuaraNetijen
                        </p>
                        <h2
                            id="how-heading"
                            class="text-3xl leading-tight font-bold tracking-tight"
                        >
                            Ramai di internet.<br />Lebih jelas di sini.
                        </h2>
                        <p class="mt-4 text-sm leading-7 text-[#748070]">
                            Kami merangkum percakapan publik menjadi gambaran
                            yang mudah dipahami. Biar kamu punya lebih banyak
                            perspektif sebelum memilih.
                        </p>
                        <Link
                            :href="methodology()"
                            class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-[#087f5b]"
                            >Lihat cara kerjanya <ArrowUpRight class="size-4"
                        /></Link>
                    </div>
                    <div class="grid gap-6 sm:grid-cols-3">
                        <div>
                            <div
                                class="mb-5 flex size-12 items-center justify-center rounded-2xl bg-[#eaf4e4]"
                            >
                                <AudioLines class="size-6 text-[#628652]" />
                            </div>
                            <h3 class="font-bold">Sentimen Netijen</h3>
                            <p class="mt-2 text-sm leading-6 text-[#748070]">
                                Skor 0–100 dari opini positif, netral, dan
                                negatif. Lengkap dengan jumlah opini yang
                                dianalisis.
                            </p>
                        </div>
                        <div>
                            <div
                                class="mb-5 flex size-12 items-center justify-center rounded-2xl bg-[#edf0fb]"
                            >
                                <MessageCircle class="size-6 text-[#8089b2]" />
                            </div>
                            <h3 class="font-bold">Top Suara Netijen</h3>
                            <p class="mt-2 text-sm leading-6 text-[#748070]">
                                Apa yang sering disukai dan dikeluhkan? Lihat
                                tema yang paling banyak dibicarakan.
                            </p>
                        </div>
                        <div>
                            <div
                                class="mb-5 flex size-12 items-center justify-center rounded-2xl bg-[#fbf0df]"
                            >
                                <Star class="size-6 text-[#b3935a]" />
                            </div>
                            <h3 class="font-bold">Rating Netijen</h3>
                            <p class="mt-2 text-sm leading-6 text-[#748070]">
                                Rating 1–5 dari pengguna SuaraNetijen. Dihitung
                                terpisah dari sentimen percakapan publik.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="recentEntities.length"
                class="mx-auto max-w-6xl px-5 pb-14 sm:px-8"
                aria-labelledby="recent-heading"
            >
                <div
                    class="mb-6 flex flex-wrap items-end justify-between gap-3"
                >
                    <div>
                        <h2
                            id="recent-heading"
                            class="text-2xl font-bold tracking-tight"
                        >
                            Baru diperbarui
                        </h2>
                        <p class="mt-2 text-sm text-[#748070]">
                            Intip perkembangan percakapan terbaru.
                        </p>
                    </div>
                    <Link
                        :href="searchPage()"
                        class="flex items-center gap-2 text-sm font-semibold text-[#087f5b]"
                        >Jelajahi lainnya <ArrowRight class="size-4"
                    /></Link>
                </div>
                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <PublicEntityCard
                        v-for="entity in recentEntities"
                        :key="entity.id"
                        :entity="entity"
                    />
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-5 pb-14 sm:px-8">
                <div
                    class="flex flex-col justify-between gap-6 rounded-2xl border border-[#dbe7d2] bg-[#eaf3df] p-7 sm:flex-row sm:items-center sm:p-9"
                >
                    <div class="flex items-start gap-4">
                        <ShieldCheck
                            class="mt-1 hidden size-9 shrink-0 text-[#6d8c56] sm:block"
                        />
                        <div>
                            <h2 class="text-xl font-bold tracking-tight">
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
                        >Kenali sumber data <ArrowUpRight class="size-4"
                    /></Link>
                </div>
            </section>
        </main>
    </PublicLayout>
</template>
