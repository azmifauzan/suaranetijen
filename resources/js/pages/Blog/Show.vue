<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Calendar,
    ChevronRight,
    MessageSquare,
    Newspaper,
    Tag,
} from '@lucide/vue';
import { computed } from 'vue';
import PublicSeo from '@/components/PublicSeo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home } from '@/routes';
import { index as blogIndex } from '@/routes/blog';

interface LinkedEntity {
    id: number;
    name: string;
    slug: string;
    category_name?: string | null;
    score: number | null;
    opinion_count: number;
    is_public_score_eligible: boolean;
}

interface RelatedPost {
    id: number;
    slug: string;
    title: string;
    excerpt: string;
    cover_url: string | null;
    published_at: string;
}

const props = defineProps<{
    post: {
        id: number;
        slug: string;
        title: string;
        excerpt: string;
        body_html: string;
        cover_url: string | null;
        published_at: string;
        published_at_iso: string;
        updated_at_iso: string;
        byline: string;
    };
    linkedEntities: LinkedEntity[];
    relatedPosts: RelatedPost[];
}>();

const page = usePage();
const siteUrl = computed(() => {
    const raw = (page.props.seo as { site_url?: string } | undefined)?.site_url;
    return (raw || '').replace(/\/$/, '');
});

function toJsonLd(value: unknown): string {
    return JSON.stringify(value)
        .replace(/</g, '\\u003c')
        .replace(/>/g, '\\u003e')
        .replace(/&/g, '\\u0026');
}

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
            name: 'Blog',
            item: `${siteUrl.value}/blog`,
        },
        {
            '@type': 'ListItem',
            position: 3,
            name: props.post.title,
            item: `${siteUrl.value}/blog/${props.post.slug}`,
        },
    ],
}));

const articleJsonLd = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'BlogPosting',
    headline: props.post.title,
    description: props.post.excerpt,
    datePublished: props.post.published_at_iso,
    dateModified: props.post.updated_at_iso,
    mainEntityOfPage: `${siteUrl.value}/blog/${props.post.slug}`,
    image: props.post.cover_url
        ? [props.post.cover_url]
        : [`${siteUrl.value}/og-image.png?v=2`],
    author: {
        '@type': 'Organization',
        name: 'Tim SuaraNetijen',
        url: siteUrl.value,
    },
    publisher: {
        '@type': 'Organization',
        name: 'SuaraNetijen',
        url: siteUrl.value,
        logo: {
            '@type': 'ImageObject',
            url: `${siteUrl.value}/apple-touch-icon.png`,
        },
    },
}));
</script>

<template>
    <PublicLayout>
        <PublicSeo
            :title="post.title"
            :description="post.excerpt"
            :canonical-path="`/blog/${post.slug}`"
            :image="post.cover_url || '/og-image.png?v=2'"
            og-type="article"
        />

        <Head>
            <component :is="'script'" type="application/ld+json">
                {{ toJsonLd(breadcrumbJsonLd) }}
            </component>
            <component :is="'script'" type="application/ld+json">
                {{ toJsonLd(articleJsonLd) }}
            </component>
        </Head>

        <main class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-12">
            <!-- Breadcrumbs -->
            <nav
                class="mb-6 flex flex-wrap items-center gap-2 text-xs text-neutral-500"
            >
                <Link
                    :href="home()"
                    class="transition-colors hover:text-neutral-900"
                >
                    Beranda
                </Link>
                <ChevronRight class="size-3.5 text-neutral-400" />
                <Link
                    :href="blogIndex()"
                    class="transition-colors hover:text-neutral-900"
                >
                    Blog
                </Link>
                <ChevronRight class="size-3.5 text-neutral-400" />
                <span
                    class="max-w-[240px] truncate font-medium text-neutral-800 sm:max-w-md"
                >
                    {{ post.title }}
                </span>
            </nav>

            <article>
                <!-- Article Header -->
                <header class="mb-8 space-y-4">
                    <div
                        class="flex items-center gap-2 text-xs font-medium text-neutral-500"
                    >
                        <span class="inline-flex items-center gap-1">
                            <Calendar class="size-3.5 text-neutral-400" />
                            {{ post.published_at }}
                        </span>
                        <span>·</span>
                        <span class="font-semibold text-emerald-800">{{
                            post.byline
                        }}</span>
                    </div>

                    <h1
                        class="text-3xl font-extrabold tracking-tight text-neutral-900 sm:text-4xl sm:leading-tight"
                    >
                        {{ post.title }}
                    </h1>

                    <p
                        v-if="post.excerpt"
                        class="pt-1 text-base leading-relaxed font-medium text-neutral-600 sm:text-lg"
                    >
                        {{ post.excerpt }}
                    </p>
                </header>

                <!-- Cover Image -->
                <div
                    v-if="post.cover_url"
                    class="mb-10 aspect-video overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-100 shadow-2xs"
                >
                    <img
                        :src="post.cover_url"
                        :alt="post.title"
                        class="size-full object-cover"
                    />
                </div>

                <!-- Article Body -->
                <div class="blog-body" v-html="post.body_html"></div>
            </article>

            <!-- Linked Entities Section -->
            <section
                v-if="linkedEntities.length > 0"
                class="mt-14 border-t border-neutral-200 pt-8"
            >
                <div class="mb-5 flex items-center gap-2">
                    <Tag class="size-4 text-[#087f5b]" />
                    <h2 class="text-lg font-bold text-neutral-900">
                        Entitas Terkait
                    </h2>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Link
                        v-for="entity in linkedEntities"
                        :key="entity.id"
                        :href="`/e/${entity.slug}`"
                        class="group flex items-center justify-between rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs transition-all hover:border-emerald-300 hover:shadow-xs"
                    >
                        <div class="min-w-0 pr-3">
                            <h3
                                class="truncate text-sm font-bold text-neutral-900 transition-colors group-hover:text-[#087f5b]"
                            >
                                {{ entity.name }}
                            </h3>
                            <p class="mt-0.5 text-xs text-neutral-500">
                                {{ entity.category_name || 'Entitas' }}
                            </p>
                        </div>

                        <div class="shrink-0 text-right">
                            <div
                                v-if="
                                    entity.is_public_score_eligible &&
                                    entity.score !== null
                                "
                            >
                                <div
                                    class="text-base font-extrabold text-[#087f5b]"
                                >
                                    {{ entity.score.toFixed(1) }}
                                </div>
                                <div class="text-[10px] text-neutral-400">
                                    {{ entity.opinion_count }} opini
                                </div>
                            </div>
                            <div v-else class="text-right">
                                <span
                                    class="rounded bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-500"
                                >
                                    Belum cukup opini
                                </span>
                            </div>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- Related Articles -->
            <section
                v-if="relatedPosts.length > 0"
                class="mt-14 border-t border-neutral-200 pt-8"
            >
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Newspaper class="size-4 text-[#087f5b]" />
                        <h2 class="text-lg font-bold text-neutral-900">
                            Artikel Lainnya
                        </h2>
                    </div>
                    <Link
                        :href="blogIndex()"
                        class="inline-flex items-center gap-0.5 text-xs font-semibold text-[#087f5b] hover:underline"
                    >
                        Lihat semua
                        <ChevronRight class="size-3.5" />
                    </Link>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <Link
                        v-for="rel in relatedPosts"
                        :key="rel.id"
                        :href="`/blog/${rel.slug}`"
                        class="group flex flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs transition-all hover:border-emerald-300 hover:shadow-xs"
                    >
                        <div
                            class="mb-2.5 text-[11px] font-medium text-neutral-400"
                        >
                            {{ rel.published_at }}
                        </div>
                        <h3
                            class="line-clamp-2 text-sm leading-snug font-bold text-neutral-900 transition-colors group-hover:text-[#087f5b]"
                        >
                            {{ rel.title }}
                        </h3>
                        <p
                            v-if="rel.excerpt"
                            class="mt-2 line-clamp-2 text-xs text-neutral-500"
                        >
                            {{ rel.excerpt }}
                        </p>
                    </Link>
                </div>
            </section>
        </main>
    </PublicLayout>
</template>
