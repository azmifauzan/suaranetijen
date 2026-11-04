<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Calendar, ChevronRight, FileText, Newspaper } from '@lucide/vue';
import PublicSeo from '@/components/PublicSeo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

interface BlogPostSummary {
    id: number;
    slug: string;
    title: string;
    excerpt: string;
    cover_url: string | null;
    published_at: string;
    byline: string;
}

const props = defineProps<{
    posts: {
        data: BlogPostSummary[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
    };
}>();
</script>

<template>
    <PublicLayout>
        <PublicSeo
            title="Blog & Wawasan Sentimen"
            description="Artikel analisis sentimen publik, metodologi riset, dan rangkuman suara netizen Indonesia terkini di SuaraNetijen."
            canonical-path="/blog"
        />

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
            <!-- Header Section -->
            <div class="mb-10 text-center sm:mb-14">
                <span
                    class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800"
                >
                    <Newspaper class="size-3.5" />
                    Editorial SuaraNetijen
                </span>
                <h1
                    class="text-3xl font-extrabold tracking-tight text-neutral-900 sm:text-4xl"
                >
                    Blog & Analisis Sentimen
                </h1>
                <p
                    class="mx-auto mt-3 max-w-2xl text-sm text-neutral-600 sm:text-base"
                >
                    Kupas tuntas tren percakapan, metodologi pengindeksan, dan
                    rangkuman opini publik Indonesia secara transparan dan
                    berbasis data.
                </p>
            </div>

            <!-- Empty State -->
            <div
                v-if="posts.data.length === 0"
                class="mx-auto max-w-md rounded-2xl border border-neutral-200 bg-white p-8 text-center shadow-xs"
            >
                <div
                    class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-neutral-100 text-neutral-400"
                >
                    <FileText class="size-6" />
                </div>
                <h2 class="text-base font-bold text-neutral-800">
                    Belum Ada Artikel
                </h2>
                <p class="mt-1.5 text-xs leading-relaxed text-neutral-500">
                    Nantikan analisis sentimen berkala dan penjelasan metodologi
                    dari tim SuaraNetijen segera di sini.
                </p>
            </div>

            <!-- Articles Grid -->
            <div
                v-else
                class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
            >
                <article
                    v-for="post in posts.data"
                    :key="post.id"
                    class="group relative flex flex-col overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-2xs transition-all duration-200 hover:border-emerald-300 hover:shadow-md"
                >
                    <!-- Whole card is clickable; the title link stays for semantics -->
                    <Link
                        :href="`/blog/${post.slug}`"
                        class="absolute inset-0 z-10"
                        tabindex="-1"
                        aria-hidden="true"
                    />
                    <!-- Cover -->
                    <Link
                        :href="`/blog/${post.slug}`"
                        class="relative block aspect-video w-full overflow-hidden bg-neutral-100"
                    >
                        <img
                            v-if="post.cover_url"
                            :src="post.cover_url"
                            :alt="post.title"
                            class="size-full object-cover transition-transform duration-300 group-hover:scale-102"
                            loading="lazy"
                        />
                        <div
                            v-else
                            class="flex size-full items-center justify-center bg-gradient-to-br from-emerald-50 to-neutral-100 p-6 text-emerald-800/40"
                        >
                            <Newspaper class="size-12" />
                        </div>
                    </Link>

                    <!-- Content -->
                    <div class="flex flex-1 flex-col justify-between p-5">
                        <div class="space-y-2.5">
                            <div
                                class="flex items-center gap-2 text-[11px] font-medium text-neutral-500"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <Calendar class="size-3 text-neutral-400" />
                                    {{ post.published_at }}
                                </span>
                                <span>·</span>
                                <span class="font-semibold text-emerald-700">{{
                                    post.byline
                                }}</span>
                            </div>

                            <h2
                                class="line-clamp-2 text-lg leading-snug font-bold text-neutral-900 transition-colors group-hover:text-[#087f5b]"
                            >
                                <Link :href="`/blog/${post.slug}`">
                                    {{ post.title }}
                                </Link>
                            </h2>

                            <p
                                v-if="post.excerpt"
                                class="line-clamp-3 text-xs leading-relaxed text-neutral-600"
                            >
                                {{ post.excerpt }}
                            </p>
                        </div>

                        <div
                            class="pointer-events-none mt-4 flex items-center justify-between border-t border-neutral-100 pt-3 text-xs font-semibold text-[#087f5b]"
                        >
                            <span>Baca selengkapnya</span>
                            <ChevronRight
                                class="size-4 transition-transform group-hover:translate-x-0.5"
                            />
                        </div>
                    </div>
                </article>
            </div>

            <!-- Pagination -->
            <div
                v-if="posts.links && posts.links.length > 3"
                class="mt-12 flex justify-center"
            >
                <nav
                    aria-label="Navigasi halaman blog"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-2xs"
                >
                    <template v-for="(link, i) in posts.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="[
                                'rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                                link.active
                                    ? 'bg-[#087f5b] text-white'
                                    : 'text-neutral-700 hover:bg-neutral-100',
                            ]"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="px-2.5 py-1.5 text-xs text-neutral-400"
                            v-html="link.label"
                        />
                    </template>
                </nav>
            </div>
        </main>
    </PublicLayout>
</template>
