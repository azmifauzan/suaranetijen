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
        meta_description: string;
        intro?: string | null;
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
    description: props.topic.meta_description,
    itemListElement: props.entities.map((item, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        name: item.name,
        url: `${siteUrl.value}/e/${item.slug}`,
    })),
}));
</script>

<template>
    <PublicLayout>
        <PublicSeo
            :title="topic.title"
            :description="topic.meta_description"
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
                <Link :href="topicsIndex.url()" class="hover:underline">Topik</Link>
                <template v-if="topic.category">
                    <span>/</span>
                    <Link
                        :href="showCategory.url(topic.category.slug)"
                        class="hover:underline"
                        >{{ topic.category.name }}</Link
                    >
                </template>
                <span>/</span>
                <span class="font-medium text-neutral-800">{{ topic.keyword }}</span>
            </nav>

            <!-- Header Section -->
            <div class="mb-10 rounded-2xl border border-[#e5e9e2] bg-white p-6 sm:p-8 shadow-xs">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-[#edf8f0] px-3 py-1 text-xs font-semibold text-[#185b3b]"
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
                    class="text-2xl font-bold tracking-tight text-[#18392d] sm:text-3xl lg:text-4xl"
                >
                    {{ topic.title }}
                </h1>

                <div
                    v-if="topic.intro"
                    class="prose prose-neutral mt-4 max-w-none text-base leading-relaxed text-[#4a554e]"
                >
                    <p class="whitespace-pre-line">{{ topic.intro }}</p>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-[#f0f3eb] pt-4 text-xs text-neutral-500">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="font-medium text-neutral-700">Tema terkait:</span>
                        <span
                            v-for="t in topic.themes"
                            :key="t.id"
                            class="rounded-md bg-[#f4f7f2] px-2 py-0.5 font-medium text-[#185b3b]"
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
                        <h2 class="text-lg font-bold text-[#18392d] sm:text-xl">
                            Daftar Entitas Terkait
                        </h2>
                        <p class="text-xs text-neutral-500">
                            Diurutkan berdasarkan frekuensi suara netizen menyebut tema terkait
                        </p>
                    </div>
                    <span class="rounded-full bg-[#edf8f0] px-3 py-1 text-xs font-semibold text-[#185b3b]">
                        {{ entities.length }} Entitas
                    </span>
                </div>

                <div v-if="entities.length === 0" class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-neutral-500">
                    Belum ada entitas aktif dengan data opini yang cukup pada topik ini.
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="(item, idx) in entities"
                        :key="item.id"
                        class="group relative flex flex-col gap-4 rounded-xl border border-[#e5e9e2] bg-white p-5 transition hover:border-[#185b3b]/40 hover:shadow-xs sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[#edf8f0] text-sm font-bold text-[#185b3b]"
                            >
                                {{ idx + 1 }}
                            </div>
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Link
                                        :href="showEntity.url(item.slug)"
                                        class="text-lg font-bold text-[#18392d] transition hover:text-[#087f5b] group-hover:underline"
                                    >
                                        {{ item.name }}
                                    </Link>
                                    <span
                                        class="rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs text-neutral-600"
                                    >
                                        {{ item.type_label }}
                                    </span>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    <span class="inline-flex items-center gap-1 font-semibold text-[#185b3b]">
                                        <MessageSquare class="size-3.5" />
                                        {{ item.mention_count }} opini menyebut tema topik
                                    </span>
                                    <span
                                        v-for="tb in item.theme_breakdown"
                                        :key="tb.theme_id"
                                        class="text-neutral-500"
                                    >
                                        • {{ tb.mention_count }}x {{ tb.display_label }}
                                    </span>
                                </div>

                                <!-- Quote from netizen context -->
                                <div
                                    v-if="item.quote"
                                    class="relative mt-2 rounded-lg bg-[#fafbf9] p-3 text-xs italic text-[#4a554e] border-l-2 border-[#bceccb]"
                                >
                                    <Quote class="mb-1 size-3 text-[#185b3b]/50" />
                                    <span>“{{ item.quote }}”</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right / Score badge -->
                        <div class="flex sm:flex-col items-center sm:items-end justify-between gap-2 shrink-0 border-t border-neutral-100 sm:border-0 pt-3 sm:pt-0">
                            <div v-if="item.score !== null" class="text-right">
                                <div class="text-[10px] font-bold text-neutral-500 uppercase tracking-wider">Sentimen Netijen</div>
                                <div class="text-xl font-extrabold text-[#087f5b]">
                                    {{ item.score }}<span class="text-xs font-normal text-neutral-400">/100</span>
                                </div>
                            </div>
                            <div v-else class="text-right">
                                <span class="rounded bg-neutral-100 px-2 py-1 text-[11px] text-neutral-500">
                                    Menunggu data
                                </span>
                            </div>

                            <Link
                                :href="showEntity.url(item.slug)"
                                class="inline-flex items-center text-xs font-semibold text-[#087f5b] hover:underline"
                            >
                                Lihat Profil →
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Topics Section -->
            <div
                v-if="relatedTopics.length > 0"
                class="rounded-2xl border border-[#e5e9e2] bg-[#f9faf7] p-6"
            >
                <div class="mb-4 flex items-center gap-2">
                    <Sparkles class="size-4 text-[#185b3b]" />
                    <h3 class="text-base font-bold text-[#18392d]">
                        Topik Terkait Lainnya
                    </h3>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3">
                    <Link
                        v-for="rel in relatedTopics"
                        :key="rel.id"
                        :href="showTopic.url(rel.slug)"
                        class="block rounded-xl border border-white bg-white p-4 transition hover:border-[#185b3b]/30 hover:shadow-xs"
                    >
                        <div class="font-bold text-[#18392d] hover:text-[#087f5b]">
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
