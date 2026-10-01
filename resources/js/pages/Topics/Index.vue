<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Compass, Hash } from '@lucide/vue';
import PublicSeo from '@/components/PublicSeo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home } from '@/routes';
import { show as showTopic } from '@/routes/topics';

interface TopicItem {
    id: number;
    slug: string;
    keyword: string;
    title: string;
    category_name?: string | null;
}

defineProps<{
    groupedTopics: Record<string, TopicItem[]>;
    totalTopics: number;
    isIndexable: boolean;
}>();
</script>

<template>
    <PublicLayout>
        <PublicSeo
            title="Kumpulan Topik Populer Netizen"
            description="Jelajahi berbagai topik pilihan netizen tentang brand, produk, dan layanan di Indonesia di SuaraNetijen."
            canonical-path="/topik"
            :robots="isIndexable ? 'index, follow' : 'noindex, follow'"
        />

        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            <!-- Breadcrumbs -->
            <nav class="mb-6 flex items-center gap-2 text-xs text-neutral-500">
                <Link :href="home()" class="hover:underline">Beranda</Link>
                <span>/</span>
                <span class="font-medium text-neutral-800">Topik</span>
            </nav>

            <!-- Hero Section -->
            <div class="mb-10 rounded-2xl border border-[#e5e9e2] bg-white p-6 sm:p-8 shadow-xs">
                <div class="mb-3 flex items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-[#edf8f0] px-3 py-1 text-xs font-semibold text-[#185b3b]"
                    >
                        <Compass class="size-3.5" />
                        Eksplorasi Topik
                    </span>
                    <span class="text-xs text-neutral-500">
                        {{ totalTopics }} Topik Terkurasi
                    </span>
                </div>

                <h1
                    class="text-2xl font-bold tracking-tight text-[#18392d] sm:text-3xl lg:text-4xl"
                >
                    Topik Pembahasan Populer Netizen
                </h1>

                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-[#4a554e] sm:text-base">
                    Temukan rangkuman pilihan dan peringkat entitas berdasarkan suara netizen di berbagai kategori dan tema kebutuhan harian.
                </p>
            </div>

            <!-- Empty state -->
            <div
                v-if="Object.keys(groupedTopics).length === 0"
                class="rounded-xl border border-dashed border-neutral-300 p-12 text-center text-neutral-500"
            >
                Belum ada topik terpublikasi saat ini.
            </div>

            <!-- Grouped Topics -->
            <div v-else class="space-y-10">
                <section
                    v-for="(topics, categoryGroup) in groupedTopics"
                    :key="categoryGroup"
                    class="space-y-4"
                >
                    <div class="flex items-center gap-2 border-b border-[#e5e9e2] pb-2">
                        <Hash class="size-4 text-[#185b3b]" />
                        <h2 class="text-lg font-bold text-[#18392d]">
                            {{ categoryGroup }}
                        </h2>
                        <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-500">
                            {{ topics.length }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                        <Link
                            v-for="topic in topics"
                            :key="topic.id"
                            :href="showTopic.url(topic.slug)"
                            class="group block rounded-xl border border-[#e5e9e2] bg-white p-5 transition hover:border-[#185b3b]/40 hover:shadow-xs"
                        >
                            <span class="text-xs font-semibold text-[#185b3b]">
                                {{ topic.category_name }}
                            </span>
                            <h3 class="mt-1 font-bold text-[#18392d] group-hover:text-[#087f5b] group-hover:underline">
                                {{ topic.title }}
                            </h3>
                            <div class="mt-3 flex items-center text-xs font-semibold text-[#087f5b]">
                                Telusuri topik →
                            </div>
                        </Link>
                    </div>
                </section>
            </div>
        </main>
    </PublicLayout>
</template>
