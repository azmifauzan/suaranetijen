<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

interface Setting {
    base_url: string | null;
    model: string | null;
    has_api_key: boolean;
    max_tokens: number;
    temperature: number;
    timeout_seconds: number;
}

const props = defineProps<{
    setting: Setting | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'LLM Settings', href: '/admin/llm-settings' },
        ],
    },
});

const form = useForm({
    base_url: props.setting?.base_url ?? '',
    model: props.setting?.model ?? '',
    api_key: '',
    max_tokens: props.setting?.max_tokens ?? 1024,
    temperature: props.setting?.temperature ?? 0.2,
    timeout_seconds: props.setting?.timeout_seconds ?? 30,
});

function save() {
    form.put('/admin/llm-settings', { preserveScroll: true });
}
</script>

<template>
    <Head title="LLM Settings - Admin" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">LLM Settings</h1>
            <p class="mt-1 text-xs text-neutral-500">
                Shared by every LLM-backed feature (entity candidate enrichment, and any future one) — changes take
                effect immediately, no redeploy needed.
            </p>
        </div>

        <div class="max-w-xl rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <form @submit.prevent="save" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Base URL</label>
                    <input
                        v-model="form.base_url"
                        type="text"
                        placeholder="https://api.openai.com/v1"
                        class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    />
                    <p v-if="form.errors.base_url" class="mt-1 text-xs text-rose-500">{{ form.errors.base_url }}</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Model</label>
                    <input
                        v-model="form.model"
                        type="text"
                        placeholder="gpt-4o-mini"
                        class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    />
                    <p v-if="form.errors.model" class="mt-1 text-xs text-rose-500">{{ form.errors.model }}</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">API Key</label>
                    <input
                        v-model="form.api_key"
                        type="password"
                        :placeholder="setting?.has_api_key ? '•••••••• (leave blank to keep current key)' : 'sk-...'"
                        class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    />
                    <p v-if="form.errors.api_key" class="mt-1 text-xs text-rose-500">{{ form.errors.api_key }}</p>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Max tokens</label>
                        <input
                            v-model.number="form.max_tokens"
                            type="number"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Temperature</label>
                        <input
                            v-model.number="form.temperature"
                            type="number"
                            step="0.1"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Timeout (s)</label>
                        <input
                            v-model.number="form.timeout_seconds"
                            type="number"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        />
                    </div>
                </div>

                <div class="pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50"
                    >
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
