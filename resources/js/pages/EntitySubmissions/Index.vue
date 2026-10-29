<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Inbox, Plus } from '@lucide/vue';
import { ref } from 'vue';
import EntitySubmissionController from '@/actions/App/Domains/Entities/Controllers/EntitySubmissionController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show } from '@/routes/entities';
import { index } from '@/routes/entity-submissions';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usulan Brand/Produk', href: index() }],
    },
});

type Status = 'pending' | 'approved' | 'rejected';

defineProps<{
    submissions: {
        id: number;
        name: string;
        entity_type: string;
        status: Status;
        review_note: string | null;
        entity_slug: string | null;
        created_at: string | null;
    }[];
    categories: { id: number; name: string }[];
    brands: { id: number; name: string }[];
}>();

const isFormOpen = ref(false);
const entityType = ref('brand');

const typeLabel: Record<string, string> = {
    brand: 'Brand',
    product: 'Produk',
    service: 'Layanan',
};

const statusLabel: Record<Status, string> = {
    pending: 'Menunggu review',
    approved: 'Disetujui',
    rejected: 'Ditolak',
};

const statusClass: Record<Status, string> = {
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
};

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

function formatDate(value: string | null): string {
    if (value === null) {
        return '';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'Asia/Jakarta',
    });
}

function onSubmitted(): void {
    isFormOpen.value = false;
    entityType.value = 'brand';
}
</script>

<template>
    <Head title="Usulan Brand/Produk" />

    <div class="flex w-full flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-bold tracking-tight">Usulan Brand/Produk</h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    Usulkan brand, produk, atau layanan yang belum ada. Admin meninjau setiap
                    usulan sebelum tampil dan usulan tidak mempengaruhi skor atau peringkat.
                </p>
            </div>
            <Button v-if="submissions.length > 0" @click="isFormOpen = true">
                <Plus class="size-4" /> Tambah usulan
            </Button>
        </div>

        <div
            v-if="submissions.length === 0"
            class="flex flex-col items-center gap-3 rounded-xl border border-dashed px-6 py-16 text-center"
        >
            <Inbox class="text-muted-foreground size-10" />
            <div>
                <p class="font-medium">Belum ada usulan</p>
                <p class="text-muted-foreground mt-1 text-sm">
                    Brand atau produkmu belum ada di SuaraNetijen? Usulkan sekarang.
                </p>
            </div>
            <Button @click="isFormOpen = true">
                <Plus class="size-4" /> Tambah usulan
            </Button>
        </div>

        <ul v-else class="divide-y rounded-xl border">
            <li v-for="submission in submissions" :key="submission.id" class="px-4 py-4 sm:px-5">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ submission.name }}</p>
                        <p class="text-muted-foreground mt-0.5 text-xs">
                            {{ typeLabel[submission.entity_type] ?? submission.entity_type }}
                            · {{ formatDate(submission.created_at) }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <Link
                            v-if="submission.status === 'approved' && submission.entity_slug"
                            :href="show(submission.entity_slug)"
                            class="text-primary text-sm underline"
                        >
                            Lihat halaman
                        </Link>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="statusClass[submission.status]"
                        >
                            {{ statusLabel[submission.status] }}
                        </span>
                    </div>
                </div>
                <p
                    v-if="submission.status === 'rejected' && submission.review_note"
                    class="text-muted-foreground mt-2 text-sm"
                >
                    Alasan: {{ submission.review_note }}
                </p>
            </li>
        </ul>

    <Dialog v-model:open="isFormOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <Form
                v-bind="EntitySubmissionController.store.form()"
                class="space-y-5"
                :options="{ preserveScroll: true }"
                reset-on-success
                @success="onSubmitted"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>Tambah usulan</DialogTitle>
                    <DialogDescription>
                        Isi data brand, produk, atau layanan yang ingin diusulkan.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="name">Nama</Label>
                    <Input id="name" name="name" required maxlength="120" placeholder="Contoh: Kopi Senja" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid grid-cols-2 gap-4">
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

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Batal</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">Kirim usulan</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
    </div>
</template>
