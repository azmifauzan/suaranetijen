<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface AliasItem {
    id: number;
    alias: string;
    normalized_alias: string;
    alias_type: string;
}

interface EntityDetail {
    id: number;
    name: string;
    slug: string;
    type: string;
    description: string | null;
    category_id: number;
    parent_id: number | null;
    status: string;
    searchable: boolean;
    rankable: boolean;
    aliases: AliasItem[];
}

const props = defineProps<{
    entity: EntityDetail;
    categories: Array<{ id: number; name: string }>;
    parent_brands: Array<{ id: number; name: string }>;
}>();

const form = useForm({
    name: props.entity.name,
    slug: props.entity.slug,
    type: props.entity.type,
    category_id: props.entity.category_id,
    parent_id: props.entity.parent_id,
    description: props.entity.description || '',
    status: props.entity.status,
    searchable: props.entity.searchable,
    rankable: props.entity.rankable,
});

const newAlias = ref('');
const newAliasType = ref('common_variant');

function updateEntity() {
    form.put(`/admin/entities/${props.entity.id}`, {
        preserveScroll: true,
    });
}

function addAlias() {
    if (!newAlias.value.trim()) return;

    router.post(
        `/admin/entities/${props.entity.id}/aliases`,
        {
            alias: newAlias.value.trim(),
            alias_type: newAliasType.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newAlias.value = '';
            },
        }
    );
}

function removeAlias(aliasId: number) {
    router.delete(`/admin/entities/${props.entity.id}/aliases/${aliasId}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Edit ${entity.name} - Admin`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <Link href="/admin/entities" class="text-xs text-neutral-500 hover:underline dark:text-neutral-400">
                    &larr; Back to Entities
                </Link>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                    Edit {{ entity.name }}
                </h1>
            </div>
            <a
                :href="`/e/${entity.slug}`"
                target="_blank"
                class="rounded-lg border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300"
            >
                View Public Page &nearr;
            </a>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Edit Form -->
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 lg:col-span-2">
                <form @submit.prevent="updateEntity" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Slug</label>
                        <input
                            v-model="form.slug"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Type</label>
                            <select
                                v-model="form.type"
                                class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <option value="brand">Brand</option>
                                <option value="product">Product</option>
                                <option value="service">Service</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Category</label>
                            <select
                                v-model="form.category_id"
                                class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Parent Brand</label>
                        <select
                            v-model="form.parent_id"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            <option :value="null">None (Standalone Brand)</option>
                            <option v-for="b in parent_brands" :key="b.id" :value="b.id">
                                {{ b.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        ></textarea>
                    </div>

                    <div class="flex flex-wrap gap-6 pt-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.searchable" type="checkbox" class="rounded border-neutral-300" />
                            Searchable
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.rankable" type="checkbox" class="rounded border-neutral-300" />
                            Rankable
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <select v-model="form.status" class="rounded border border-neutral-300 px-2 py-1 text-xs">
                                <option value="active">Active</option>
                                <option value="disabled">Disabled</option>
                            </select>
                            Status
                        </label>
                    </div>

                    <div class="pt-4">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50"
                        >
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Aliases Section -->
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Aliases & Mentions</h2>
                <p class="mt-1 text-xs text-neutral-500">
                    Alternative names matched by crawler and search engine.
                </p>

                <!-- Add alias -->
                <div class="mt-4 space-y-2">
                    <input
                        v-model="newAlias"
                        type="text"
                        placeholder="Add new alias..."
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                        @keyup.enter="addAlias"
                    />
                    <div class="flex items-center gap-2">
                        <select
                            v-model="newAliasType"
                            class="w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            <option value="common_variant">Common Variant</option>
                            <option value="abbreviation">Abbreviation</option>
                            <option value="misspelling">Misspelling</option>
                        </select>
                        <button
                            type="button"
                            class="whitespace-nowrap rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500"
                            @click="addAlias"
                        >
                            + Add
                        </button>
                    </div>
                </div>

                <!-- List of aliases -->
                <div class="mt-6 space-y-2">
                    <div
                        v-for="alias in entity.aliases"
                        :key="alias.id"
                        class="flex items-center justify-between rounded-lg border border-neutral-100 bg-neutral-50 p-2.5 dark:border-neutral-800 dark:bg-neutral-800/50"
                    >
                        <div>
                            <div class="text-xs font-medium text-neutral-800 dark:text-neutral-200">{{ alias.alias }}</div>
                            <div class="text-[10px] text-neutral-400 font-mono">
                                norm: {{ alias.normalized_alias }} • {{ alias.alias_type }}
                            </div>
                        </div>
                        <button
                            type="button"
                            class="text-xs text-rose-500 hover:text-rose-700"
                            title="Remove alias"
                            @click="removeAlias(alias.id)"
                        >
                            &times;
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
