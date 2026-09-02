<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface EntityItem {
    id: number;
    name: string;
    slug: string;
    type: string;
    status: string;
    searchable: boolean;
    rankable: boolean;
    aliases_count: number;
    category: { id: number; name: string };
    parent?: { id: number; name: string } | null;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    entities: PaginatedData<EntityItem>;
    categories: Array<{ id: number; name: string }>;
    parent_brands: Array<{ id: number; name: string }>;
    filters: {
        search?: string;
        category_id?: number | null;
        type?: string | null;
        status?: string | null;
    };
}>();

const search = ref(props.filters.search || '');
const categoryId = ref<number | null>(props.filters.category_id || null);
const type = ref<string | null>(props.filters.type || null);
const status = ref<string | null>(props.filters.status || null);

const showCreateModal = ref(false);
const newName = ref('');
const newSlug = ref('');
const newType = ref('product');
const newCategoryId = ref<number | null>(null);
const newParentId = ref<number | null>(null);
const newDescription = ref('');

function applyFilters() {
    router.get(
        '/admin/entities',
        {
            search: search.value || undefined,
            category_id: categoryId.value || undefined,
            type: type.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, replace: true }
    );
}

function handleCreate() {
    if (!newCategoryId.value) return;

    router.post(
        '/admin/entities',
        {
            name: newName.value,
            slug: newSlug.value || undefined,
            type: newType.value,
            category_id: newCategoryId.value,
            parent_id: newParentId.value,
            description: newDescription.value || undefined,
        },
        {
            onSuccess: () => {
                showCreateModal.value = false;
                newName.value = '';
                newSlug.value = '';
                newType.value = 'product';
                newCategoryId.value = null;
                newParentId.value = null;
                newDescription.value = '';
            },
        }
    );
}

function toggleStatus(entity: EntityItem) {
    router.post(`/admin/entities/${entity.id}/toggle-status`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Manage Entities - Admin" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                    Entities Catalog
                </h1>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Brands, products, and services tracked across public sentiment sources. Total: {{ entities.total }}
                </p>
            </div>
            <button
                type="button"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500"
                @click="showCreateModal = true"
            >
                + Add Entity
            </button>
        </div>

        <!-- Filters Bar -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <input
                v-model="search"
                type="text"
                placeholder="Search name or slug..."
                class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                @keyup.enter="applyFilters"
            />
            <select
                v-model="categoryId"
                class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                @change="applyFilters"
            >
                <option :value="null">All Categories</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                    {{ cat.name }}
                </option>
            </select>
            <select
                v-model="type"
                class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                @change="applyFilters"
            >
                <option :value="null">All Types</option>
                <option value="brand">Brand</option>
                <option value="product">Product</option>
                <option value="service">Service</option>
            </select>
            <select
                v-model="status"
                class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                @change="applyFilters"
            >
                <option :value="null">All Status</option>
                <option value="active">Active</option>
                <option value="disabled">Disabled</option>
            </select>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <table class="min-w-full divide-y divide-neutral-200 text-left text-sm dark:divide-neutral-800">
                <thead class="bg-neutral-50 font-medium text-neutral-600 dark:bg-neutral-800/50 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Parent Brand</th>
                        <th class="px-4 py-3">Aliases</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                    <tr v-for="entity in entities.data" :key="entity.id" class="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/50">
                        <td class="px-4 py-3">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100">{{ entity.name }}</div>
                            <div class="font-mono text-xs text-neutral-500">/e/{{ entity.slug }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold uppercase"
                                :class="{
                                    'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300': entity.type === 'brand',
                                    'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300': entity.type === 'product',
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300': entity.type === 'service',
                                }"
                            >
                                {{ entity.type }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-neutral-600 dark:text-neutral-300">{{ entity.category.name }}</td>
                        <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">
                            {{ entity.parent?.name || '—' }}
                        </td>
                        <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">
                            {{ entity.aliases_count }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="entity.status === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-300'"
                            >
                                {{ entity.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <Link
                                :href="`/admin/entities/${entity.id}/edit`"
                                class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                            >
                                Edit / Aliases
                            </Link>
                            <button
                                type="button"
                                class="text-xs font-medium text-neutral-600 hover:underline dark:text-neutral-400"
                                @click="toggleStatus(entity)"
                            >
                                {{ entity.status === 'active' ? 'Disable' : 'Enable' }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="entities.data.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center text-neutral-500">
                            No entities found matching filters.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create Entity Modal -->
        <div
            v-if="showCreateModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        >
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-neutral-900">
                <h2 class="text-lg font-bold text-neutral-900 dark:text-neutral-100">Add Entity</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Name</label>
                        <input
                            v-model="newName"
                            type="text"
                            placeholder="e.g. Samsung Galaxy A55"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Slug (Optional)</label>
                        <input
                            v-model="newSlug"
                            type="text"
                            placeholder="auto-generated if empty"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Type</label>
                            <select
                                v-model="newType"
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
                                v-model="newCategoryId"
                                class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <option :value="null">Select category...</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div v-if="newType !== 'brand'">
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Parent Brand (Optional)</label>
                        <select
                            v-model="newParentId"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            <option :value="null">None</option>
                            <option v-for="brand in parent_brands" :key="brand.id" :value="brand.id">
                                {{ brand.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Description</label>
                        <textarea
                            v-model="newDescription"
                            rows="2"
                            placeholder="Short description for disambiguation..."
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        ></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300"
                        @click="showCreateModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500"
                        @click="handleCreate"
                    >
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
