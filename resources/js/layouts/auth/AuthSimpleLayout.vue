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
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-[#f4f8f1] p-6 text-[#18392d] md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                        aria-label="SuaraNetijen — Beranda"
                    >
                        <BrandLogo tagline />
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-bold tracking-tight">
                            {{ title }}
                        </h1>
                        <p class="text-center text-sm text-[#68746b]">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
