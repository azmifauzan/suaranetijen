<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface SeoSharedProps {
    site_name?: string;
    site_url?: string;
}

const props = withDefaults(
    defineProps<{
        title: string;
        description: string;
        canonicalPath: string;
        robots?: string;
        image?: string;
    }>(),
    {
        robots: 'index, follow',
        image: '/logo.svg',
    },
);

const page = usePage();
const seo = computed(
    () => (page.props.seo as SeoSharedProps | undefined) ?? {},
);
const siteName = computed(() => seo.value.site_name || 'SuaraNetijen');
const canonicalUrl = computed(() => {
    const path = props.canonicalPath.startsWith('/')
        ? props.canonicalPath
        : `/${props.canonicalPath}`;

    return `${(seo.value.site_url || '').replace(/\/$/, '')}${path}`;
});
const imageUrl = computed(() => {
    if (props.image?.startsWith('http')) {
        return props.image;
    }

    return `${(seo.value.site_url || '').replace(/\/$/, '')}${props.image}`;
});
</script>

<template>
    <Head :title="`${title} | ${siteName}`">
        <meta
            head-key="description"
            name="description"
            :content="description"
        />
        <meta head-key="robots" name="robots" :content="robots" />
        <link head-key="canonical" rel="canonical" :href="canonicalUrl" />
        <meta
            head-key="og:title"
            property="og:title"
            :content="`${title} | ${siteName}`"
        />
        <meta
            head-key="og:description"
            property="og:description"
            :content="description"
        />
        <meta head-key="og:url" property="og:url" :content="canonicalUrl" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta
            head-key="og:site_name"
            property="og:site_name"
            :content="siteName"
        />
        <meta head-key="og:locale" property="og:locale" content="id_ID" />
        <meta head-key="og:image" property="og:image" :content="imageUrl" />
        <meta head-key="twitter:card" name="twitter:card" content="summary" />
        <meta
            head-key="twitter:title"
            name="twitter:title"
            :content="`${title} | ${siteName}`"
        />
        <meta
            head-key="twitter:description"
            name="twitter:description"
            :content="description"
        />
        <meta
            head-key="twitter:image"
            name="twitter:image"
            :content="imageUrl"
        />
        <slot />
    </Head>
</template>
