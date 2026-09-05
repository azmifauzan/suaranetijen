<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import BrandLogo from '@/components/BrandLogo.vue';
import { home } from '@/routes';

const props = withDefaults(defineProps<{ status: number }>(), {
    status: 500,
});

const content = computed(() => {
    const pages: Record<number, { title: string; description: string }> = {
        401: {
            title: 'Perlu masuk terlebih dahulu',
            description: 'Silakan masuk untuk melanjutkan ke halaman ini.',
        },
        403: {
            title: 'Akses tidak tersedia',
            description: 'Kamu tidak memiliki izin untuk membuka halaman ini.',
        },
        404: {
            title: 'Halaman tidak ditemukan',
            description:
                'Halaman yang kamu cari mungkin sudah dipindahkan atau tidak tersedia.',
        },
        419: {
            title: 'Sesi kedaluwarsa',
            description:
                'Halaman ini sudah terlalu lama terbuka. Muat ulang dan coba lagi.',
        },
        429: {
            title: 'Terlalu banyak permintaan',
            description: 'Tunggu sebentar sebelum mencoba lagi.',
        },
        500: {
            title: 'Ada gangguan di sisi kami',
            description:
                'Terjadi kesalahan tak terduga. Silakan coba lagi sebentar lagi.',
        },
        503: {
            title: 'SuaraNetijen sedang beristirahat',
            description:
                'Kami sedang melakukan perawatan. Coba kembali beberapa saat lagi.',
        },
    };

    return pages[props.status] ?? pages[500];
});
</script>

<template>
    <Head :title="`${status} — ${content.title}`" />

    <main
        class="flex min-h-svh items-center justify-center bg-[#f4f8f1] px-6 py-12 text-[#18392d]"
    >
        <div class="w-full max-w-lg text-center">
            <Link
                :href="home()"
                class="inline-flex items-center"
                aria-label="SuaraNetijen — Beranda"
            >
                <BrandLogo tagline />
            </Link>

            <div class="mt-12">
                <p class="text-sm font-bold tracking-[0.28em] text-[#087f5b]">
                    ERROR {{ status }}
                </p>
                <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">
                    {{ content.title }}
                </h1>
                <p
                    class="mx-auto mt-4 max-w-md text-base leading-7 text-[#68746b]"
                >
                    {{ content.description }}
                </p>
            </div>

            <Link
                :href="home()"
                class="mt-8 inline-flex items-center justify-center rounded-full bg-[#087f5b] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#066446] focus:ring-2 focus:ring-[#087f5b] focus:ring-offset-2"
            >
                Kembali ke beranda
            </Link>
        </div>
    </main>
</template>
