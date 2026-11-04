<script setup lang="ts">
import { BadgeCheck } from '@lucide/vue';
import { officialSocials } from '@/lib/socials';

const paths: Record<string, string> = {
    instagram:
        'M7.75 2h8.5A5.75 5.75 0 0 1 22 7.75v8.5A5.75 5.75 0 0 1 16.25 22h-8.5A5.75 5.75 0 0 1 2 16.25v-8.5A5.75 5.75 0 0 1 7.75 2Zm0 1.8A3.95 3.95 0 0 0 3.8 7.75v8.5a3.95 3.95 0 0 0 3.95 3.95h8.5a3.95 3.95 0 0 0 3.95-3.95v-8.5a3.95 3.95 0 0 0-3.95-3.95h-8.5ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 1.8a3.2 3.2 0 1 0 0 6.4 3.2 3.2 0 0 0 0-6.4Zm5.2-2.45a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4Z',
    facebook:
        'M13.5 22v-8.2h2.8l.5-3.3h-3.3V8.4c0-.9.4-1.7 1.8-1.7h1.6V3.9c-.3 0-1.3-.2-2.4-.2-2.5 0-4.1 1.5-4.1 4.2v2.6H7.6v3.3h2.8V22h3.1Z',
    threads:
        'M12.19 24h-.01c-3.58-.02-6.33-1.2-8.18-3.51C2.35 18.44 1.5 15.59 1.47 12.01v-.02c.03-3.58.88-6.43 2.53-8.48C5.85 1.2 8.6.02 12.18 0h.01c2.75.02 5.04.73 6.83 2.1 1.68 1.29 2.86 3.13 3.51 5.47l-2.04.57c-1.1-3.96-3.9-5.98-8.3-6.02-2.91.02-5.11.94-6.54 2.72C4.31 6.5 3.62 8.91 3.59 12c.03 3.09.72 5.5 2.06 7.16 1.43 1.78 3.63 2.7 6.54 2.72 2.62-.02 4.36-.63 5.8-2.05 1.65-1.61 1.62-3.59 1.09-4.8-.31-.71-.87-1.3-1.63-1.75-.19 1.35-.62 2.45-1.28 3.27-.89 1.1-2.14 1.7-3.73 1.79-1.2.07-2.36-.22-3.26-.8-1.06-.69-1.69-1.74-1.75-2.96-.07-1.19.41-2.29 1.33-3.08.88-.76 2.12-1.21 3.58-1.29 1.07-.06 2.07-.01 3.02.14-.13-.74-.38-1.33-.75-1.76-.51-.59-1.31-.88-2.36-.89h-.03c-.84 0-1.99.23-2.72 1.32L7.73 7.85c.98-1.45 2.57-2.26 4.48-2.26h.04c3.19.02 5.1 1.98 5.29 5.39.11.05.22.09.32.14 1.49.7 2.58 1.76 3.15 3.07.8 1.82.87 4.79-1.55 7.16-1.85 1.81-4.09 2.63-7.28 2.65Zm1-11.69c-.24 0-.49.01-.74.02-1.84.1-2.98.95-2.92 2.14.07 1.26 1.45 1.84 2.78 1.77 1.22-.07 2.82-.54 3.09-3.71a10.5 10.5 0 0 0-2.21-.22Z',
};
</script>

<template>
    <div>
        <p class="flex items-center gap-2 text-sm font-bold">
            Ikuti SuaraNetijen
            <span
                class="bg-brand-tint text-brand-deep inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                ><BadgeCheck class="size-3.5" aria-hidden="true" />Akun
                resmi</span
            >
        </p>
        <ul
            class="mt-3 flex flex-wrap gap-2"
            aria-label="Akun resmi media sosial"
        >
            <li v-for="social in officialSocials" :key="social.key">
                <a
                    :href="social.url"
                    target="_blank"
                    rel="noopener noreferrer me"
                    :aria-label="`${social.label} SuaraNetijen, ${social.handle} (buka di tab baru)`"
                    class="border-line hover:bg-sage inline-flex items-center gap-2 rounded-full border bg-white px-3.5 py-2 text-sm font-semibold transition"
                >
                    <svg
                        viewBox="0 0 24 24"
                        class="size-4"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path :d="paths[social.key]" />
                    </svg>
                    {{ social.label }}
                </a>
            </li>
        </ul>
    </div>
</template>
