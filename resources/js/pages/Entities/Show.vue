<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

interface CategoryData {
    id: number;
    name: string;
    slug: string;
}

interface ParentData {
    id: number;
    name: string;
    slug: string;
}

interface AliasData {
    id: number;
    alias: string;
    alias_type: string;
}

interface EntityData {
    id: number;
    name: string;
    slug: string;
    type: string;
    type_label: string;
    description: string | null;
    searchable: boolean;
    rankable: boolean;
    category: CategoryData;
    parent: ParentData | null;
    aliases: AliasData[];
}

defineProps<{
    entity: EntityData;
}>();
</script>

<template>
    <Head :title="`${entity.name} - Sentimen Netijen & Review`" />

    <div
        class="min-h-screen bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <!-- Header / Navigation -->
        <header
            class="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900"
        >
            <div
                class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4 sm:px-6"
            >
                <Link
                    href="/"
                    class="flex items-center gap-2 text-lg font-bold text-emerald-600 dark:text-emerald-400"
                >
                    <span>SuaraNetijen</span>
                </Link>
                <div class="flex items-center gap-4 text-sm">
                    <Link
                        href="/search"
                        class="flex items-center gap-1.5 text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            />
                        </svg>
                        <span>Cari Entitas</span>
                    </Link>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            <!-- Breadcrumbs -->
            <nav
                class="mb-4 flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400"
            >
                <Link href="/" class="hover:underline">Beranda</Link>
                <span>/</span>
                <span>{{ entity.category.name }}</span>
                <template v-if="entity.parent">
                    <span>/</span>
                    <Link
                        :href="`/e/${entity.parent.slug}`"
                        class="hover:underline"
                    >
                        {{ entity.parent.name }}
                    </Link>
                </template>
                <span>/</span>
                <span
                    class="font-medium text-neutral-800 dark:text-neutral-200"
                    >{{ entity.name }}</span
                >
            </nav>

            <!-- Entity Header Card -->
            <div
                class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8 dark:border-neutral-800 dark:bg-neutral-900"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <h1
                                class="text-2xl font-black tracking-tight text-neutral-900 sm:text-3xl dark:text-neutral-100"
                            >
                                {{ entity.name }}
                            </h1>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold tracking-wider uppercase"
                                :class="{
                                    'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300':
                                        entity.type === 'brand',
                                    'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300':
                                        entity.type === 'product',
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300':
                                        entity.type === 'service',
                                }"
                            >
                                {{ entity.type_label }}
                            </span>
                        </div>

                        <div
                            class="mt-2 flex flex-wrap items-center gap-4 text-sm text-neutral-500 dark:text-neutral-400"
                        >
                            <div>
                                Kategori:
                                <span
                                    class="font-medium text-neutral-700 dark:text-neutral-300"
                                    >{{ entity.category.name }}</span
                                >
                            </div>
                            <div v-if="entity.parent">
                                Brand / Induk:
                                <Link
                                    :href="`/e/${entity.parent.slug}`"
                                    class="font-medium text-emerald-600 hover:underline dark:text-emerald-400"
                                >
                                    {{ entity.parent.name }}
                                </Link>
                            </div>
                        </div>

                        <p
                            v-if="entity.description"
                            class="mt-4 text-sm leading-relaxed text-neutral-600 dark:text-neutral-300"
                        >
                            {{ entity.description }}
                        </p>
                    </div>
                </div>

                <!-- Aliases list -->
                <div
                    v-if="entity.aliases.length > 0"
                    class="mt-6 border-t border-neutral-100 pt-4 dark:border-neutral-800"
                >
                    <div
                        class="text-xs font-medium tracking-wider text-neutral-400 uppercase"
                    >
                        Nama Alternatif / Alias:
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <span
                            v-for="alias in entity.aliases"
                            :key="alias.id"
                            class="rounded-md bg-neutral-100 px-2.5 py-1 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300"
                        >
                            {{ alias.alias }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Sentiment & Observation Substrate Notice (Epic 8 milestone) -->
            <div
                class="mt-6 rounded-xl border border-dashed border-neutral-300 bg-neutral-100/50 p-6 text-center dark:border-neutral-700 dark:bg-neutral-900/40"
            >
                <div
                    class="text-sm font-semibold text-neutral-700 dark:text-neutral-300"
                >
                    Sentimen Netijen Belum Tersedia
                </div>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                    Crawler opini publik belum mengumpulkan minimal 30 opini
                    netijen untuk entitas ini. Skor agregat publik akan dihitung
                    otomatis saat pipeline observasi aktif.
                </p>
            </div>
        </main>
    </div>
</template>
