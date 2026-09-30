<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Trophy } from '@lucide/vue';
import { index as leaderboardPage } from '@/routes/leaderboard';
import { getDirectWebsiteUrl, getFaviconUrl, trackSponsorClick } from '@/lib/sponsor';
import { show as showEntity } from '@/routes/entities';

interface SponsorEntry {
    id: number;
    rank: number;
    entity_id: number;
    name: string;
    slug: string;
    type_label: string;
    category_name: string;
    website_url?: string | null;
    settled_total_amount: number;
    sentiment_score: number | null;
    opinion_count: number;
}

interface SponsorTeaser {
    is_empty?: boolean;
    entries?: SponsorEntry[];
    /** Only present on homepage teaser shape */
    top_entry?: SponsorEntry | null;
    /** Only present on homepage teaser shape */
    top_entries?: SponsorEntry[];
}

const props = defineProps<{
    teaser: SponsorTeaser;
    /** Optional label shown above the cards, e.g. "Sponsor Kategori ini" */
    label?: string;
}>();

function formatRupiah(amount: number): string {
    return 'Rp' + amount.toLocaleString('id-ID');
}

/** Normalize both teaser shapes into a flat entries array */
const entries = computed(() => {
    if (props.teaser.top_entries) {
        return props.teaser.top_entries.slice(0, 3);
    }
    return props.teaser.entries ?? [];
});

const isEmpty = computed(() => {
    if (typeof props.teaser.is_empty === 'boolean') {
        return props.teaser.is_empty;
    }
    return !props.teaser.top_entry;
});
</script>

<template>
    <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
        <!-- Header row -->
        <div class="mb-3 flex items-center justify-between gap-2">
            <div class="flex items-center gap-1.5">
                <Trophy class="h-4 w-4 text-amber-500" />
                <span class="text-xs font-semibold text-amber-700">
                    {{ label ?? 'Papan Sponsor' }}
                </span>
                <span
                    class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-600"
                >
                    Sponsor
                </span>
            </div>
            <Link
                :href="leaderboardPage.url()"
                class="text-[11px] font-medium text-amber-600 hover:underline"
            >
                Lihat papan penuh →
            </Link>
        </div>

        <!-- Disclosure -->
        <p class="mb-3 text-[10px] leading-relaxed text-amber-600/80">
            Urutan berdasarkan nominal sponsor terkonfirmasi, bukan Sentimen Netijen atau
            penilaian editorial.
        </p>

        <!-- Empty invite -->
        <div v-if="isEmpty" class="text-center py-3">
            <p class="text-xs text-amber-600">Papan Sponsor masih kosong —</p>
            <Link
                :href="leaderboardPage.url()"
                class="mt-1 inline-block text-xs font-semibold text-amber-700 hover:underline"
            >
                Jadi yang pertama sponsori
            </Link>
        </div>

        <!-- Sponsor cards -->
        <div v-else class="flex flex-col gap-2 sm:flex-row sm:gap-3">
            <a
                v-for="entry in entries"
                :key="entry.slug"
                :href="getDirectWebsiteUrl(entry.website_url, entry.slug)"
                target="_blank"
                rel="sponsored noopener noreferrer"
                class="group flex flex-1 items-center gap-3 rounded-lg border border-amber-200 bg-white px-3 py-2.5 transition hover:border-amber-400 hover:shadow-sm"
                @click="trackSponsorClick(entry.slug)"
            >
                <!-- Favicon -->
                <img
                    v-if="getFaviconUrl(entry.website_url)"
                    :src="getFaviconUrl(entry.website_url) ?? undefined"
                    :alt="entry.name"
                    class="h-7 w-7 flex-shrink-0 rounded-full border border-amber-100 object-contain"
                    loading="lazy"
                    onerror="this.style.display='none'"
                />

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <span
                            class="truncate text-xs font-semibold text-neutral-900 group-hover:text-amber-700"
                        >{{ entry.name }}</span>
                        <span
                            v-if="entry.rank <= 3"
                            class="flex-shrink-0 text-[10px]"
                            :title="`#${entry.rank} Papan Sponsor`"
                        >{{ entry.rank === 1 ? '🥇' : entry.rank === 2 ? '🥈' : '🥉' }}</span>
                    </div>
                    <div class="mt-0.5 flex items-center gap-1.5">
                        <span class="text-[10px] text-amber-600 font-medium">
                            {{ formatRupiah(entry.settled_total_amount) }}
                        </span>
                        <span
                            v-if="entry.sentiment_score !== null"
                            class="text-[10px] text-neutral-400"
                        >· {{ entry.sentiment_score }}/100</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Link to entity pages -->
        <div v-if="!isEmpty" class="mt-2 flex flex-wrap gap-2">
            <Link
                v-for="entry in entries"
                :key="entry.slug + '-link'"
                :href="showEntity.url(entry.slug)"
                class="text-[10px] text-neutral-400 hover:text-neutral-600 hover:underline"
            >
                Lihat {{ entry.name }}
            </Link>
        </div>
    </div>
</template>
