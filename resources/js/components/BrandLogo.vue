<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

withDefaults(defineProps<{ tagline?: boolean }>(), { tagline: false });
</script>

<template>
    <span class="inline-flex items-center gap-2.5">
        <AppLogoIcon
            class="size-10 shrink-0 text-[#087f5b]"
            aria-hidden="true"
        />
        <span class="flex flex-col">
            <span class="text-[22px] leading-tight font-bold tracking-[-1px]"
                >suara<span class="text-[#087f5b]">netijen</span
                ><span class="text-[#087f5b]">.</span></span
            >
            <span
                v-if="tagline"
                class="mt-1 text-xs tracking-normal text-[#66736c]"
                >Cari tahu. Dengar netijen.</span
            >
        </span>
    </span>
</template>
