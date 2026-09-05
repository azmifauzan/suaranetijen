<script setup lang="ts">
import type { LinkComponentBaseProps, Method } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Props = {
    href: LinkComponentBaseProps['href'];
    tabindex?: number;
    method?: Method;
    as?: string;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <Link
        :href="props.href"
        :tabindex="props.tabindex"
        :method="props.method"
        :as="props.as"
        :class="
            cn(
                'text-primary decoration-primary/40 hover:text-primary/80 hover:decoration-primary inline-flex font-medium underline underline-offset-4 transition-colors duration-300 ease-out',
                props.class,
            )
        "
    >
        <slot />
    </Link>
</template>
