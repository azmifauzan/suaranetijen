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
        image: '/og-image.png?v=2',
    },
);

const page = usePage();
const seo = computed(
    () => (page.props.seo as SeoSharedProps | undefined) ?? {},
);
const siteName = computed(() => seo.value.site_name || 'SuaraNetijen');
// Search results cut titles at about 60 characters; drop the suffix rather than the keyword.
const fullTitle = computed(() => {
    const withSuffix = `${props.title} | ${siteName.value}`;

    return withSuffix.length > 60 ? props.title : withSuffix;
});
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
    <Head :title="fullTitle">
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
            :content="fullTitle"
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
        <meta
            head-key="og:image:alt"
            property="og:image:alt"
            :content="fullTitle"
        />
        <meta
            head-key="twitter:card"
            name="twitter:card"
            content="summary_large_image"
        />
        <meta head-key="og:image:width" property="og:image:width" content="1200" />
        <meta head-key="og:image:height" property="og:image:height" content="630" />
        <meta
            head-key="twitter:title"
            name="twitter:title"
            :content="fullTitle"
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
