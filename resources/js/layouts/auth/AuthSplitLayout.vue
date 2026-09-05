<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BrandLogo from '@/components/BrandLogo.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0"
    >
        <div
            class="relative hidden h-full flex-col border-r border-[#dbe7d2] bg-[#eaf3df] p-10 text-[#18392d] lg:flex"
        >
            <Link
                :href="home()"
                class="relative z-20 flex items-center text-lg font-medium"
                aria-label="SuaraNetijen — Beranda"
            >
                <BrandLogo tagline />
            </Link>
            <div class="relative z-20 mt-auto max-w-md">
                <p class="text-3xl font-bold tracking-tight">
                    Sebelum pilih, cek kata netizen.
                </p>
                <p class="mt-4 text-sm leading-6 text-[#68746b]">
                    SuaraNetijen membantu kamu membaca gambaran opini publik
                    tentang brand, produk, dan layanan di Indonesia.
                </p>
            </div>
        </div>
        <div class="lg:p-8">
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]"
            >
                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-xl font-medium tracking-tight" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-muted-foreground text-sm" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
