<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { MessageSquare, Quote, Sparkles, Tag } from '@lucide/vue';
import { computed } from 'vue';
import PublicSeo from '@/components/PublicSeo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home } from '@/routes';
import { show as showCategory } from '@/routes/categories';
import { show as showEntity } from '@/routes/entities';
import { index as topicsIndex, show as showTopic } from '@/routes/topics';

interface ThemeBreakdown {
    theme_id: number;
    display_label: string;
    mention_count: number;
}

interface TopicEntityItem {
    id: number;
    name: string;
    slug: string;
    type_label: string;
    mention_count: number;
    score: number | null;
    quote: string | null;
    is_indexable: boolean;
    theme_breakdown: ThemeBreakdown[];
}

interface RelatedTopic {
    id: number;
    slug: string;
    keyword: string;
    title: string;
}

const props = defineProps<{
    topic: {
        id: number;
        slug: string;
        keyword: string;
        title: string;
        updated_at: string;
        category?: {
            id: number;
            name: string;
            slug: string;
        } | null;
        themes: Array<{ id: number; display_label: string }>;
    };
    entities: TopicEntityItem[];
    isIndexable: boolean;
    window: string;
    relatedTopics: RelatedTopic[];
}>();

const page = usePage();
const siteUrl = computed(() => {
    const raw = (page.props.seo as { site_url?: string } | undefined)?.site_url;
    return (raw || '').replace(/\/$/, '');
});
const pageDescription = computed(
    () =>
        `Opini netizen tentang ${props.topic.keyword.slice(0, 70)}. Daftar entitas diurutkan berdasarkan frekuensi tema.`,
);

const breadcrumbJsonLd = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: [
        {
            '@type': 'ListItem',
            position: 1,
            name: 'Beranda',
            item: `${siteUrl.value}/`,
        },
        {
            '@type': 'ListItem',
            position: 2,
            name: 'Topik',
            item: `${siteUrl.value}/topik`,
        },
        ...(props.topic.category
            ? [
                  {
                      '@type': 'ListItem',
                      position: 3,
                      name: props.topic.category.name,
                      item: `${siteUrl.value}/category/${props.topic.category.slug}`,
                  },
              ]
            : []),
        {
            '@type': 'ListItem',
            position: props.topic.category ? 4 : 3,
            name: props.topic.title,
            item: `${siteUrl.value}/topik/${props.topic.slug}`,
        },
    ],
}));

const itemListJsonLd = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'ItemList',
    name: props.topic.title,
    description: pageDescription.value,
    itemListElement: props.entities.map((item, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        name: item.name,
        ...(item.is_indexable
            ? { url: `${siteUrl.value}/e/${item.slug}` }
            : {}),
    })),
}));
</script>

<template>
    <PublicLayout>
        <PublicSeo
            :title="topic.title"
            :description="pageDescription"
            :canonical-path="`/topik/${topic.slug}`"
            :robots="isIndexable ? 'index, follow' : 'noindex, follow'"
        />

        <Head>
            <component :is="'script'" type="application/ld+json">
                {{ JSON.stringify(breadcrumbJsonLd) }}
            </component>
            <component :is="'script'" type="application/ld+json">
                {{ JSON.stringify(itemListJsonLd) }}
            </component>
        </Head>

        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            <!-- Breadcrumbs -->
            <nav
                class="mb-6 flex flex-wrap items-center gap-2 text-xs text-neutral-500"
            >
                <Link :href="home()" class="hover:underline">Beranda</Link>
                <span>/</span>
                <Link :href="topicsIndex.url()" class="hover:underline"
                    >Topik</Link
                >
                <template v-if="topic.category">
                    <span>/</span>
                    <Link
                        :href="showCategory.url(topic.category.slug)"
                        class="hover:underline"
                        >{{ topic.category.name }}</Link
                    >
                </template>
                <span>/</span>
                <span class="font-medium text-neutral-800">{{
                    topic.keyword
                }}</span>
            </nav>

            <!-- Header Section -->
            <div
                class="border-line mb-10 rounded-2xl border bg-white p-6 shadow-xs sm:p-8"
            >
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span
                        class="bg-sage text-brand-deep inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                    >
                        <Tag class="size-3.5" />
                        Topik Bahasan Netizen
                    </span>
                    <span
                        v-if="topic.category"
                        class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-medium text-neutral-600"
                    >
                        {{ topic.category.name }}
                    </span>
                </div>

                <h1
                    class="text-ink text-2xl font-bold tracking-tight sm:text-3xl lg:text-4xl"
                >
                    {{ topic.title }}
                </h1>

                <div
                    class="border-line-soft mt-6 flex flex-wrap items-center justify-between gap-4 border-t pt-4 text-xs text-neutral-500"
                >
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="font-medium text-neutral-700"
                            >Tema terkait:</span
                        >
                        <span
                            v-for="t in topic.themes"
                            :key="t.id"
                            class="bg-paper text-brand-deep rounded-md px-2 py-0.5 font-medium"
                        >
                            {{ t.display_label }}
                        </span>
                    </div>
                    <div>Diperbarui {{ topic.updated_at }}</div>
                </div>
            </div>

            <!-- Entities List -->
            <div class="mb-12">
                <div class="mb-5 flex items-center justify-between">
                    <div>
                        <h2 class="text-ink text-lg font-bold sm:text-xl">
                            Daftar Entitas Terkait
                        </h2>
                        <p class="text-xs text-neutral-500">
                            Diurutkan berdasarkan frekuensi suara netizen
                            menyebut tema terkait
                        </p>
                    </div>
                    <span
                        class="bg-sage text-brand-deep rounded-full px-3 py-1 text-xs font-semibold"
                    >
                        {{ entities.length }} Entitas
                    </span>
                </div>

                <div
                    v-if="entities.length === 0"
                    class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-neutral-500"
                >
                    Belum ada entitas aktif dengan data opini yang cukup pada
                    topik ini.
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="(item, idx) in entities"
                        :key="item.id"
                        class="group border-line hover:border-brand-deep/40 relative flex flex-col gap-4 rounded-xl border bg-white p-5 transition hover:shadow-xs sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="bg-sage text-brand-deep flex size-9 shrink-0 items-center justify-center rounded-lg text-sm font-bold"
                            >
                                {{ idx + 1 }}
                            </div>
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Link
                                        v-if="item.is_indexable"
                                        :href="showEntity.url(item.slug)"
                                        class="text-ink hover:text-brand text-lg font-bold transition group-hover:underline"
                                    >
                                        {{ item.name }}
                                    </Link>
                                    <span
                                        v-else
                                        class="text-ink text-lg font-bold"
                                        >{{ item.name }}</span
                                    >
                                    <span
                                        class="rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs text-neutral-600"
                                    >
                                        {{ item.type_label }}
                                    </span>
                                </div>

                                <div
                                    class="flex flex-wrap items-center gap-2 text-xs"
                                >
                                    <span
                                        class="text-brand-deep inline-flex items-center gap-1 font-semibold"
                                    >
                                        <MessageSquare class="size-3.5" />
                                        {{ item.mention_count }} opini menyebut
                                        tema topik
                                    </span>
                                    <span
                                        v-for="tb in item.theme_breakdown"
                                        :key="tb.theme_id"
                                        class="text-neutral-500"
                                    >
                                        {{ tb.mention_count }}x
                                        {{ tb.display_label }}
                                    </span>
                                </div>

                                <!-- Quote from netizen context -->
                                <div
                                    v-if="item.quote"
                                    class="bg-canvas text-ink-muted border-brand-tint-strong relative mt-2 rounded-lg border-l-2 p-3 text-xs italic"
                                >
                                    <Quote
                                        class="text-brand-deep/50 mb-1 size-3"
                                    />
                                    <span>“{{ item.quote }}”</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right / Score badge -->
                        <div
                            class="flex shrink-0 items-center justify-between gap-2 border-t border-neutral-100 pt-3 sm:flex-col sm:items-end sm:border-0 sm:pt-0"
                        >
                            <div v-if="item.score !== null" class="text-right">
                                <div
                                    class="text-[10px] font-bold text-neutral-500"
                                >
                                    Sentimen Netijen
                                </div>
                                <div class="text-brand text-xl font-extrabold">
                                    {{ item.score
                                    }}<span
                                        class="text-xs font-normal text-neutral-400"
                                        >/100</span
                                    >
                                </div>
                            </div>
                            <div v-else class="text-right">
                                <span
                                    class="rounded bg-neutral-100 px-2 py-1 text-[11px] text-neutral-500"
                                >
                                    Menunggu data
                                </span>
                            </div>

                            <Link
                                v-if="item.is_indexable"
                                :href="showEntity.url(item.slug)"
                                class="text-brand inline-flex items-center text-xs font-semibold hover:underline"
                            >
                                Lihat Profil
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Topics Section -->
            <div
                v-if="relatedTopics.length > 0"
                class="border-line bg-canvas rounded-2xl border p-6"
            >
                <div class="mb-4 flex items-center gap-2">
                    <Sparkles class="text-brand-deep size-4" />
                    <h3 class="text-ink text-base font-bold">
                        Topik Terkait Lainnya
                    </h3>
                </div>

                <div
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3"
                >
                    <Link
                        v-for="rel in relatedTopics"
                        :key="rel.id"
                        :href="showTopic.url(rel.slug)"
                        class="hover:border-brand-deep/30 block rounded-xl border border-white bg-white p-4 transition hover:shadow-xs"
                    >
                        <div class="text-ink hover:text-brand font-bold">
                            {{ rel.title }}
                        </div>
                        <div class="mt-1 text-xs text-neutral-500">
                            Topik: {{ rel.keyword }}
                        </div>
                    </Link>
                </div>
            </div>
        </main>
    </PublicLayout>
</template>
