<script setup lang="ts">
import { Head, Link, useForm, useHttp } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Eye,
    FileText,
    Image as ImageIcon,
    Loader2,
    Plus,
    Search,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import admin from '@/routes/admin';

interface EntitySearchResult {
    id: number;
    name: string;
    slug: string;
    type?: string;
    category?: { name: string };
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Blog', href: '/admin/blog' },
            { title: 'Tulis Artikel', href: '#' },
        ],
    },
});

const form = useForm({
    title: '',
    slug: '',
    excerpt: '',
    body: '',
    published_at: '',
    entity_ids: [] as number[],
    cover: null as File | null,
});

const coverPreview = ref<string | null>(null);
const activeTab = ref<'write' | 'preview'>('write');
const previewHtml = ref('');
const previewRequest = useHttp<{ markdown: string }, { html: string }>({
    markdown: '',
});

const selectedEntities = ref<Array<{ id: number; name: string; slug: string }>>(
    [],
);
const entitySearchQuery = ref('');
const entitySearchResults = ref<EntitySearchResult[]>([]);
const isSearchingEntities = ref(false);

function onCoverChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        const file = target.files[0];
        form.cover = file;
        coverPreview.value = URL.createObjectURL(file);
    }
}

function removeCover() {
    form.cover = null;
    coverPreview.value = null;
}

async function fetchPreview() {
    activeTab.value = 'preview';
    previewRequest.markdown = form.body;
    try {
        previewHtml.value = (
            await previewRequest.post(admin.blog.preview().url)
        ).html;
    } catch {
        previewHtml.value =
            '<p class="text-red-600">Gagal memuat pratinjau.</p>';
    }
}

async function searchEntities() {
    if (entitySearchQuery.value.trim().length < 2) {
        entitySearchResults.value = [];
        return;
    }
    isSearchingEntities.value = true;
    try {
        const res = await fetch(
            `/api/search?q=${encodeURIComponent(entitySearchQuery.value)}&limit=8`,
        );
        const data = await res.json();
        const items = data.data || data;
        entitySearchResults.value = Array.isArray(items) ? items : [];
    } catch {
        entitySearchResults.value = [];
    } finally {
        isSearchingEntities.value = false;
    }
}

function addEntity(entity: EntitySearchResult) {
    if (!selectedEntities.value.some((e) => e.id === entity.id)) {
        selectedEntities.value.push({
            id: entity.id,
            name: entity.name,
            slug: entity.slug,
        });
        form.entity_ids.push(entity.id);
    }
    entitySearchQuery.value = '';
    entitySearchResults.value = [];
}

function removeEntity(id: number) {
    selectedEntities.value = selectedEntities.value.filter((e) => e.id !== id);
    form.entity_ids = form.entity_ids.filter((entityId) => entityId !== id);
}

function submit() {
    form.post(admin.blog.store.url(), {
        forceFormData: true,
    });
}
</script>

<template>
    <Head title="Tulis Artikel Blog - Admin" />

    <div class="mx-auto max-w-4xl space-y-6 p-6">
        <div>
            <Link
                :href="admin.blog.index()"
                class="inline-flex items-center gap-1 text-xs font-semibold text-neutral-500 hover:text-neutral-900"
            >
                <ArrowLeft class="size-3.5" />
                Kembali ke Daftar Blog
            </Link>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-neutral-900">
                Tulis Artikel Blog
            </h1>
            <p class="text-xs text-neutral-500">
                Buat artikel analisis, editorial, atau metodologi SuaraNetijen.
                Artikel disimpan sebagai draft awal.
            </p>
        </div>

        <form
            class="space-y-6 rounded-xl border border-neutral-200 bg-white p-6 shadow-2xs"
            @submit.prevent="submit"
        >
            <!-- Judul -->
            <div>
                <div class="flex items-center justify-between">
                    <label
                        class="block text-xs font-bold text-neutral-700 uppercase"
                        >Judul Artikel (Wajib)</label
                    >
                    <span class="text-[11px] text-neutral-400"
                        >{{ form.title.length }}/160</span
                    >
                </div>
                <input
                    v-model="form.title"
                    type="text"
                    maxlength="160"
                    placeholder="Contoh: Rangkuman Sentimen Smartphone Kuartal III 2026"
                    class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-[#087f5b] focus:outline-hidden"
                    required
                />
                <p class="mt-1 text-[11px] text-neutral-500">
                    Aturan copy: jangan gunakan kata superlatif
                    ("terbaik/terburuk"), persentase (%), URL, atau mention (@).
                </p>
                <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">
                    {{ form.errors.title }}
                </p>
            </div>

            <!-- Slug -->
            <div>
                <label
                    class="block text-xs font-bold text-neutral-700 uppercase"
                    >Slug URL (Opsional)</label
                >
                <input
                    v-model="form.slug"
                    type="text"
                    placeholder="Kosongkan untuk otomatis dibuat dari judul"
                    class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 font-mono text-sm focus:border-[#087f5b] focus:outline-hidden"
                />
                <p class="mt-1 text-[11px] text-neutral-500">
                    Format: huruf kecil, angka, dan tanda hubung (-). Slug
                    dikunci permanen setelah artikel diterbitkan.
                </p>
                <p v-if="form.errors.slug" class="mt-1 text-xs text-red-600">
                    {{ form.errors.slug }}
                </p>
            </div>

            <!-- Cover Image -->
            <div>
                <label
                    class="block text-xs font-bold text-neutral-700 uppercase"
                    >Cover Image (Opsional)</label
                >
                <p class="mb-2 text-[11px] text-neutral-500">
                    Format: JPG, PNG, atau WebP. Maks 2 MB, lebar minimal 1200px
                    (ukuran optimal og:image).
                </p>

                <div
                    v-if="coverPreview"
                    class="relative mb-3 max-w-md overflow-hidden rounded-lg border border-neutral-200"
                >
                    <img
                        :src="coverPreview"
                        alt="Cover preview"
                        class="h-48 w-full object-cover"
                    />
                    <button
                        type="button"
                        class="absolute top-2 right-2 rounded-full bg-neutral-900/70 p-1 text-white transition-colors hover:bg-neutral-900"
                        @click="removeCover"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="block w-full cursor-pointer text-xs text-neutral-500 file:mr-3 file:rounded-md file:border-0 file:bg-neutral-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-neutral-700 hover:file:bg-neutral-200"
                    @change="onCoverChange"
                />
                <p v-if="form.errors.cover" class="mt-1 text-xs text-red-600">
                    {{ form.errors.cover }}
                </p>
            </div>

            <!-- Excerpt -->
            <div>
                <div class="flex items-center justify-between">
                    <label
                        class="block text-xs font-bold text-neutral-700 uppercase"
                        >Ringkasan / Excerpt (Opsional)</label
                    >
                    <span class="text-[11px] text-neutral-400"
                        >{{ form.excerpt.length }}/300</span
                    >
                </div>
                <textarea
                    v-model="form.excerpt"
                    rows="2"
                    maxlength="300"
                    placeholder="Ringkasan singkat untuk kartu artikel dan meta description. Jika kosong, diambil dari paragraf pertama."
                    class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-[#087f5b] focus:outline-hidden"
                ></textarea>
                <p v-if="form.errors.excerpt" class="mt-1 text-xs text-red-600">
                    {{ form.errors.excerpt }}
                </p>
            </div>

            <!-- Body (Markdown) + Preview tab -->
            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label
                        class="block text-xs font-bold text-neutral-700 uppercase"
                        >Konten Artikel (Markdown, Wajib)</label
                    >
                    <div
                        class="flex rounded-md border border-neutral-200 bg-neutral-100 p-0.5 text-xs"
                    >
                        <button
                            type="button"
                            :class="[
                                'rounded px-2.5 py-1 font-semibold transition-all',
                                activeTab === 'write'
                                    ? 'bg-white text-neutral-900 shadow-2xs'
                                    : 'text-neutral-500 hover:text-neutral-900',
                            ]"
                            @click="activeTab = 'write'"
                        >
                            Tulis (Markdown)
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded px-2.5 py-1 font-semibold transition-all',
                                activeTab === 'preview'
                                    ? 'bg-white text-neutral-900 shadow-2xs'
                                    : 'text-neutral-500 hover:text-neutral-900',
                            ]"
                            @click="fetchPreview"
                        >
                            Pratinjau
                        </button>
                    </div>
                </div>

                <div v-show="activeTab === 'write'">
                    <textarea
                        v-model="form.body"
                        rows="14"
                        placeholder="Tulis artikel menggunakan format Markdown (# Heading, ## Subheading, **teks tebal**, [tautan](url), dll)..."
                        class="w-full rounded-lg border border-neutral-300 p-3 font-mono text-sm leading-relaxed focus:border-[#087f5b] focus:outline-hidden"
                        required
                    ></textarea>
                    <p class="mt-1 text-[11px] text-neutral-500">
                        Catatan: Tag HTML mentah akan dibersihkan secara
                        otomatis di server demi keamanan pembaca.
                    </p>
                </div>

                <div
                    v-show="activeTab === 'preview'"
                    class="min-h-72 rounded-lg border border-neutral-200 bg-neutral-50/50 p-4"
                >
                    <div
                        v-if="previewRequest.processing"
                        class="flex items-center justify-center py-12 text-neutral-400"
                    >
                        <Loader2 class="mr-2 size-5 animate-spin" />
                        Memuat pratinjau server...
                    </div>
                    <div
                        v-else
                        class="blog-body text-sm"
                        v-html="
                            previewHtml ||
                            '<p class=\'text-neutral-400 italic\'>Belum ada teks untuk dipratinjau.</p>'
                        "
                    ></div>
                </div>

                <p v-if="form.errors.body" class="mt-1 text-xs text-red-600">
                    {{ form.errors.body }}
                </p>
            </div>

            <!-- Entitas Terkait -->
            <div>
                <label
                    class="block text-xs font-bold text-neutral-700 uppercase"
                    >Entitas Terkait (Opsional)</label
                >
                <p class="mb-2 text-[11px] text-neutral-500">
                    Entitas yang dibahas dalam artikel. Kartu entitas beserta
                    Sentimen Netijen live akan ditampilkan di bawah artikel.
                </p>

                <!-- Selected pills -->
                <div
                    v-if="selectedEntities.length > 0"
                    class="mb-3 flex flex-wrap gap-2"
                >
                    <span
                        v-for="entity in selectedEntities"
                        :key="entity.id"
                        class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-800"
                    >
                        {{ entity.name }}
                        <button
                            type="button"
                            class="text-emerald-600 hover:text-emerald-900"
                            @click="removeEntity(entity.id)"
                        >
                            <X class="size-3" />
                        </button>
                    </span>
                </div>

                <!-- Entity Search Input -->
                <div class="relative">
                    <Search
                        class="absolute top-2.5 left-3 size-4 text-neutral-400"
                    />
                    <input
                        v-model="entitySearchQuery"
                        type="search"
                        placeholder="Ketik minimal 2 huruf untuk mencari entitas (brand / produk / layanan)..."
                        class="w-full rounded-lg border border-neutral-300 py-2 pr-3 pl-9 text-xs focus:border-[#087f5b] focus:outline-hidden"
                        @input="searchEntities"
                    />
                    <div
                        v-if="entitySearchResults.length > 0"
                        class="absolute z-20 mt-1 max-h-48 w-full overflow-auto rounded-lg border border-neutral-200 bg-white py-1 shadow-lg"
                    >
                        <button
                            v-for="res in entitySearchResults"
                            :key="res.id"
                            type="button"
                            class="flex w-full items-center justify-between px-3 py-1.5 text-left text-xs hover:bg-neutral-50"
                            @click="addEntity(res)"
                        >
                            <span class="font-medium text-neutral-900">{{
                                res.name
                            }}</span>
                            <span class="text-[10px] text-neutral-400">{{
                                res.category?.name || 'Entitas'
                            }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Published At -->
            <div>
                <label
                    class="block text-xs font-bold text-neutral-700 uppercase"
                    >Jadwal / Tanggal Terbit (Opsional)</label
                >
                <input
                    v-model="form.published_at"
                    type="datetime-local"
                    class="mt-1 w-full max-w-xs rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-[#087f5b] focus:outline-hidden"
                />
                <p class="mt-1 text-[11px] text-neutral-500">
                    Kosongkan jika ingin tanggal publikasi diisi otomatis saat
                    Anda menekan tombol "Terbitkan". Jika tanggal di masa depan
                    diisi, artikel berstatus terbit baru tampil di publik
                    setelah waktu tersebut tiba.
                </p>
                <p
                    v-if="form.errors.published_at"
                    class="mt-1 text-xs text-red-600"
                >
                    {{ form.errors.published_at }}
                </p>
            </div>

            <!-- Submit -->
            <div
                class="flex items-center justify-end gap-3 border-t border-neutral-100 pt-4"
            >
                <Link
                    :href="admin.blog.index()"
                    class="rounded-lg border border-neutral-300 px-4 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50"
                >
                    Batal
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#087f5b] px-5 py-2 text-xs font-semibold text-white shadow-2xs transition-colors hover:bg-[#066347] disabled:opacity-50"
                >
                    <Plus class="size-3.5" />
                    Simpan Draft Artikel
                </button>
            </div>
        </form>
    </div>
</template>
