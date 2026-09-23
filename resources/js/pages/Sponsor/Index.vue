<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Award,
    CheckCircle2,
    Clock,
    Eye,
    Globe,
    MousePointerClick,
    Search,
    Shield,
    Trophy,
    X,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import PublicSeo from '@/components/PublicSeo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { getDirectWebsiteUrl, getFaviconUrl, trackSponsorClick } from '@/lib/sponsor';
import { show as showEntity } from '@/routes/entities';
import { index as leaderboardPage } from '@/routes/leaderboard';
import { paymentStatus } from '@/routes/sponsor';

interface PeriodItem {
    id: number;
    key: string;
    name: string;
    status: string;
    is_active: boolean;
}

interface LeaderboardEntry {
    id: number;
    rank: number;
    entity_id: number;
    name: string;
    slug: string;
    type_label: string;
    category_name: string;
    website_url?: string | null;
    description?: string | null;
    settled_total_amount: number;
    first_settled_at: string | null;
    clicks_count: number;
    views_count: number;
    sentiment_score: number | null;
    opinion_count: number;
    rating_average: number | null;
    rating_count: number;
}

interface BoardStats {
    total_listings: number;
    total_amount: number;
    total_clicks: number;
    total_views: number;
    highest_bid: number;
}

interface UserOrderInfo {
    id: number;
    provider_order_id: string;
    amount: number;
    status: string;
    status_label: string;
    entity_name: string;
    entity_slug: string;
    payment_link_url: string | null;
}

interface CategoryItem {
    id: number;
    name: string;
    slug: string;
}

const props = defineProps<{
    periods: PeriodItem[];
    activePeriod: { id: number; key: string; name: string };
    selectedPeriod: {
        id: number;
        key: string;
        name: string;
        is_active: boolean;
    };
    leaderboard: LeaderboardEntry[];
    stats?: BoardStats;
    minAmount: number;
    incrementAmount: number;
    userOrder?: UserOrderInfo | null;
    categories: CategoryItem[];
}>();

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);

// Leaderboard search (rankup.uno-style): filters the already-loaded board client-side, entirely
// separate from the sponsor-entry form below — this never touches organic search relevance.
const leaderboardQuery = ref('');
const filteredLeaderboard = computed(() => {
    const q = leaderboardQuery.value.trim().toLowerCase();
    if (!q) return props.leaderboard;

    return props.leaderboard.filter(
        (entry) =>
            entry.name.toLowerCase().includes(q) || entry.category_name.toLowerCase().includes(q),
    );
});
const leaderboardPageSize = 25;
const visibleLeaderboardCount = ref(leaderboardPageSize);
const visibleLeaderboard = computed(() =>
    filteredLeaderboard.value.slice(0, visibleLeaderboardCount.value),
);
const leaderboardSections = computed(() => {
    const topTenEntries = visibleLeaderboard.value.slice(3, 10);
    const remainingEntries = visibleLeaderboard.value.slice(10);
    const firstRemainingRank = remainingEntries[0]?.rank;
    const lastRemainingRank = remainingEntries[remainingEntries.length - 1]?.rank;

    return [
        {
            key: 'ranks-4-10',
            title: 'Peringkat #4 sampai #10',
            entries: topTenEntries,
        },
        {
            key: 'ranks-11-plus',
            title:
                firstRemainingRank && lastRemainingRank
                    ? `Peringkat #${firstRemainingRank} sampai #${lastRemainingRank}`
                    : 'Peringkat lainnya',
            entries: remainingEntries,
        },
    ].filter((section) => section.entries.length > 0);
});
const canLoadMoreLeaderboard = computed(
    () => visibleLeaderboardCount.value < filteredLeaderboard.value.length,
);

function loadMoreLeaderboard() {
    visibleLeaderboardCount.value += leaderboardPageSize;
}

watch(leaderboardQuery, () => {
    visibleLeaderboardCount.value = leaderboardPageSize;
});

// Sponsor entry form state — inline on the page (Pamerin/RankUp both keep this as a plain page
// section, never a popup), with a confirm-and-pay modal only at the final step.
const formSection = ref<HTMLElement | null>(null);
const urlInput = ref('');
const isFetchingPreview = ref(false);
const previewError = ref<string | null>(null);
const urlPreview = ref<{ title: string; url: string; description: string | null } | null>(null);
const candidates = ref<Array<{ id: number; name: string; slug: string; category_name: string; type_label: string }>>([]);
const selectedEntity = ref<{ id: number; name: string; slug: string } | null>(null);
// New-entity mode: the retrieved URL matched no existing entity, so the user names it and picks
// a category instead (RankUp asks the same at submission) — the entity itself isn't created
// until the payment actually confirms (docs/26; see ProcessSponsorshipRelayWebhook).
const newEntityName = ref('');
const newEntityCategoryId = ref<number | null>(null);
// Pre-filled from the fetched page's own meta description (FetchUrlPreview), still fully editable;
// only used on the new-entity path, where it lands in Entity.description.
const newEntityDescription = ref('');
const isNewEntityMode = computed(
    () => !!urlPreview.value && !isFetchingPreview.value && candidates.value.length === 0 && !selectedEntity.value,
);
const hasSponsorTarget = computed(() => !!selectedEntity.value || isNewEntityMode.value);
const contributionAmount = ref<number>(10000);
const customAmount = ref<string>('10000');
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);
const guestEmail = ref<string>('');
const isConfirmModalOpen = ref(false);

const isValidGuestEmail = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(guestEmail.value.trim()));

interface RebutTarget {
    rank: number;
    name: string;
    entityId?: number;
    targetAmount: number;
    neededAmount: number;
}

const rebutTarget = ref<RebutTarget | null>(null);

function startRebut(entry: { rank: number; name: string; settled_total_amount: number; entity_id?: number }) {
    const targetAmount = entry.settled_total_amount;
    const increment = props.incrementAmount || 100;
    let needed = Math.max(props.minAmount, targetAmount + increment);

    if (selectedEntity.value) {
        const existingEntry = props.leaderboard.find((e) => e.entity_id === selectedEntity.value?.id);
        if (existingEntry) {
            needed = Math.max(props.minAmount, (targetAmount + increment) - existingEntry.settled_total_amount);
        }
    }

    rebutTarget.value = {
        rank: entry.rank,
        name: entry.name,
        entityId: 'entity_id' in entry ? entry.entity_id : undefined,
        targetAmount: targetAmount,
        neededAmount: needed,
    };

    contributionAmount.value = needed;
    customAmount.value = needed.toString();

    errorMessage.value = null;
    formSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function cancelRebut() {
    rebutTarget.value = null;
    contributionAmount.value = 10000;
    customAmount.value = '10000';
}

const presetAmounts = [1000, 10000, 25000, 50000, 100000];

function selectPreset(amount: number) {
    contributionAmount.value = amount;
    customAmount.value = amount.toString();
}

function handleCustomAmountChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const val = parseInt(target.value, 10);
    if (!isNaN(val)) {
        contributionAmount.value = val;
    }
}

// Predict rank
const predictedRank = computed(() => {
    if (!selectedEntity.value || !contributionAmount.value) return null;
    const entityId = selectedEntity.value.id;
    const currentEntry = props.leaderboard.find((e) => e.entity_id === entityId);
    const newTotal = (currentEntry?.settled_total_amount ?? 0) + contributionAmount.value;

    let higherCount = 0;
    for (const item of props.leaderboard) {
        if (item.entity_id === entityId) continue;
        if (item.settled_total_amount > newTotal) {
            higherCount++;
        } else if (item.settled_total_amount === newTotal && currentEntry?.first_settled_at && item.first_settled_at && item.first_settled_at < currentEntry.first_settled_at) {
            higherCount++;
        }
    }
    return higherCount + 1;
});

// URL-first preview + match, mirroring Pamerin's flow (confirmed live): paste a URL, the site
// is fetched server-side for its title, and only then does the rest of the form appear — here,
// "the rest" is either a match against existing entities, or (no match) a name + category to
// register a new one, created only once payment confirms.
//
// A bare domain (no http/https typed) must still work — Pamerin's own input accepts that — so
// this normalizes before validating/sending rather than rejecting anything without a scheme.
function normalizeUrlInput(value: string): string | null {
    const trimmed = value.trim();
    if (!trimmed) return null;

    const withScheme = /^https?:\/\//i.test(trimmed) ? trimmed : `https://${trimmed}`;
    try {
        const parsed = new URL(withScheme);
        return parsed.protocol === 'http:' || parsed.protocol === 'https:' ? withScheme : null;
    } catch {
        return null;
    }
}

let previewTimer: ReturnType<typeof setTimeout> | null = null;
watch(urlInput, (value) => {
    if (previewTimer) clearTimeout(previewTimer);
    urlPreview.value = null;
    candidates.value = [];
    previewError.value = null;
    selectedEntity.value = null;
    newEntityName.value = '';
    newEntityCategoryId.value = null;
    newEntityDescription.value = '';

    const normalized = normalizeUrlInput(value);
    if (!normalized) {
        return;
    }

    previewTimer = setTimeout(async () => {
        isFetchingPreview.value = true;
        try {
            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
            const res = await fetch('/api/sponsor/preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: JSON.stringify({ url: normalized }),
            });
            const json = await res.json();

            if (!res.ok) {
                previewError.value = json.error || 'Gagal mengambil informasi dari URL tersebut.';
                return;
            }

            urlPreview.value = json.preview;
            candidates.value = (json.candidates || []).slice(0, 5).map((item: any) => ({
                id: item.id,
                name: item.name,
                slug: item.slug,
                category_name: item.category_name || item.category?.name || 'Umum',
                type_label: item.type_label || 'Entitas',
            }));

            if (candidates.value.length === 0) {
                newEntityName.value = json.preview.title;
                newEntityDescription.value = json.preview.description || '';
            }
        } catch {
            previewError.value = 'Gagal mengambil informasi dari URL tersebut.';
        } finally {
            isFetchingPreview.value = false;
        }
    }, 400);
});

watch(selectedEntity, (newEntity) => {
    if (rebutTarget.value) {
        const targetAmount = rebutTarget.value.targetAmount;
        const increment = props.incrementAmount || 100;
        let needed = Math.max(props.minAmount, targetAmount + increment);
        if (newEntity) {
            const existingEntry = props.leaderboard.find((e) => e.entity_id === newEntity.id);
            if (existingEntry) {
                needed = Math.max(props.minAmount, (targetAmount + increment) - existingEntry.settled_total_amount);
            }
        }
        rebutTarget.value.neededAmount = needed;
        contributionAmount.value = needed;
        customAmount.value = needed.toString();
    }
});

onMounted(() => {
    if (typeof window === 'undefined') return;

    const params = new URLSearchParams(window.location.search);
    const rebutRank = params.get('rebut_rank');
    const targetName = params.get('target_name');
    const neededAmountStr = params.get('needed_amount');

    if (rebutRank) {
        const rankNum = parseInt(rebutRank, 10);
        const matched = props.leaderboard.find((e) => e.rank === rankNum);
        if (matched) {
            startRebut(matched);
        } else if (targetName && neededAmountStr) {
            const needed = parseInt(neededAmountStr, 10) || props.minAmount;
            const increment = props.incrementAmount || 100;
            rebutTarget.value = {
                rank: rankNum,
                name: targetName,
                targetAmount: Math.max(0, needed - increment),
                neededAmount: needed,
            };
            contributionAmount.value = needed;
            customAmount.value = needed.toString();
            setTimeout(() => {
                formSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 150);
        }
    }
});

// Used by "+ Sponsori" buttons on already-listed entities: skip the URL step entirely and jump
// straight to step 2, scrolling the (always-on-page) form into view.
function selectEntityAndScrollToForm(entity: { id: number; name: string; slug: string }) {
    selectedEntity.value = entity;
    urlInput.value = '';
    urlPreview.value = null;
    candidates.value = [];
    previewError.value = null;
    errorMessage.value = null;
    formSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function openConfirmModal() {
    if (!selectedEntity.value && !isNewEntityMode.value) {
        errorMessage.value = 'Silakan pilih entitas yang ingin disponsori.';
        return;
    }

    if (isNewEntityMode.value && !newEntityName.value.trim()) {
        errorMessage.value = 'Nama entitas wajib diisi.';
        return;
    }

    if (isNewEntityMode.value && !newEntityCategoryId.value) {
        errorMessage.value = 'Pilih kategori entitas.';
        return;
    }

    if (contributionAmount.value < props.minAmount) {
        errorMessage.value = `Nominal minimal adalah Rp${props.minAmount.toLocaleString('id-ID')}.`;
        return;
    }

    if (!currentUser.value && !isValidGuestEmail.value) {
        errorMessage.value = 'Masukkan email yang valid untuk melanjutkan.';
        return;
    }

    errorMessage.value = null;
    isConfirmModalOpen.value = true;
}

function closeConfirmModal() {
    isConfirmModalOpen.value = false;
}

async function submitOrder() {
    if (!selectedEntity.value && !isNewEntityMode.value) return;

    isSubmitting.value = true;
    errorMessage.value = null;

    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
        const response = await fetch('/api/sponsor/orders', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
            },
            body: JSON.stringify({
                amount: contributionAmount.value,
                // No redirect_url: the backend builds the correct post-payment URL itself
                // (with the real order id, known only after creation) as its own default.
                ...(currentUser.value ? {} : { email: guestEmail.value.trim() }),
                ...(selectedEntity.value
                    ? {
                          entity_id: selectedEntity.value.id,
                          website_url: urlPreview.value?.url || undefined,
                      }
                    : {
                          new_entity_name: newEntityName.value.trim(),
                          new_entity_category_id: newEntityCategoryId.value,
                          new_entity_url: urlPreview.value?.url,
                          new_entity_description: newEntityDescription.value.trim() || null,
                      }),
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            errorMessage.value = data.message || data.error || 'Gagal memproses pesanan sponsor.';
            isConfirmModalOpen.value = false;
            return;
        }

        if (data.data?.payment_link_url) {
            window.location.href = data.data.payment_link_url;
        } else {
            router.visit(paymentStatus({ query: { order_id: data.data?.id } }));
        }
    } catch (e: any) {
        errorMessage.value = e.message || 'Terjadi kesalahan saat memproses pesanan.';
        isConfirmModalOpen.value = false;
    } finally {
        isSubmitting.value = false;
    }
}

function formatRupiah(amount: number): string {
    return 'Rp' + amount.toLocaleString('id-ID');
}
</script>

<template>
    <PublicLayout>
        <PublicSeo
            title="Leaderboard Sponsor Brand, Produk, dan Layanan Indonesia"
            description="Leaderboard sponsor SuaraNetijen: peringkat exposure publik untuk brand, produk, dan layanan Indonesia berdasarkan nominal sponsor terkonfirmasi."
            canonical-path="/leaderboard"
        />

        <div class="mx-auto max-w-6xl px-5 pt-5 pb-8 sm:px-8 sm:pt-8 sm:pb-12">
            <!-- Order status banner if coming back from payment -->
            <div
                v-if="userOrder"
                class="mb-8 rounded-2xl border p-5 shadow-sm"
                :class="
                    userOrder.status === 'paid'
                        ? 'border-[#c1e8cf] bg-[#f0faf3]'
                        : 'border-[#fed7aa] bg-[#fffbeb]'
                "
            >
                <div class="flex items-start gap-4">
                    <CheckCircle2
                        v-if="userOrder.status === 'paid'"
                        class="size-6 text-[#16a34a] shrink-0"
                    />
                    <Clock
                        v-else
                        class="size-6 text-[#d97706] shrink-0"
                    />
                    <div class="flex-1">
                        <h4
                            class="font-bold text-sm sm:text-base"
                            :class="
                                userOrder.status === 'paid'
                                    ? 'text-[#166534]'
                                    : 'text-[#92400e]'
                            "
                        >
                            {{
                                userOrder.status === 'paid'
                                    ? 'Pembayaran Berhasil Dikonfirmasi!'
                                    : 'Pesanan Sponsor Sedang Diproses'
                            }}
                        </h4>
                        <p class="mt-1 text-xs text-[#526456] sm:text-sm">
                            Sponsor untuk
                            <span class="font-semibold text-[#18392d]">{{
                                userOrder.entity_name
                            }}</span>
                            sebesar
                            <span class="font-semibold text-[#18392d]">{{
                                formatRupiah(userOrder.amount)
                            }}</span>
                            ({{ userOrder.status_label }}).
                            <span v-if="userOrder.status === 'paid'">
                                Kontribusi telah terakumulasi pada periode ini.
                            </span>
                            <span v-else>
                                Jika belum membayar, Anda dapat melanjutkan pembayaran melalui QRIS.
                            </span>
                        </p>
                        <div
                            v-if="userOrder.status === 'pending' && userOrder.payment_link_url"
                            class="mt-3"
                        >
                            <a
                                :href="userOrder.payment_link_url"
                                class="inline-flex items-center rounded-lg bg-[#d97706] px-4 py-2 text-xs font-semibold text-white transition hover:bg-[#b45309]"
                            >
                                Bayar Sekarang via QRIS
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-black tracking-tight text-[#18392d] sm:text-3xl">
                    Leaderboard
                </h1>
            </div>

            <!-- Top 3 sits first so the competitive target is visible before the sponsor form. -->
            <div class="space-y-2">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-sm font-bold text-[#18392d]">Top 3 Leaderboard</h2>

                    <!-- Period Switcher -->
                    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-[#738378] mr-1">
                            Periode:
                        </span>
                        <Link
                            v-for="p in periods"
                            :key="p.id"
                            :href="leaderboardPage({ query: { period: p.key } })"
                            class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition"
                            :class="
                                selectedPeriod.key === p.key
                                    ? 'bg-[#18392d] text-white shadow-sm'
                                    : 'border border-[#d7e0d5] bg-white text-[#4c5f53] hover:border-[#9ab59f]'
                            "
                        >
                            {{ p.name }}
                            <span
                                v-if="p.is_active"
                                class="ml-1 inline-block size-1.5 rounded-full bg-[#22c55e]"
                            ></span>
                        </Link>
                    </div>
                </div>

                <div
                    v-if="leaderboard.length > 0 && filteredLeaderboard.length > 0"
                    class="flex flex-col gap-2"
                >
                    <div
                        v-for="entry in filteredLeaderboard.slice(0, 3)"
                        :key="entry.id"
                        class="flex flex-col gap-2 rounded-xl border bg-white px-3 py-2 transition hover:border-[#8ab591] sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                        :class="
                            entry.rank === 1
                                ? 'border-[#f2ddb3] bg-[#fffefb]'
                                : entry.rank === 2
                                  ? 'border-[#dce4dd] bg-white'
                                  : 'border-[#e8dfd5] bg-white'
                        "
                    >
                        <div class="flex min-w-0 items-center gap-2.5">
                            <span
                                class="inline-flex size-6 shrink-0 items-center justify-center rounded-md text-xs font-black"
                                :class="
                                    entry.rank === 1
                                        ? 'bg-[#fef3c7] text-[#92400e] border border-[#fde68a]'
                                        : entry.rank === 2
                                          ? 'bg-[#e2e8f0] text-[#334155] border border-[#cbd5e1]'
                                          : 'bg-[#ffedd5] text-[#9a3412] border border-[#fed7aa]'
                                "
                            >
                                #{{ entry.rank }}
                            </span>
                            <img
                                v-if="getFaviconUrl(entry.website_url)"
                                :src="getFaviconUrl(entry.website_url)!"
                                :alt="entry.name"
                                class="size-6 shrink-0 rounded-md border border-black/10 bg-white object-contain p-0.5"
                                loading="lazy"
                                @error="(e) => ((e.target as HTMLElement).style.display = 'none')"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <Link
                                        :href="showEntity(entry.slug)"
                                        class="truncate text-sm font-bold text-[#18392d] hover:text-[#087f5b]"
                                    >
                                        {{ entry.name }}
                                    </Link>
                                    <span class="hidden truncate rounded-md bg-black/5 px-2 py-0.5 text-[10px] font-semibold text-[#5a6b60] sm:inline">
                                        {{ entry.type_label }}
                                    </span>
                                </div>
                                <p class="truncate text-[11px] leading-4 text-[#738478]">
                                    {{ entry.category_name }}
                                    <span v-if="entry.description" class="hidden lg:inline"> · {{ entry.description }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5 border-t border-[#edf1ec] pt-1.5 sm:border-0 sm:pt-0 sm:justify-end">
                            <!-- Stats: Sentimen, Views, Clicks (Visible on Mobile & Desktop) -->
                            <div class="flex items-center gap-2 text-[10px] text-[#55695a] sm:text-[11px]">
                                <span
                                    v-if="typeof entry.sentiment_score === 'number'"
                                    class="inline-flex items-center gap-0.5 font-bold text-[#1b6b44]"
                                    title="Sentimen Netijen"
                                >
                                    Sentimen {{ entry.sentiment_score.toLocaleString('id-ID', { maximumFractionDigits: 1 }) }}
                                </span>
                                <span v-else class="text-[#718274]">Tanpa skor</span>

                                <span>•</span>

                                <span class="inline-flex items-center gap-1" title="Kunjungan detail">
                                    <Eye class="size-3 text-[#2563eb]" />
                                    {{ (entry.views_count || 0).toLocaleString('id-ID') }}
                                </span>

                                <span>•</span>

                                <span class="inline-flex items-center gap-1" title="Klik website">
                                    <MousePointerClick class="size-3 text-[#087f5b]" />
                                    {{ (entry.clicks_count || 0).toLocaleString('id-ID') }} klik
                                </span>
                            </div>

                            <!-- Total Sponsor -->
                            <div class="text-right">
                                <span class="text-xs font-black text-[#92400e] sm:text-sm">
                                    {{ formatRupiah(entry.settled_total_amount) }}
                                </span>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-1.5">
                                <a
                                    :href="getDirectWebsiteUrl(entry.website_url, entry.slug, { placement: 'leaderboard_top3' })"
                                    :ping="`/api/sponsor/click/${entry.slug}`"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex min-h-8 items-center justify-center rounded-lg bg-[#eaf7ee] px-2.5 py-1 text-xs font-bold text-[#145736] transition hover:bg-[#bceccb]"
                                    @click="trackSponsorClick(entry.slug, { placement: 'leaderboard_top3', url: entry.website_url || undefined })"
                                >
                                    Buka Situs
                                </a>
                                <button
                                    type="button"
                                    class="inline-flex min-h-8 items-center justify-center rounded-lg border border-[#d8e3d6] bg-white px-2.5 py-1 text-xs font-bold text-[#1f4a38] transition hover:border-[#8cb896] hover:bg-[#edf6ee]"
                                    @click="selectEntityAndScrollToForm({ id: entry.entity_id, name: entry.name, slug: entry.slug })"
                                >
                                    + Sponsor
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex min-h-8 items-center justify-center rounded-lg border border-[#f59e0b] bg-[#fffbeb] px-2.5 py-1 text-xs font-bold text-[#92400e] transition hover:bg-[#fef3c7]"
                                    @click="startRebut(entry)"
                                >
                                    Rebut #{{ entry.rank }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="leaderboard.length === 0"
                    class="rounded-xl border border-dashed border-[#dce3db] bg-[#fafcfa] p-4 text-center text-xs text-[#637568]"
                >
                    Belum ada entitas di Top 3 untuk periode ini.
                </div>
            </div>

            <!-- Sponsor Entry Form — inline on the page, not a popup (Pamerin/RankUp both keep
                 this as a plain section; only the final payment confirmation below is a modal). -->
            <div ref="formSection" class="mt-5 rounded-2xl border border-[#e5e9e2] bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-center gap-2 text-xs font-bold text-[#d97706]">
                    <Trophy class="size-4" />
                    SPONSORI ENTITAS
                </div>
                <h3 class="mt-1 text-lg font-extrabold text-[#18392d]">
                    Tingkatkan Posisi di Leaderboard
                </h3>
                <p class="mt-1 text-xs text-[#637568]">
                    Dukungan Anda terakumulasi pada entitas pilihan untuk periode {{ activePeriod.name }}.
                </p>

                <!-- Mode Rebut Posisi Banner -->
                <div
                    v-if="rebutTarget"
                    class="mt-4 flex flex-col gap-3 rounded-2xl border border-[#f59e0b] bg-[#fffcf2] p-3 text-[#92400e] shadow-2xs sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#d97706] text-white shadow-xs">
                            <Trophy class="size-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full bg-[#f59e0b] px-2.5 py-0.5 text-[10px] font-black tracking-wide text-white uppercase">
                                    Mode Rebut Posisi
                                </span>
                                <span class="font-bold text-sm text-[#78350f]">
                                    Target: Posisi #{{ rebutTarget.rank }} ({{ rebutTarget.name }})
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-[#92400e]">
                                Sponsori minimal <strong class="font-extrabold text-[#78350f]">{{ formatRupiah(rebutTarget.neededAmount) }}</strong> untuk langsung menggeser posisi <strong>{{ rebutTarget.name }}</strong> di leaderboard periode ini!
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-[#d97706]/30 bg-white/90 px-3 py-1.5 text-xs font-bold text-[#92400e] transition hover:bg-white"
                            @click="cancelRebut()"
                        >
                            Batal Rebut
                        </button>
                    </div>
                </div>

                <!-- Step 1: URL first — fetch the site, then match against existing entities. -->
                <div class="mt-4">
                    <label class="block text-xs font-bold text-[#31483b]">
                        Link Website Resmi Entitas
                    </label>
                    <div class="relative mt-1.5">
                        <Search class="absolute top-3 left-3 size-4 text-[#8e9f93]" />
                        <input
                            v-model="urlInput"
                            type="url"
                            placeholder="https://situs-resmi-brand.com"
                            class="w-full rounded-xl border border-[#cfd9ce] py-2.5 pr-4 pl-9 text-sm text-[#18392d] placeholder-[#8e9f93] focus:border-[#087f5b] focus:ring-1 focus:ring-[#087f5b] focus:outline-none"
                        />
                    </div>
                    <p class="mt-1.5 text-[11px] text-[#788a7e]">
                        Kami ambil judul situsnya, lalu cocokkan dengan entitas yang sudah terdaftar di SuaraNetijen.
                    </p>

                    <div v-if="isFetchingPreview" class="mt-3 flex items-center gap-2 text-xs text-[#637568]">
                        <span class="size-3.5 animate-spin rounded-full border-2 border-[#cfd9ce] border-t-[#087f5b]" />
                        Mengambil informasi situs...
                    </div>

                    <div
                        v-if="previewError"
                        class="mt-3 rounded-xl border border-[#f3c9c9] bg-[#fdf2f2] p-3 text-xs text-[#b91c1c]"
                    >
                        {{ previewError }}
                    </div>

                    <!-- Live preview, mirroring Pamerin's "Nanti tampil seperti ini" card -->
                    <div
                        v-if="urlPreview && !selectedEntity"
                        class="mt-3 rounded-xl border border-[#d8e2d6] bg-[#f7faf6] p-3 text-xs"
                    >
                        <p class="text-[10px] font-bold tracking-wide text-[#8e9f93] uppercase">Ditemukan</p>
                        <p class="mt-1 font-bold text-[#18392d]">{{ urlPreview.title }}</p>
                        <p class="mt-0.5 truncate text-[#6e7f73]">{{ urlPreview.url }}</p>
                    </div>

                    <!-- Candidate entity matches -->
                    <div
                        v-if="candidates.length > 0 && !selectedEntity"
                        class="mt-2 max-h-48 overflow-y-auto rounded-xl border border-[#d8e2d6] bg-white shadow-lg"
                    >
                        <button
                            v-for="item in candidates"
                            :key="item.id"
                            type="button"
                            class="flex w-full items-center justify-between p-3 text-left transition hover:bg-[#f0f7f0]"
                            @click="selectedEntity = item"
                        >
                            <div>
                                <div class="text-sm font-bold text-[#18392d]">
                                    {{ item.name }}
                                </div>
                                <div class="text-xs text-[#6e7f73]">
                                    {{ item.type_label }} · {{ item.category_name }}
                                </div>
                            </div>
                            <span class="rounded-lg bg-[#e2efe4] px-2.5 py-1 text-xs font-bold text-[#19613c]">
                                Ini entitasnya
                            </span>
                        </button>
                    </div>

                    <!-- No match: register it as a new entity instead (docs/26 override) — it's
                         created now but Disabled/invisible everywhere, and only flips to a real,
                         visible listing once the payment actually confirms. -->
                    <div
                        v-if="isNewEntityMode"
                        class="mt-3 rounded-xl border border-[#e5d4b8] bg-[#fffaf0] p-3"
                    >
                        <p class="text-xs text-[#7c694e]">
                            Belum terdaftar di SuaraNetijen. Daftarkan sebagai entitas baru — akan tampil di leaderboard
                            begitu pembayaran terkonfirmasi.
                        </p>
                        <label class="mt-3 block text-xs font-bold text-[#31483b]">
                            Nama Entitas
                        </label>
                        <input
                            v-model="newEntityName"
                            type="text"
                            placeholder="Nama brand, produk, atau layanan"
                            class="mt-1.5 w-full rounded-xl border border-[#cfd9ce] py-2.5 px-4 text-sm text-[#18392d] placeholder-[#8e9f93] focus:border-[#087f5b] focus:ring-1 focus:ring-[#087f5b] focus:outline-none"
                        />
                        <label class="mt-3 block text-xs font-bold text-[#31483b]">
                            Kategori
                        </label>
                        <select
                            v-model="newEntityCategoryId"
                            class="mt-1.5 w-full rounded-xl border border-[#cfd9ce] bg-white py-2.5 px-4 text-sm text-[#18392d] focus:border-[#087f5b] focus:ring-1 focus:ring-[#087f5b] focus:outline-none"
                        >
                            <option :value="null" disabled>Pilih kategori...</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                        <label class="mt-3 block text-xs font-bold text-[#31483b]">
                            Deskripsi
                        </label>
                        <textarea
                            v-model="newEntityDescription"
                            rows="3"
                            maxlength="500"
                            placeholder="Deskripsi singkat brand, produk, atau layanan"
                            class="mt-1.5 w-full rounded-xl border border-[#cfd9ce] py-2.5 px-4 text-sm text-[#18392d] placeholder-[#8e9f93] focus:border-[#087f5b] focus:ring-1 focus:ring-[#087f5b] focus:outline-none"
                        ></textarea>
                        <p class="mt-1.5 text-[11px] text-[#788a7e]">
                            Kami isi otomatis dari deskripsi situs. Bisa Anda ubah.
                        </p>
                    </div>

                    <div
                        v-if="selectedEntity"
                        class="mt-2 flex items-center justify-between rounded-xl border border-[#bfe2ca] bg-[#f0faf2] p-3 text-xs"
                    >
                        <span class="inline-flex items-center gap-1.5 font-bold text-[#1b6b44]">
                            <CheckCircle2 class="size-4 text-[#16a34a]" />
                            Terpilih: {{ selectedEntity.name }}
                        </span>
                        <button
                            type="button"
                            class="font-semibold text-[#8b998f] hover:text-[#a73520]"
                            @click="selectedEntity = null"
                        >
                            Ganti
                        </button>
                    </div>
                </div>

                <!-- Step 2: appears once an entity is confirmed or a new one is ready to name. -->
                <template v-if="hasSponsorTarget">
                    <!-- Guest email (no account required) -->
                    <div v-if="!currentUser" class="mt-4">
                        <label class="block text-xs font-bold text-[#31483b]">
                            Email
                        </label>
                        <input
                            v-model="guestEmail"
                            type="email"
                            placeholder="nama@email.com"
                            class="mt-1.5 w-full rounded-xl border border-[#cfd9ce] py-2.5 px-4 text-sm text-[#18392d] placeholder-[#8e9f93] focus:border-[#087f5b] focus:ring-1 focus:ring-[#087f5b] focus:outline-none"
                        />
                        <p class="mt-1.5 text-[11px] text-[#788a7e]">
                            Tidak perlu akun. Kami kirim link masuk ke email ini agar Anda bisa cek status sponsor kapan saja.
                        </p>
                    </div>

                    <!-- Contribution Amount -->
                    <div class="mt-4">
                        <label class="block text-xs font-bold text-[#31483b]">
                            Nominal Sponsor (Minimal Rp{{ minAmount.toLocaleString('id-ID') }})
                        </label>

                        <!-- Presets -->
                        <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-5">
                            <button
                                v-for="preset in presetAmounts"
                                :key="preset"
                                type="button"
                                class="rounded-xl border py-2 text-xs font-bold transition"
                                :class="
                                    contributionAmount === preset
                                        ? 'border-[#d97706] bg-[#fff6e6] text-[#92400e]'
                                        : 'border-[#dce4db] bg-white text-[#45574a] hover:bg-[#f6faf5]'
                                "
                                @click="selectPreset(preset)"
                            >
                                {{ formatRupiah(preset) }}
                            </button>
                        </div>

                        <!-- Custom input -->
                        <div class="relative mt-3">
                            <span class="absolute top-2.5 left-3 text-xs font-bold text-[#687a6d]">Rp</span>
                            <input
                                v-model="customAmount"
                                type="number"
                                :min="minAmount"
                                :step="incrementAmount || 100"
                                class="w-full rounded-xl border border-[#cfd9ce] py-2.5 pr-4 pl-10 text-sm font-bold text-[#18392d] focus:border-[#087f5b] focus:ring-1 focus:ring-[#087f5b] focus:outline-none"
                                @input="handleCustomAmountChange"
                            />
                        </div>
                        <p class="mt-1 text-[11px] text-[#788a7e]">
                            Bebas tentukan nominal (minimal {{ formatRupiah(minAmount) }}, kelipatan {{ formatRupiah(incrementAmount || 100) }}). Untuk merebut posisi, cukup tambah {{ formatRupiah(incrementAmount || 100) }} di atas total sponsor target.
                        </p>

                        <!-- Quick Rebut Target Amount Button -->
                        <div v-if="rebutTarget" class="mt-2.5">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-xl border border-[#f59e0b] bg-[#fffbeb] px-3 py-1.5 text-xs font-bold text-[#92400e] shadow-2xs transition hover:bg-[#fef3c7]"
                                @click="selectPreset(rebutTarget.neededAmount)"
                            >
                                Pasang Nominal Rebut Posisi #{{ rebutTarget.rank }}: {{ formatRupiah(rebutTarget.neededAmount) }}
                            </button>
                        </div>
                    </div>

                    <!-- Rank Prediction Callout -->
                    <div
                        v-if="predictedRank"
                        class="mt-4 rounded-xl border border-[#fed7aa] bg-[#fffbf2] p-3 text-xs text-[#9a6a24]"
                    >
                        <span class="font-bold">Estimasi Posisi:</span>
                        Dengan tambahan {{ formatRupiah(contributionAmount) }}, entitas ini diperkirakan menempati posisi
                        <strong class="font-extrabold text-[#92400e]">#{{ predictedRank }}</strong> di Leaderboard!
                    </div>

                    <!-- Error message -->
                    <div
                        v-if="errorMessage"
                        class="mt-4 flex items-center gap-2 rounded-xl bg-[#fdf2f2] p-3 text-xs text-[#b91c1c]"
                    >
                        <AlertCircle class="size-4 shrink-0" />
                        <span>{{ errorMessage }}</span>
                    </div>

                    <div class="mt-4 border-t border-[#edf1eb] pt-3">
                        <button
                            type="button"
                            class="w-full rounded-full bg-[#d97706] py-3 text-sm font-bold text-white shadow-md transition hover:bg-[#b45309] disabled:opacity-50"
                            :disabled="
                                (!currentUser && !isValidGuestEmail) ||
                                (isNewEntityMode && (!newEntityName.trim() || !newEntityCategoryId))
                            "
                            @click="openConfirmModal()"
                        >
                            Lanjut ke Pembayaran QRIS ({{ formatRupiah(contributionAmount) }})
                        </button>
                    </div>
                </template>
            </div>

            <!-- Leaderboard search (rankup.uno-style): filters the board below, never affects
                 the sponsor entry form above or organic search. -->
            <div v-if="leaderboard.length > 0" class="relative mt-8 w-full">
                <Search class="absolute top-3 left-3 size-4 text-[#8e9f93]" />
                <input
                    v-model="leaderboardQuery"
                    type="search"
                    placeholder="Cari di leaderboard..."
                    class="w-full rounded-xl border border-[#d7e0d5] bg-white py-2.5 pr-4 pl-9 text-sm text-[#18392d] placeholder-[#8e9f93] focus:border-[#087f5b] focus:ring-1 focus:ring-[#087f5b] focus:outline-none"
                />
            </div>

            <!-- Empty state -->
            <div
                v-if="leaderboard.length === 0"
                class="mt-10 rounded-3xl border border-dashed border-[#dce3db] bg-white p-12 text-center"
            >
                <div class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-[#fdf8ee] text-[#d97706]">
                    <Trophy class="size-8" />
                </div>
                <h3 class="mt-4 text-lg font-bold text-[#1f3729]">
                    Belum ada entitas di leaderboard periode ini
                </h3>
                <p class="mx-auto mt-2 max-w-md text-sm text-[#6c7d70]">
                    Jadilah yang pertama mengangkat brand, produk, atau layanan pilihanmu ke posisi teratas Leaderboard periode ini!
                </p>
            </div>

            <div
                v-else-if="filteredLeaderboard.length === 0"
                class="mt-6 rounded-2xl border border-dashed border-[#dce3db] bg-white p-8 text-center text-sm text-[#6c7d70]"
            >
                Tidak ada entitas di leaderboard yang cocok dengan "{{ leaderboardQuery }}".
            </div>

            <!-- Leaderboard Content -->
            <div v-else class="mt-5 space-y-2">
                <template v-for="(section, sectionIndex) in leaderboardSections" :key="section.key">
                    <div
                        class="overflow-hidden rounded-2xl border shadow-sm"
                        :class="sectionIndex === 0 ? 'border-[#cfe3d2] bg-[#f7fbf7]' : 'border-[#dfe5dc] bg-white'"
                    >
                    <div
                        class="border-b px-4 py-3"
                        :class="sectionIndex === 0 ? 'border-[#cfe3d2] bg-[#edf6ee]' : 'border-[#dfe5dc] bg-[#f8faf7]'"
                    >
                        <h3 class="text-sm font-bold text-[#18392d]">
                            {{ section.title }}
                        </h3>
                    </div>
                    <div class="divide-y divide-[#edf1ec]">
                        <div
                            v-for="entry in section.entries"
                            :key="entry.id"
                            class="flex flex-col gap-2 p-2.5 transition sm:flex-row sm:items-center sm:justify-between sm:gap-3 hover:bg-[#fafcfa]"
                        >
                            <div class="flex min-w-0 items-center gap-2.5">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-md bg-[#f0f4ef] text-xs font-black text-[#45574a]">
                                    #{{ entry.rank }}
                                </span>
                                <img
                                    v-if="getFaviconUrl(entry.website_url)"
                                    :src="getFaviconUrl(entry.website_url)!"
                                    :alt="entry.name"
                                    class="size-6 shrink-0 rounded-md border border-black/10 bg-white object-contain p-0.5"
                                    loading="lazy"
                                    @error="(e) => ((e.target as HTMLElement).style.display = 'none')"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <Link
                                            :href="showEntity(entry.slug)"
                                            class="truncate text-sm font-bold text-[#18392d] hover:text-[#087f5b]"
                                        >
                                            {{ entry.name }}
                                        </Link>
                                        <span class="rounded-md bg-neutral-100 px-2 py-0.5 text-[10px] font-medium text-neutral-600">
                                            {{ entry.category_name }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="entry.description"
                                        class="mt-0.5 truncate text-[11px] leading-4 text-[#5b6e61]"
                                    >
                                        {{ entry.description }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5 border-t border-[#edf1ec] pt-1.5 sm:border-0 sm:pt-0 sm:justify-end">
                                <!-- Stats: Sentimen, Views, Clicks (Visible on Mobile & Desktop) -->
                                <div class="flex items-center gap-2 text-[10px] text-[#55695a] sm:text-[11px]">
                                    <span
                                        v-if="typeof entry.sentiment_score === 'number'"
                                        class="inline-flex items-center gap-0.5 font-bold text-[#1b6b44]"
                                        title="Sentimen Netijen"
                                    >
                                        Sentimen {{ entry.sentiment_score.toLocaleString('id-ID', { maximumFractionDigits: 1 }) }}
                                    </span>
                                    <span v-else class="text-[#718274]">Tanpa skor</span>

                                    <span>•</span>

                                    <span class="inline-flex items-center gap-1" title="Kunjungan detail">
                                        <Eye class="size-3 text-[#2563eb]" />
                                        {{ (entry.views_count || 0).toLocaleString('id-ID') }}
                                    </span>

                                    <span>•</span>

                                    <span class="inline-flex items-center gap-1" title="Klik direct URL">
                                        <MousePointerClick class="size-3 text-[#087f5b]" />
                                        {{ (entry.clicks_count || 0).toLocaleString('id-ID') }} klik
                                    </span>
                                </div>

                                <!-- Total Sponsor -->
                                <div class="text-right">
                                    <span class="text-xs font-black text-[#92400e] sm:text-sm">
                                        {{ formatRupiah(entry.settled_total_amount) }}
                                    </span>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-1.5">
                                    <a
                                        :href="getDirectWebsiteUrl(entry.website_url, entry.slug, { placement: 'leaderboard_row' })"
                                        :ping="`/api/sponsor/click/${entry.slug}`"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex min-h-8 items-center justify-center rounded-lg bg-[#eaf7ee] px-2.5 py-1 text-xs font-bold text-[#145736] transition hover:bg-[#bceccb]"
                                        @click="trackSponsorClick(entry.slug, { placement: 'leaderboard_row', url: entry.website_url || undefined })"
                                    >
                                        Buka Situs
                                    </a>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-8 items-center justify-center rounded-lg border border-[#d8e3d6] bg-white px-2.5 py-1 text-xs font-bold text-[#18392d] transition hover:border-[#8cb896] hover:bg-[#f0f7f0]"
                                        @click="selectEntityAndScrollToForm({ id: entry.entity_id, name: entry.name, slug: entry.slug })"
                                    >
                                        + Sponsori
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-8 items-center justify-center rounded-lg border border-[#f59e0b] bg-[#fffbeb] px-2.5 py-1 text-xs font-bold text-[#92400e] transition hover:bg-[#fef3c7]"
                                        title="Rebut posisi ini"
                                        @click="startRebut(entry)"
                                    >
                                        Rebut
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>

                    <!-- Stats separate the core ranking from the rest of the loaded entries. -->
                    <div v-if="sectionIndex === 0 && stats" class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-5">
                        <div class="rounded-2xl border border-[#e5e9e2] bg-white p-3 shadow-2xs">
                            <div class="flex items-center gap-1.5 text-[11px] font-semibold text-[#66776b]">
                                <Trophy class="size-3.5 text-[#d97706]" /> Total Listing
                            </div>
                            <div class="mt-1 text-lg font-black text-[#18392d]">
                                {{ stats.total_listings }}
                            </div>
                            <div class="mt-0.5 text-[10px] text-[#8e9f93]">entitas aktif</div>
                        </div>

                        <div class="rounded-2xl border border-[#f2ddb3] bg-[#fffefb] p-3 shadow-2xs">
                            <div class="flex items-center gap-1.5 text-[11px] font-semibold text-[#9a6a24]">
                                <Award class="size-3.5 text-[#d97706]" /> Total Sponsor
                            </div>
                            <div class="mt-1 text-lg font-black text-[#92400e]">
                                {{ formatRupiah(stats.total_amount) }}
                            </div>
                            <div class="mt-0.5 text-[10px] text-[#b45309]">terkonfirmasi</div>
                        </div>

                        <div class="rounded-2xl border border-[#e5e9e2] bg-white p-3 shadow-2xs">
                            <div class="flex items-center gap-1.5 text-[11px] font-semibold text-[#66776b]">
                                <MousePointerClick class="size-3.5 text-[#087f5b]" /> Total Klik URL
                            </div>
                            <div class="mt-1 text-lg font-black text-[#18392d]">
                                {{ stats.total_clicks.toLocaleString('id-ID') }}
                            </div>
                            <div class="mt-0.5 text-[10px] text-[#8e9f93]">kunjungan direct link</div>
                        </div>

                        <div class="rounded-2xl border border-[#e5e9e2] bg-white p-3 shadow-2xs">
                            <div class="flex items-center gap-1.5 text-[11px] font-semibold text-[#66776b]">
                                <Eye class="size-3.5 text-[#2563eb]" /> Total Pengunjung
                            </div>
                            <div class="mt-1 text-lg font-black text-[#18392d]">
                                {{ stats.total_views.toLocaleString('id-ID') }}
                            </div>
                            <div class="mt-0.5 text-[10px] text-[#8e9f93]">tampilan halaman detail</div>
                        </div>

                        <div class="col-span-2 rounded-2xl border border-[#e5e9e2] bg-white p-3 shadow-2xs sm:col-span-1">
                            <div class="flex items-center gap-1.5 text-[11px] font-semibold text-[#66776b]">
                                <Award class="size-3.5 text-[#d97706]" /> Peringkat #1 Saat Ini
                            </div>
                            <div class="mt-1 text-lg font-black text-[#18392d]">
                                {{ formatRupiah(stats.highest_bid) }}
                            </div>
                            <div class="mt-0.5 text-[10px] text-[#8e9f93]">sponsor tertinggi</div>
                        </div>
                    </div>
                </template>

                <div v-if="canLoadMoreLeaderboard" class="mt-4 flex justify-center">
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center justify-center rounded-full border border-[#cfdccc] bg-white px-5 py-2 text-xs font-bold text-[#24543a] shadow-xs transition hover:border-[#8cb896] hover:bg-[#f0f7f0]"
                        @click="loadMoreLeaderboard"
                    >
                        Muat 25 peringkat lagi
                    </button>
                </div>
            </div>


        </div>

        <!-- Payment confirmation modal — the ONLY modal in this flow; everything gathering
             input above is plain page content. -->
        <div
            v-if="isConfirmModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        >
            <div class="relative w-full max-w-md rounded-3xl border border-[#e5e9e2] bg-white p-6 shadow-2xl sm:p-8">
                <button
                    type="button"
                    class="absolute top-5 right-5 flex size-9 items-center justify-center rounded-full text-[#7a8a7f] hover:bg-[#f2f6f1] hover:text-[#18392d]"
                    @click="closeConfirmModal()"
                >
                    <X class="size-5" />
                </button>

                <div class="flex items-center gap-2 text-xs font-bold text-[#d97706]">
                    <Trophy class="size-4" />
                    KONFIRMASI PEMBAYARAN
                </div>
                <h3 class="mt-1 text-xl font-extrabold text-[#18392d]">
                    Ringkasan Pesanan Sponsor
                </h3>

                <div class="mt-5 space-y-3 rounded-2xl border border-[#e5e9e2] bg-[#f8faf7] p-4 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-[#637568]">Entitas</span>
                        <span class="font-bold text-[#18392d]">{{ selectedEntity?.name || newEntityName }}</span>
                    </div>
                    <div v-if="isNewEntityMode" class="flex items-center justify-between">
                        <span class="text-[#637568]">Status</span>
                        <span class="font-bold text-[#92400e]">Entitas baru — aktif setelah bayar</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[#637568]">Nominal</span>
                        <span class="font-bold text-[#92400e]">{{ formatRupiah(contributionAmount) }}</span>
                    </div>
                    <div v-if="!currentUser" class="flex items-center justify-between">
                        <span class="text-[#637568]">Email</span>
                        <span class="font-bold text-[#18392d]">{{ guestEmail }}</span>
                    </div>
                    <div v-if="predictedRank" class="flex items-center justify-between">
                        <span class="text-[#637568]">Estimasi Posisi</span>
                        <span class="font-bold text-[#92400e]">#{{ predictedRank }}</span>
                    </div>
                </div>

                <div
                    v-if="errorMessage"
                    class="mt-4 flex items-center gap-2 rounded-xl bg-[#fdf2f2] p-3 text-xs text-[#b91c1c]"
                >
                    <AlertCircle class="size-4 shrink-0" />
                    <span>{{ errorMessage }}</span>
                </div>

                <div class="mt-6">
                    <button
                        type="button"
                        class="w-full rounded-full bg-[#d97706] py-3 text-sm font-bold text-white shadow-md transition hover:bg-[#b45309] disabled:opacity-50"
                        :disabled="isSubmitting"
                        @click="submitOrder()"
                    >
                        <span v-if="isSubmitting">Memproses ke QRIS...</span>
                        <span v-else>Bayar Sekarang</span>
                    </button>
                    <p class="mt-2 text-center text-[11px] text-[#788a7e]">
                        Pembayaran diproses aman melalui QRIS. Biaya gateway QRIS ditanggung pembeli.
                    </p>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
