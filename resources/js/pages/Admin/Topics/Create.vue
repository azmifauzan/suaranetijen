<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import admin from '@/routes/admin';

interface CategoryOption {
    id: number;
    name: string;
}

defineProps<{
    categories: CategoryOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Topik Landing Pages', href: '/admin/topics' },
            { title: 'Buat Manual', href: '#' },
        ],
    },
});

const form = useForm({
    keyword: '',
    category_id: null as number | null,
    title: '',
    meta_description: '',
    intro: '',
});

function submit() {
    form.post(admin.topics.store.url());
}
</script>

<template>
    <Head title="Buat Topik Manual - Admin" />

    <div class="mx-auto max-w-2xl space-y-6 p-6">
        <div>
            <Link
                :href="admin.topics.index()"
                class="inline-flex items-center gap-1 text-xs font-semibold text-neutral-500 hover:text-neutral-900"
            >
                <ArrowLeft class="size-3.5" />
                Kembali ke Daftar Topik
            </Link>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-neutral-900">
                Buat Topik Manual
            </h1>
            <p class="text-xs text-neutral-500">
                Tambahkan kata kunci topik secara langsung ke antrian draft.
            </p>
        </div>

        <form class="space-y-4 rounded-xl border border-neutral-200 bg-white p-6 shadow-2xs" @submit.prevent="submit">
            <!-- Keyword -->
            <div>
                <label class="block text-xs font-bold text-neutral-700 uppercase">Keyword Topik (Wajib)</label>
                <input
                    v-model="form.keyword"
                    type="text"
                    placeholder="Contoh: hp baterai awet, vps murah"
                    class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-[#087f5b] focus:outline-hidden"
                    required
                />
                <p v-if="form.errors.keyword" class="mt-1 text-xs text-red-600">
                    {{ form.errors.keyword }}
                </p>
            </div>

            <!-- Category -->
            <div>
                <label class="block text-xs font-bold text-neutral-700 uppercase">Kategori (Opsional)</label>
                <select
                    v-model="form.category_id"
                    class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-[#087f5b] focus:outline-hidden"
                >
                    <option :value="null">-- Pilih Kategori --</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">
                        {{ c.name }}
                    </option>
                </select>
                <p v-if="form.errors.category_id" class="mt-1 text-xs text-red-600">
                    {{ form.errors.category_id }}
                </p>
            </div>

            <!-- Title -->
            <div>
                <label class="block text-xs font-bold text-neutral-700 uppercase">Judul H1 (Opsional)</label>
                <input
                    v-model="form.title"
                    type="text"
                    maxlength="60"
                    placeholder="Judul halaman topik..."
                    class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-[#087f5b] focus:outline-hidden"
                />
                <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">
                    {{ form.errors.title }}
                </p>
            </div>

            <!-- Meta description -->
            <div>
                <label class="block text-xs font-bold text-neutral-700 uppercase">Meta Description (Opsional)</label>
                <textarea
                    v-model="form.meta_description"
                    rows="2"
                    maxlength="155"
                    placeholder="Deskripsi singkat untuk SEO..."
                    class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-[#087f5b] focus:outline-hidden"
                />
                <p v-if="form.errors.meta_description" class="mt-1 text-xs text-red-600">
                    {{ form.errors.meta_description }}
                </p>
            </div>

            <!-- Intro -->
            <div>
                <label class="block text-xs font-bold text-neutral-700 uppercase">Intro Teks (Opsional)</label>
                <textarea
                    v-model="form.intro"
                    rows="3"
                    maxlength="1000"
                    placeholder="Teks pengantar topik..."
                    class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-[#087f5b] focus:outline-hidden"
                />
                <p v-if="form.errors.intro" class="mt-1 text-xs text-red-600">
                    {{ form.errors.intro }}
                </p>
            </div>

            <div class="border-t border-neutral-100 pt-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#087f5b] px-4 py-2 text-sm font-semibold text-white hover:bg-[#076c4d]"
                >
                    <Plus class="size-4" />
                    Simpan Topik
                </button>
            </div>
        </form>
    </div>
</template>
