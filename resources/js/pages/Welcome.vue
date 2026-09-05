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
    Wifi,
} from '@lucide/vue';
import { computed } from 'vue';
import EntitySearch from '@/components/EntitySearch.vue';
import PublicEntityCard from '@/components/PublicEntityCard.vue';
import PublicSeo from '@/components/PublicSeo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { methodology, sources } from '@/routes';
import { show as showCategory } from '@/routes/categories';
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

const props = withDefaults(
    defineProps<{
        categories: CategoryItem[];
        searchSuggestions?: SearchSuggestion[];
        topEntities?: EntityItem[];
        recentEntities?: EntityItem[];
    }>(),
    {
        searchSuggestions: () => [],
        topEntities: () => [],
        recentEntities: () => [],
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
            title="Sentimen Publik Brand, Produk, dan Layanan Indonesia"
            description="Cari tahu opini netizen tentang brand, produk, dan layanan di Indonesia lewat sentimen publik dan rating pengguna di SuaraNetijen."
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
                        Cari tahu.<br /><span class="text-[#087f5b]"
                            >Sebelum pilih, cek kata netizen.</span
                        >
                    </h1>
                    <p
                        class="mx-auto mt-6 max-w-lg text-base leading-7 text-[#61725f] sm:text-lg"
                    >
                        Mau pilih brand, produk, atau layanan?<br />
                        Cari dulu, lihat apa kata netizen.
                    </p>
                    <div class="mx-auto mt-8 max-w-2xl"><EntitySearch /></div>
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
