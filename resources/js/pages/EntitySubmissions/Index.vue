<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import EntitySubmissionController from '@/actions/App/Domains/Entities/Controllers/EntitySubmissionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/entity-submissions';
import { show } from '@/routes/entities';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usulan Brand/Produk', href: index() }],
    },
});

defineProps<{
    submissions: {
        id: number;
        name: string;
        entity_type: string;
        status: 'pending' | 'approved' | 'rejected';
        review_note: string | null;
        entity_slug: string | null;
    }[];
    categories: { id: number; name: string }[];
    brands: { id: number; name: string }[];
}>();

const entityType = ref('brand');

const statusLabel = {
    pending: 'Menunggu review',
    approved: 'Disetujui',
    rejected: 'Ditolak',
};

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';
</script>

<template>
    <Head title="Usulan Brand/Produk" />

    <h1 class="sr-only">Usulan Brand/Produk</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Usulkan brand atau produk"
            description="Brand, produk, atau layanan belum ada di SuaraNetijen? Kirim usulan. Admin meninjau setiap usulan sebelum tampil. Mengusulkan tidak mempengaruhi skor atau peringkat."
        />

        <Form
            v-bind="EntitySubmissionController.store.form()"
            class="space-y-5"
            :options="{ preserveScroll: true }"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Nama</Label>
                <Input id="name" name="name" required maxlength="120" placeholder="Contoh: Kopi Senja" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="entity_type">Jenis</Label>
                <select id="entity_type" v-model="entityType" name="entity_type" :class="selectClass">
                    <option value="brand">Brand</option>
                    <option value="product">Produk</option>
                    <option value="service">Layanan</option>
                </select>
                <InputError :message="errors.entity_type" />
            </div>

            <div class="grid gap-2">
                <Label for="category_id">Kategori</Label>
                <select id="category_id" name="category_id" required :class="selectClass">
                    <option value="">Pilih kategori</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                        {{ category.name }}
                    </option>
                </select>
                <InputError :message="errors.category_id" />
            </div>

            <div v-if="entityType !== 'brand'" class="grid gap-2">
                <Label for="parent_entity_id">Brand induk (opsional)</Label>
                <select id="parent_entity_id" name="parent_entity_id" :class="selectClass">
                    <option value="">Tidak ada</option>
                    <option v-for="brand in brands" :key="brand.id" :value="brand.id">
                        {{ brand.name }}
                    </option>
                </select>
                <InputError :message="errors.parent_entity_id" />
            </div>

            <div class="grid gap-2">
                <Label for="website_url">Website (opsional)</Label>
                <Input id="website_url" name="website_url" type="url" placeholder="https://" />
                <InputError :message="errors.website_url" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Deskripsi singkat (opsional)</Label>
                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    maxlength="500"
                    class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                />
                <InputError :message="errors.description" />
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_owner" value="0" />
                <input type="checkbox" name="is_owner" value="1" class="size-4" />
                Saya pemilik atau pengelola brand ini
            </label>

            <Button type="submit" :disabled="processing">Kirim usulan</Button>
        </Form>

        <div class="space-y-3">
            <Heading variant="small" title="Usulan saya" />
            <p v-if="submissions.length === 0" class="text-muted-foreground text-sm">
                Belum ada usulan.
            </p>
            <ul v-else class="divide-y rounded-lg border">
                <li
                    v-for="submission in submissions"
                    :key="submission.id"
                    class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                >
                    <span class="font-medium">{{ submission.name }}</span>
                    <Link
                        v-if="submission.status === 'approved' && submission.entity_slug"
                        :href="show(submission.entity_slug)"
                        class="text-primary underline"
                    >
                        {{ statusLabel[submission.status] }}
                    </Link>
                    <span v-else class="text-muted-foreground text-right">
                        {{ statusLabel[submission.status] }}
                        <span v-if="submission.status === 'rejected' && submission.review_note" class="block text-xs">
                            {{ submission.review_note }}
                        </span>
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
