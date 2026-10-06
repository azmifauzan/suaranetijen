<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, MessageCircle, Sparkles, Tag } from '@lucide/vue';
import { show as showCategory } from '@/routes/categories';
import { show as showEntity } from '@/routes/entities';
import { show as showRanking } from '@/routes/rankings';
import { show as showTopic } from '@/routes/topics';

export interface CategoryEntity {
    id: number;
    name: string;
    slug: string;
    score: number;
    opinion_count: number;
    category_name: string;
    category_slug: string;
}

export interface ChildCategory {
    id: number;
    name: string;
    slug: string;
}

export interface CategoryTopic {
    id: number;
    slug: string;
    title: string;
    keyword: string;
}

export interface CategoryBlockItem {
    id: number;
    name: string;
    slug: string;
    top_entities: CategoryEntity[];
    child_categories: ChildCategory[];
    topics: CategoryTopic[];
    category_url: string;
    top_ranking_url: string;
}

defineProps<{
    block: CategoryBlockItem;
}>();
</script>

<template>
    <article
        class="flex flex-col justify-between rounded-2xl border border-[#dfe5dc] bg-white p-5 transition hover:border-[#9cbfa3] hover:shadow-xs sm:p-6"
    >
        <div>
            <!-- Header -->
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span
                        class="inline-flex items-center gap-1 rounded-md bg-[#edf4ec] px-2 py-0.5 text-[11px] font-semibold text-[#3b5e40]"
                    >
                        {{ block.name }}
                    </span>
                    <h2 class="mt-2 text-lg font-bold tracking-tight text-[#18392d] sm:text-xl">
                        {{ block.name }} menurut netizen
                    </h2>
                </div>
                <Link
                    :href="showRanking(block.slug)"
                    class="shrink-0 rounded-lg p-1.5 text-[#5e7364] transition hover:bg-[#edf4ec] hover:text-[#087f5b]"
                    :title="`Peringkat sentimen ${block.name}`"
                >
                    <ArrowRight class="size-4" />
                </Link>
            </div>

            <!-- Top 3 ranked entities -->
            <div
                v-if="block.top_entities.length > 0"
                class="mt-4 border-t border-[#edf1ec] pt-3"
            >
                <p class="mb-2 flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider text-[#687a6c]">
                    <Sparkles class="size-3 text-[#087f5b]" />
                    Sentimen Netizen Tertinggi
                </p>

                <div class="space-y-2">
                    <div
                        v-for="entity in block.top_entities"
                        :key="entity.id"
                        class="flex items-center justify-between gap-3 rounded-xl border border-[#edf1ec] bg-[#f9fbf8] px-3 py-2 transition hover:border-[#c5d8c8] hover:bg-white"
                    >
                        <div class="min-w-0 flex-1">
                            <Link
                                :href="showEntity(entity.slug)"
                                class="truncate text-sm font-bold text-[#18392d] hover:text-[#087f5b] hover:underline"
                            >
                                {{ entity.name }}
                            </Link>
                            <div class="flex items-center gap-2 text-[11px] text-[#5d6e61]">
                                <span>{{ entity.category_name }}</span>
                                <span>•</span>
                                <span class="flex items-center gap-1">
                                    <MessageCircle class="size-3" />
                                    {{ entity.opinion_count.toLocaleString('id-ID') }} opini
                                </span>
                            </div>
                        </div>

                        <div class="shrink-0 text-right">
                            <span
                                class="inline-flex items-center rounded-lg px-2 py-1 text-xs font-bold"
                                :class="
                                    entity.score >= 70
                                        ? 'bg-[#dcf5e5] text-[#17623d]'
                                        : entity.score >= 50
                                          ? 'bg-[#fff1d5] text-[#845713]'
                                          : 'bg-[#fce5e1] text-[#a74232]'
                                "
                            >
                                {{
                                    entity.score.toLocaleString('id-ID', {
                                        maximumFractionDigits: 1,
                                    })
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Published Topics in this Category -->
            <div
                v-if="block.topics.length > 0"
                class="mt-3.5 border-t border-[#edf1ec] pt-3"
            >
                <div class="flex flex-wrap items-center gap-1.5">
                    <Tag class="size-3 text-[#798b7e]" />
                    <Link
                        v-for="topic in block.topics"
                        :key="topic.slug"
                        :href="showTopic(topic.slug)"
                        class="rounded-md border border-[#e2e8e0] bg-[#f5f7f3] px-2 py-0.5 text-xs text-[#415344] transition hover:border-[#087f5b] hover:bg-white hover:text-[#087f5b]"
                    >
                        {{ topic.title }}
                    </Link>
                </div>
            </div>

            <!-- Child Categories Links -->
            <div
                v-if="block.child_categories.length > 0"
                class="mt-3.5 border-t border-[#edf1ec] pt-3"
            >
                <p class="mb-1.5 text-[11px] font-medium text-neutral-500">
                    Subkategori:
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <Link
                        v-for="child in block.child_categories"
                        :key="child.id"
                        :href="showCategory(child.slug)"
                        class="rounded-full border border-[#dce3da] bg-white px-2.5 py-1 text-xs text-[#526356] transition hover:border-[#8ab591] hover:text-[#087f5b]"
                    >
                        {{ child.name }}
                    </Link>
                </div>
            </div>
        </div>

        <!-- Footer link -->
        <div class="mt-5 border-t border-[#edf1ec] pt-3">
            <Link
                :href="showRanking(block.slug)"
                class="inline-flex items-center gap-1.5 text-xs font-bold text-[#087f5b] hover:underline"
            >
                Lihat peringkat {{ block.name }}
                <ArrowRight class="size-3.5" />
            </Link>
        </div>
    </article>
</template>
