<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="currentColor"
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M15 4C8.925 4 4 8.925 4 15v15c0 6.075 4.925 11 11 11h3v5l10-5h5c6.075 0 11-4.925 11-11V15c0-6.075-4.925-11-11-11H15Zm-1 15a2 2 0 0 1 2 2v6a2 2 0 0 1-4 0v-6a2 2 0 0 1 2-2Zm10-7a2 2 0 0 1 2 2v16a2 2 0 0 1-4 0V14a2 2 0 0 1 2-2Zm10 4a2 2 0 0 1 2 2v8a2 2 0 0 1-4 0v-8a2 2 0 0 1 2-2Z"
        />
    </svg>
</template>
