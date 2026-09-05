<script setup lang="ts">
import { router, useForm, Head } from '@inertiajs/vue3';
import { reactive } from 'vue';

interface Candidate {
    id: number;
    normalized_term: string;
    raw_terms: string[];
    source_types: string[];
    frequency_score: number;
    unmatched_mention_count: number;
    suggested_name: string | null;
    suggested_entity_type: string | null;
    suggested_category_id: number | null;
    suggested_aliases: string[] | null;
    reasoning: string | null;
}

const props = defineProps<{
    candidates: { data: Candidate[]; links: unknown };
    categories: Array<{ id: number; name: string }>;
    brands: Array<{ id: number; name: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Entity Candidates', href: '/admin/entity-candidates' },
        ],
    },
});

const forms = reactive(
    Object.fromEntries(
        props.candidates.data.map((c) => [
            c.id,
            useForm({
                name: c.suggested_name ?? c.normalized_term,
                entity_type: c.suggested_entity_type ?? 'product',
                category_id: c.suggested_category_id,
                parent_id: null as number | null,
                aliases: (c.suggested_aliases ?? []).join(', '),
            }),
        ])
    )
);

function approve(candidateId: number) {
    const form = forms[candidateId];
    form.transform((data) => ({
        ...data,
        aliases: String(data.aliases)
            .split(',')
            .map((a: string) => a.trim())
            .filter(Boolean),
    })).post(`/admin/entity-candidates/${candidateId}/approve`, { preserveScroll: true });
}

function reject(candidateId: number) {
    router.post(`/admin/entity-candidates/${candidateId}/reject`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Entity Candidates - Admin" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">Entity Candidates</h1>
            <p class="mt-1 text-xs text-neutral-500">
                New brand/product terms found in zero-result searches and external feeds, ranked by frequency.
                Review the LLM's suggestion, edit if needed, then approve or dismiss.
            </p>
        </div>

        <div v-if="candidates.data.length === 0" class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-sm text-neutral-500">
            No pending candidates right now.
        </div>

        <div
            v-for="candidate in candidates.data"
            :key="candidate.id"
            class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ candidate.normalized_term }}</div>
                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-neutral-500">
                        <span>score: {{ candidate.frequency_score }}</span>
                        <span>unmatched mentions: {{ candidate.unmatched_mention_count }}</span>
                        <span>sources: {{ candidate.source_types.join(', ') }}</span>
                    </div>
                    <div class="mt-1 text-[11px] text-neutral-400">raw: {{ candidate.raw_terms.slice(0, 5).join(' · ') }}</div>
                    <p v-if="candidate.reasoning" class="mt-2 text-xs text-neutral-600 dark:text-neutral-400">{{ candidate.reasoning }}</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="block text-[10px] font-medium text-neutral-500">Name</label>
                    <input
                        v-model="forms[candidate.id].name"
                        type="text"
                        class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                    />
                </div>
                <div>
                    <label class="block text-[10px] font-medium text-neutral-500">Type</label>
                    <select
                        v-model="forms[candidate.id].entity_type"
                        class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        <option value="brand">Brand</option>
                        <option value="product">Product</option>
                        <option value="service">Service</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-medium text-neutral-500">Category</label>
                    <select
                        v-model="forms[candidate.id].category_id"
                        class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        <option :value="null">Choose...</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-medium text-neutral-500">Parent brand</label>
                    <select
                        v-model="forms[candidate.id].parent_id"
                        class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        <option :value="null">None</option>
                        <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-4">
                    <label class="block text-[10px] font-medium text-neutral-500">Aliases (comma-separated)</label>
                    <input
                        v-model="forms[candidate.id].aliases"
                        type="text"
                        class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                    />
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <button
                    type="button"
                    :disabled="forms[candidate.id].processing"
                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500 disabled:opacity-50"
                    @click="approve(candidate.id)"
                >
                    Approve
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300"
                    @click="reject(candidate.id)"
                >
                    Dismiss
                </button>
            </div>
        </div>
    </div>
</template>
