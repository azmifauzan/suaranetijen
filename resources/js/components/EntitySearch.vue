<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onClickOutside, useDebounceFn } from '@vueuse/core';
import { ArrowUpRight, LoaderCircle, Search } from '@lucide/vue';
import { onBeforeUnmount, ref, useId, watch } from 'vue';
import { search as searchApi } from '@/routes/api';
import { show as showEntity } from '@/routes/entities';
import { index as searchPage } from '@/routes/search';

interface Suggestion {
    id: number;
    name: string;
    slug: string;
    type_label: string;
    category: { name: string };
}

const query = ref('');
const suggestions = ref<Suggestion[]>([]);
const loading = ref(false);
const open = ref(false);
const failed = ref(false);
const activeIndex = ref(-1);
const container = ref<HTMLElement | null>(null);
const inputId = useId();
const listId = `${inputId}-results`;
let request: AbortController | undefined;
let revision = 0;

function dismiss(): void {
    open.value = false;
    activeIndex.value = -1;
    revision++;
    request?.abort();
    loading.value = false;
}

onClickOutside(container, dismiss);
onBeforeUnmount(dismiss);

const fetchSuggestions = useDebounceFn(
    async (value: string, version: number) => {
        if (version !== revision) return;
        request = new AbortController();
        try {
            const response = await fetch(
                searchApi.url({ query: { q: value, limit: 6 } }),
                { signal: request.signal },
            );
            if (!response.ok) throw new Error('Search failed');
            const data = await response.json();
            if (version === revision) suggestions.value = data.data ?? [];
        } catch {
            if (version === revision) failed.value = true;
        } finally {
            if (version === revision) loading.value = false;
        }
    },
    200,
);

function updateSuggestions(): void {
    revision++;
    request?.abort();
    suggestions.value = [];
    activeIndex.value = -1;
    failed.value = false;
    const value = query.value.trim();
    open.value = value.length >= 2;
    loading.value = open.value;
    if (open.value) void fetchSuggestions(value, revision);
}

watch(query, updateSuggestions);

function select(item: Suggestion): void {
    dismiss();
    router.visit(showEntity(item.slug));
}

function submit(): void {
    const selected = suggestions.value[activeIndex.value];
    if (open.value && selected) {
        select(selected);
        return;
    }
    dismiss();
    router.get(searchPage.url(), { q: query.value.trim() || undefined });
}

function moveSelection(direction: number): void {
    if (!suggestions.value.length) return;
    open.value = true;
    activeIndex.value =
        (activeIndex.value + direction + suggestions.value.length) %
        suggestions.value.length;
}
</script>

<template>
    <div
        ref="container"
        class="relative w-full text-left"
        @focusout="
            (event: FocusEvent) => {
                if (!container?.contains(event.relatedTarget as Node))
                    dismiss();
            }
        "
    >
        <form
            role="search"
            class="flex min-h-18 items-center gap-2 rounded-2xl border border-[#c7d9ca] bg-white p-2 shadow-[0_8px_30px_-12px_#54886b50] focus-within:border-[#087f5b] focus-within:ring-4 focus-within:ring-[#087f5b]/10 sm:p-2.5"
            @submit.prevent="submit"
        >
            <Search
                class="ml-2 size-5 shrink-0 text-[#68796d] sm:ml-3 sm:size-6"
                aria-hidden="true"
            />
            <label :for="inputId" class="sr-only"
                >Cari brand, produk, atau layanan</label
            >
            <input
                :id="inputId"
                v-model="query"
                type="search"
                role="combobox"
                autocomplete="off"
                aria-autocomplete="list"
                :aria-expanded="open"
                :aria-controls="listId"
                :aria-activedescendant="
                    activeIndex >= 0 ? `${listId}-${activeIndex}` : undefined
                "
                placeholder="Cari brand, produk, atau layanan..."
                class="h-12 w-full min-w-0 bg-transparent px-1 text-sm text-[#18392d] outline-none placeholder:text-[#7e8b81] sm:px-2 sm:text-base"
                @focus="updateSuggestions"
                @keydown.down.prevent="moveSelection(1)"
                @keydown.up.prevent="moveSelection(-1)"
                @keydown.esc.prevent="dismiss"
            />
            <button
                type="submit"
                class="flex h-12 shrink-0 items-center gap-2 rounded-xl bg-[#087f5b] px-5 text-sm font-bold text-white transition hover:bg-[#066446] sm:px-7"
            >
                <span>Cari</span><Search class="hidden size-4 sm:block" />
            </button>
        </form>
        <div
            v-if="open"
            class="absolute inset-x-0 top-full z-30 mt-2 rounded-2xl border border-[#dce5dc] bg-white p-2 shadow-xl"
        >
            <p
                v-if="loading"
                role="status"
                class="flex items-center gap-2 p-4 text-sm text-[#63756a]"
            >
                <LoaderCircle class="size-4 animate-spin" /> Mencari...
            </p>
            <p
                v-else-if="failed"
                role="status"
                class="p-4 text-sm text-[#63756a]"
            >
                Saran belum dapat dimuat. Tekan Cari untuk melihat hasil.
            </p>
            <p
                v-else-if="!suggestions.length"
                role="status"
                class="p-4 text-sm text-[#63756a]"
            >
                Belum ada yang cocok. Coba nama atau kata kunci lain.
            </p>
            <ul :id="listId" role="listbox" aria-label="Saran pencarian">
                <li
                    v-for="(item, index) in suggestions"
                    :id="`${listId}-${index}`"
                    :key="item.id"
                    role="option"
                    :aria-selected="activeIndex === index"
                    class="flex cursor-pointer items-center justify-between gap-3 rounded-xl px-4 py-3 hover:bg-[#edf8f0]"
                    :class="{ 'bg-[#edf8f0]': activeIndex === index }"
                    @mousedown.prevent
                    @click="select(item)"
                >
                    <span
                        ><span class="block text-sm font-bold">{{
                            item.name
                        }}</span
                        ><span class="text-xs text-[#68796d]"
                            >{{ item.type_label }} ·
                            {{ item.category.name }}</span
                        ></span
                    ><ArrowUpRight class="size-4 text-[#087f5b]" />
                </li>
            </ul>
        </div>
    </div>
</template>
