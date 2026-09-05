<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pengaturan tampilan',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Pengaturan tampilan" />

    <h1 class="sr-only">Pengaturan tampilan</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Tampilan"
            description="Atur tampilan SuaraNetijen sesuai preferensimu"
        />
        <AppearanceTabs />
    </div>
</template>
