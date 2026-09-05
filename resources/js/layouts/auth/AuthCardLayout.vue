<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BrandLogo from '@/components/BrandLogo.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
        <div class="flex w-full max-w-md flex-col gap-6">
            <Link
                :href="home()"
                class="flex items-center gap-2 self-center font-medium"
                aria-label="SuaraNetijen — Beranda"
            >
                <BrandLogo tagline />
            </Link>

            <div class="flex flex-col gap-6">
                <Card
                    class="rounded-2xl border-[#dbe7d2] bg-white/95 shadow-xl shadow-[#18392d]/5"
                >
                    <CardHeader class="px-10 pt-8 pb-0 text-center">
                        <CardTitle class="text-xl">{{ title }}</CardTitle>
                        <CardDescription>
                            {{ description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="px-10 py-8">
                        <slot />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
