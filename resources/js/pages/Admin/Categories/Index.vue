<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface CategoryItem {
    id: number;
    parent_id: number | null;
    name: string;
    slug: string;
    status: string;
    entities_count: number;
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
    categories: PaginatedData<CategoryItem>;
    parent_categories: Array<{ id: number; name: string }>;
    filters: { search: string };
}>();

const search = ref(props.filters.search || '');
const showCreateModal = ref(false);
const newName = ref('');
const newSlug = ref('');
const newParentId = ref<number | null>(null);

function handleSearch() {
    router.get(
        '/admin/categories',
        { search: search.value },
        { preserveState: true, replace: true }
    );
}

function handleCreate() {
    router.post(
        '/admin/categories',
        {
            name: newName.value,
            slug: newSlug.value || undefined,
            parent_id: newParentId.value,
        },
        {
            onSuccess: () => {
                showCreateModal.value = false;
                newName.value = '';
                newSlug.value = '';
                newParentId.value = null;
            },
        }
    );
}

function toggleStatus(category: CategoryItem) {
    router.post(`/admin/categories/${category.id}/toggle-status`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Manage Categories - Admin" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                    Categories
                </h1>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Organize taxonomy and categories for entities.
                </p>
            </div>
            <button
                type="button"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500"
                @click="showCreateModal = true"
            >
                + Add Category
            </button>
        </div>

        <!-- Search Bar -->
        <div class="flex items-center gap-2">
            <input
                v-model="search"
                type="text"
                placeholder="Search categories..."
                class="w-full max-w-sm rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                @keyup.enter="handleSearch"
            />
            <button
                type="button"
                class="rounded-lg bg-neutral-200 px-4 py-2 text-sm font-medium text-neutral-800 hover:bg-neutral-300 dark:bg-neutral-700 dark:text-neutral-200"
                @click="handleSearch"
            >
                Search
            </button>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <table class="min-w-full divide-y divide-neutral-200 text-left text-sm dark:divide-neutral-800">
                <thead class="bg-neutral-50 font-medium text-neutral-600 dark:bg-neutral-800/50 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Slug</th>
                        <th class="px-4 py-3">Parent Category</th>
                        <th class="px-4 py-3">Entities</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                    <tr v-for="cat in categories.data" :key="cat.id" class="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/50">
                        <td class="px-4 py-3 font-semibold text-neutral-900 dark:text-neutral-100">{{ cat.name }}</td>
                        <td class="px-4 py-3 text-neutral-500 font-mono text-xs">{{ cat.slug }}</td>
                        <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">
                            {{ cat.parent?.name || '—' }}
                        </td>
                        <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">
                            {{ cat.entities_count }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="cat.status === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-300'"
                            >
                                {{ cat.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                @click="toggleStatus(cat)"
                            >
                                {{ cat.status === 'active' ? 'Disable' : 'Enable' }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="categories.data.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-neutral-500">
                            No categories found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create Category Modal -->
        <div
            v-if="showCreateModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-neutral-900">
                <h2 class="text-lg font-bold text-neutral-900 dark:text-neutral-100">Add Category</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Name</label>
                        <input
                            v-model="newName"
                            type="text"
                            placeholder="e.g. Smartphone"
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
                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Parent Category</label>
                        <select
                            v-model="newParentId"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            <option :value="null">None (Top-level Category)</option>
                            <option v-for="parent in parent_categories" :key="parent.id" :value="parent.id">
                                {{ parent.name }}
                            </option>
                        </select>
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
