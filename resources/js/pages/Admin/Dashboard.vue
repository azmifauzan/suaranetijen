<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

interface Stats {
    total_entities: number;
    total_categories: number;
    total_aliases: number;
}

defineProps<{
    stats: Stats;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin Dashboard',
                href: '/admin',
            },
        ],
    },
});
</script>

<template>
    <Head title="Admin Dashboard - SuaraNetijen" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                Admin Overview
            </h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                Manage entity catalog, categories, and review crawl diagnostics.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Total Entities</div>
                <div class="mt-2 text-3xl font-extrabold text-neutral-900 dark:text-neutral-100">{{ stats.total_entities }}</div>
                <div class="mt-4">
                    <Link href="/admin/entities" class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                        Manage entities &rarr;
                    </Link>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Total Categories</div>
                <div class="mt-2 text-3xl font-extrabold text-neutral-900 dark:text-neutral-100">{{ stats.total_categories }}</div>
                <div class="mt-4">
                    <Link href="/admin/categories" class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                        Manage categories &rarr;
                    </Link>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Total Aliases</div>
                <div class="mt-2 text-3xl font-extrabold text-neutral-900 dark:text-neutral-100">{{ stats.total_aliases }}</div>
                <div class="mt-4 text-xs text-neutral-400">
                    Used for search and entity matching resolution
                </div>
            </div>
        </div>
    </div>
</template>
