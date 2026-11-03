<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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

function scoreTone(score: number): string {
    if (score >= 70) return 'bg-score-high';
    if (score >= 50) return 'bg-score-mid';

    return 'bg-score-low';
}
</script>

<template>
    <article class="border-line flex flex-col rounded-2xl border bg-white">
        <header
            class="flex items-baseline justify-between gap-3 px-5 pt-5 sm:px-6"
        >
            <h3 class="text-ink text-lg font-bold tracking-tight">
                {{ block.name }}
            </h3>
            <span class="text-ink-muted text-xs">skor 0 sampai 100</span>
        </header>

        <ol
            v-if="block.top_entities.length > 0"
            class="divide-line-soft mt-3 divide-y px-5 sm:px-6"
        >
            <li
                v-for="(entity, index) in block.top_entities"
                :key="entity.id"
                class="relative flex items-center gap-3 py-3"
            >
                <span
                    class="text-ink-muted w-4 shrink-0 text-sm font-bold tabular-nums"
                >
                    {{ index + 1 }}
                </span>
                <div class="min-w-0 flex-1">
                    <Link
                        :href="showEntity(entity.slug)"
                        class="text-ink hover:text-brand block truncate text-sm font-bold after:absolute after:inset-0 hover:underline"
                    >
                        {{ entity.name }}
                    </Link>
                    <p class="text-ink-muted flex flex-wrap gap-x-3 text-xs">
                        <span>{{ entity.category_name }}</span>
                        <span
                            >{{
                                entity.opinion_count.toLocaleString('id-ID')
                            }}
                            opini</span
                        >
                    </p>
                </div>
                <div class="w-20 shrink-0 text-right">
                    <span class="text-ink text-base font-bold tabular-nums">
                        {{
                            entity.score.toLocaleString('id-ID', {
                                maximumFractionDigits: 1,
                            })
                        }}
                    </span>
                    <div
                        class="bg-line-soft mt-1 h-1.5 overflow-hidden rounded-full"
                        aria-hidden="true"
                    >
                        <div
                            class="h-full rounded-full"
                            :class="scoreTone(entity.score)"
                            :style="{
                                width: `${Math.min(100, Math.max(0, entity.score))}%`,
                            }"
                        ></div>
                    </div>
                </div>
            </li>
        </ol>

        <div
            v-if="block.topics.length > 0 || block.child_categories.length > 0"
            class="border-line-soft mt-auto space-y-2 border-t px-5 py-4 text-xs sm:px-6"
        >
            <p
                v-if="block.child_categories.length > 0"
                class="text-ink-muted leading-6"
            >
                <span class="mr-1">Subkategori:</span>
                <template
                    v-for="(child, index) in block.child_categories"
                    :key="child.id"
                >
                    <Link
                        :href="showCategory(child.slug)"
                        class="text-brand-deep hover:text-brand font-semibold underline-offset-2 hover:underline"
                    >
                        {{ child.name }}
                    </Link>
                    <span v-if="index < block.child_categories.length - 1"
                        >,
                    </span>
                </template>
            </p>
            <p v-if="block.topics.length > 0" class="text-ink-muted leading-6">
                <span class="mr-1">Topik:</span>
                <template
                    v-for="(topic, index) in block.topics"
                    :key="topic.slug"
                >
                    <Link
                        :href="showTopic(topic.slug)"
                        class="text-brand-deep hover:text-brand font-semibold underline-offset-2 hover:underline"
                    >
                        {{ topic.title }}
                    </Link>
                    <span v-if="index < block.topics.length - 1">, </span>
                </template>
            </p>
        </div>

        <Link
            :href="showRanking(block.slug)"
            class="border-line-soft text-brand hover:bg-sage flex min-h-11 items-center rounded-b-2xl border-t px-5 py-3 text-sm font-bold transition sm:px-6"
            :class="{
                'mt-auto':
                    block.topics.length === 0 &&
                    block.child_categories.length === 0,
            }"
        >
            Peringkat lengkap {{ block.name }}
        </Link>
    </article>
</template>
