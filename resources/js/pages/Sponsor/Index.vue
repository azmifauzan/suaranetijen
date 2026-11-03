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
import {
    getDirectWebsiteUrl,
    getFaviconUrl,
    trackSponsorClick,
} from '@/lib/sponsor';
import { methodology, terms } from '@/routes';
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

interface SponsorEntitySelection {
    id: number;
    name: string;
    type_label: string;
    category_name: string;
    website_url: string | null;
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
            entry.name.toLowerCase().includes(q) ||
            entry.category_name.toLowerCase().includes(q),
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
    const lastRemainingRank =
        remainingEntries[remainingEntries.length - 1]?.rank;

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
const urlPreview = ref<{
    title: string;
    url: string;
    description: string | null;
} | null>(null);
// The normalized URL the last successful check ran against; null until "Cek situs" succeeds.
const checkedUrl = ref<string | null>(null);
const fetchError = ref<string | null>(null);
// Title-search hits, shown only as optional "already registered?" suggestions.
const candidates = ref<SponsorEntitySelection[]>([]);
const selectedEntity = ref<SponsorEntitySelection | null>(null);
// 'existing' = sponsoring a registered entity; 'new' = registering one (created Disabled now, made
// visible only once payment confirms — docs/26; see ProcessSponsorshipRelayWebhook).
const entityMode = ref<'existing' | 'new' | null>(null);
const newEntityName = ref('');
const newEntityCategoryId = ref<number | null>(null);
// Pre-filled from the fetched page's meta description, still editable.
const newEntityDescription = ref('');
const newEntityType = ref<'brand' | 'product' | 'service'>('brand');
const isNewEntityMode = computed(
    () => entityMode.value === 'new' && !selectedEntity.value,
);
const hasSponsorTarget = computed(
    () => !!selectedEntity.value || isNewEntityMode.value,
);
const contributionAmount = ref<number>(10000);
const customAmount = ref<string>('10000');
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);
const guestEmail = ref<string>('');
const isConfirmModalOpen = ref(false);

const isValidGuestEmail = computed(() =>
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(guestEmail.value.trim()),
);

interface RebutTarget {
    rank: number;
    name: string;
    entityId?: number;
    targetAmount: number;
    neededAmount: number;
}

const rebutTarget = ref<RebutTarget | null>(null);

function startRebut(entry: {
    rank: number;
    name: string;
    settled_total_amount: number;
    entity_id?: number;
}) {
    const targetAmount = entry.settled_total_amount;
    const increment = props.incrementAmount || 100;
    let needed = Math.max(props.minAmount, targetAmount + increment);

    if (selectedEntity.value) {
        const existingEntry = props.leaderboard.find(
            (e) => e.entity_id === selectedEntity.value?.id,
        );
        if (existingEntry) {
            needed = Math.max(
                props.minAmount,
                targetAmount + increment - existingEntry.settled_total_amount,
            );
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
    const currentEntry = props.leaderboard.find(
        (e) => e.entity_id === entityId,
    );
    const newTotal =
        (currentEntry?.settled_total_amount ?? 0) + contributionAmount.value;

    let higherCount = 0;
    for (const item of props.leaderboard) {
        if (item.entity_id === entityId) continue;
        if (item.settled_total_amount > newTotal) {
            higherCount++;
        } else if (
            item.settled_total_amount === newTotal &&
            currentEntry?.first_settled_at &&
            item.first_settled_at &&
            item.first_settled_at < currentEntry.first_settled_at
        ) {
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

    const withScheme = /^https?:\/\//i.test(trimmed)
        ? trimmed
        : `https://${trimmed}`;
    try {
        const parsed = new URL(withScheme);
        return parsed.protocol === 'http:' || parsed.protocol === 'https:'
            ? withScheme
            : null;
    } catch {
        return null;
    }
}

function resetEntityStep() {
    urlPreview.value = null;
    checkedUrl.value = null;
    fetchError.value = null;
    candidates.value = [];
    previewError.value = null;
    selectedEntity.value = null;
    entityMode.value = null;
    newEntityName.value = '';
    newEntityCategoryId.value = null;
    newEntityDescription.value = '';
}

// Editing the URL invalidates the previous check; nothing is fetched until the user asks.
watch(urlInput, (value) => {
    if (checkedUrl.value && normalizeUrlInput(value) !== checkedUrl.value) {
        resetEntityStep();
    }
    previewError.value = null;
});

async function checkUrl() {
    const normalized = normalizeUrlInput(urlInput.value);
    if (!normalized) {
        previewError.value = 'Tulis alamat situs, contoh: namabrand.com';
        return;
    }

    resetEntityStep();
    isFetchingPreview.value = true;
    try {
        const csrfToken = (
            document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement
        )?.content;
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
            previewError.value =
                json.error || json.message || 'Alamat situs tidak valid.';
            return;
        }

        checkedUrl.value = normalized;
        urlPreview.value = json.preview;
        fetchError.value = json.fetch_error;
        // Kept even on a domain match, so "Bukan ini, daftarkan baru" starts pre-filled.
        newEntityName.value = json.suggested_name || '';
        newEntityDescription.value = json.preview?.description || '';

        if (json.domain_match) {
            selectedEntity.value = json.domain_match;
            entityMode.value = 'existing';
            return;
        }

        candidates.value = (json.candidates || [])
            .slice(0, 3)
            .map((item: any) => ({
                id: item.id,
                name: item.name,
                category_name:
                    item.category_name || item.category?.name || 'Umum',
                type_label: item.type_label || 'Entitas',
                website_url: item.website_url || null,
            }));
        entityMode.value = 'new';
    } catch {
        previewError.value = 'Koneksi bermasalah. Coba lagi.';
    } finally {
        isFetchingPreview.value = false;
    }
}

function chooseCandidate(item: SponsorEntitySelection) {
    selectedEntity.value = item;
    entityMode.value = 'existing';
}

function registerAsNewEntity() {
    selectedEntity.value = null;
    entityMode.value = 'new';
}

function changeEntity() {
    urlInput.value = '';
    resetEntityStep();
}

watch(selectedEntity, (newEntity) => {
    if (rebutTarget.value) {
        const targetAmount = rebutTarget.value.targetAmount;
        const increment = props.incrementAmount || 100;
        let needed = Math.max(props.minAmount, targetAmount + increment);
        if (newEntity) {
            const existingEntry = props.leaderboard.find(
                (e) => e.entity_id === newEntity.id,
            );
            if (existingEntry) {
                needed = Math.max(
                    props.minAmount,
                    targetAmount +
                        increment -
                        existingEntry.settled_total_amount,
                );
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

    // Entry from an entity page: "?url=<its website>" pre-fills and checks it, so the domain match
    // selects that entity straight away.
    // From the user panel's "Tambah usulan" sponsor path: name/category/type/description arrive too
    // and override the site-derived guesses for a new entity.
    const prefillUrl = params.get('url');
    const prefillType = params.get('type');
    if (
        prefillType === 'brand' ||
        prefillType === 'product' ||
        prefillType === 'service'
    ) {
        newEntityType.value = prefillType;
    }
    if (prefillUrl) {
        urlInput.value = prefillUrl;
        checkUrl().then(() => {
            if (entityMode.value !== 'new') return;
            newEntityName.value = params.get('name') || newEntityName.value;
            newEntityDescription.value =
                params.get('description') || newEntityDescription.value;
            const categoryId = Number(params.get('category_id'));
            if (props.categories.some((cat) => cat.id === categoryId)) {
                newEntityCategoryId.value = categoryId;
            }
        });
        setTimeout(
            () =>
                formSection.value?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                }),
            150,
        );
    }

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
                formSection.value?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            }, 150);
        }
    }
});

// Used by "+ Sponsori" buttons on already-listed entities: skip the URL step entirely and jump
// straight to step 2, scrolling the (always-on-page) form into view.
function selectEntityAndScrollToForm(entry: LeaderboardEntry) {
    urlInput.value = '';
    resetEntityStep();
    selectedEntity.value = {
        id: entry.entity_id,
        name: entry.name,
        type_label: entry.type_label,
        category_name: entry.category_name,
        website_url: entry.website_url || null,
    };
    entityMode.value = 'existing';
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
        const csrfToken = (
            document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement
        )?.content;
        const response = await fetch('/api/sponsor/orders', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
            },
            body: JSON.stringify({
                amount: contributionAmount.value,
                // No redirect_url: the backend builds the correct post-payment URL itself
                // (with the real order id, known only after creation) as its own default.
                ...(currentUser.value
                    ? {}
                    : { email: guestEmail.value.trim() }),
                ...(selectedEntity.value
                    ? {
                          entity_id: selectedEntity.value.id,
                          website_url:
                              urlPreview.value?.url ||
                              checkedUrl.value ||
                              undefined,
                      }
                    : {
                          new_entity_name: newEntityName.value.trim(),
                          new_entity_category_id: newEntityCategoryId.value,
                          new_entity_url:
                              urlPreview.value?.url || checkedUrl.value,
                          new_entity_description:
                              newEntityDescription.value.trim() || null,
                          new_entity_type: newEntityType.value,
                      }),
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            errorMessage.value =
                data.message ||
                data.error ||
                'Gagal memproses pesanan sponsor.';
            isConfirmModalOpen.value = false;
            return;
        }

        if (data.data?.payment_link_url) {
            window.location.href = data.data.payment_link_url;
        } else {
            router.visit(paymentStatus({ query: { order_id: data.data?.id } }));
        }
    } catch (e: any) {
        errorMessage.value =
            e.message || 'Terjadi kesalahan saat memproses pesanan.';
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
                        ? 'border-brand-tint-strong bg-paper'
                        : 'border-paid-line bg-paid-tint'
                "
            >
                <div class="flex items-start gap-4">
                    <CheckCircle2
                        v-if="userOrder.status === 'paid'"
                        class="text-score-high size-6 shrink-0"
                    />
                    <Clock v-else class="text-paid size-6 shrink-0" />
                    <div class="flex-1">
                        <h4
                            class="text-sm font-bold sm:text-base"
                            :class="
                                userOrder.status === 'paid'
                                    ? 'text-brand-deep'
                                    : 'text-paid'
                            "
                        >
                            {{
                                userOrder.status === 'paid'
                                    ? 'Pembayaran Berhasil Dikonfirmasi!'
                                    : 'Pesanan Sponsor Sedang Diproses'
                            }}
                        </h4>
                        <p class="text-ink-muted mt-1 text-xs sm:text-sm">
                            Sponsor untuk
                            <span class="text-ink font-semibold">{{
                                userOrder.entity_name
                            }}</span>
                            sebesar
                            <span class="text-ink font-semibold">{{
                                formatRupiah(userOrder.amount)
                            }}</span>
                            ({{ userOrder.status_label }}).
                            <span v-if="userOrder.status === 'paid'">
                                Kontribusi telah terakumulasi pada periode ini.
                            </span>
                            <span v-else>
                                Jika belum membayar, Anda dapat melanjutkan
                                pembayaran melalui QRIS.
                            </span>
                        </p>
                        <div
                            v-if="
                                userOrder.status === 'pending' &&
                                userOrder.payment_link_url
                            "
                            class="mt-3"
                        >
                            <a
                                :href="userOrder.payment_link_url"
                                class="bg-paid-strong hover:bg-paid-deep inline-flex min-h-11 items-center justify-center rounded-lg px-4 py-2.5 text-xs font-semibold text-white transition"
                            >
                                Bayar Sekarang via QRIS
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Header -->
            <div
                class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="text-ink text-2xl font-black tracking-tight sm:text-3xl"
                    >
                        Papan Sponsor
                    </h1>
                    <p class="text-ink-muted mt-1 max-w-xl text-sm">
                        Tempat brand, produk, atau layanan tampil lebih
                        menonjol. Siapa pun bisa memasang, mulai
                        {{ formatRupiah(minAmount) }}.
                    </p>
                </div>
                <a
                    href="#formSection"
                    class="bg-paid-strong hover:bg-paid-deep inline-flex min-h-11 shrink-0 items-center justify-center rounded-full px-5 text-sm font-bold text-white transition"
                    @click.prevent="
                        formSection?.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start',
                        })
                    "
                >
                    Pasang sponsor
                </a>
            </div>

            <!-- Top 3 sits first so the competitive target is visible before the sponsor form. -->
            <div class="space-y-2">
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                >
                    <h2 class="text-ink text-sm font-bold">
                        Posisi #1 sampai #3
                    </h2>

                    <!-- Period Switcher -->
                    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                        <span
                            class="mr-1 text-xs font-semibold text-neutral-500"
                        >
                            Periode:
                        </span>
                        <Link
                            v-for="p in periods"
                            :key="p.id"
                            :href="
                                leaderboardPage({ query: { period: p.key } })
                            "
                            class="inline-flex min-h-11 items-center rounded-full px-3.5 py-2.5 text-xs font-semibold transition"
                            :class="
                                selectedPeriod.key === p.key
                                    ? 'bg-ink text-white shadow-sm'
                                    : 'border-line text-ink-muted hover:border-line-strong border bg-white'
                            "
                        >
                            {{ p.name }}
                            <span
                                v-if="p.is_active"
                                class="bg-score-high ml-1 inline-block size-1.5 rounded-full"
                            ></span>
                        </Link>
                    </div>
                </div>
                <p class="text-xs text-neutral-500">
                    Urutan berdasarkan nominal sponsor terkonfirmasi, bukan
                    Sentimen Netijen.
                </p>

                <div
                    v-if="
                        leaderboard.length > 0 && filteredLeaderboard.length > 0
                    "
                    class="flex flex-col gap-2"
                >
                    <div
                        v-for="entry in filteredLeaderboard.slice(0, 3)"
                        :key="entry.id"
                        class="hover:border-line-strong flex flex-col gap-2 rounded-xl border bg-white px-3 py-2 transition sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                        :class="
                            entry.rank === 1
                                ? 'border-paid-line bg-canvas'
                                : entry.rank === 2
                                  ? 'border-line bg-white'
                                  : 'border-line bg-white'
                        "
                    >
                        <div class="flex min-w-0 items-center gap-2.5">
                            <span
                                class="inline-flex size-6 shrink-0 items-center justify-center rounded-md text-xs font-black"
                                :class="
                                    entry.rank === 1
                                        ? 'bg-paid-soft text-paid border-paid-line border'
                                        : entry.rank === 2
                                          ? 'bg-line-soft text-ink-muted border-line-strong border'
                                          : 'bg-paid-soft text-paid-strong border-paid-line border'
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
                                @error="
                                    (e) =>
                                        ((
                                            e.target as HTMLElement
                                        ).style.display = 'none')
                                "
                            />
                            <div class="min-w-0 flex-1">
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <Link
                                        :href="showEntity(entry.slug)"
                                        class="text-ink hover:text-brand truncate text-sm font-bold"
                                    >
                                        {{ entry.name }}
                                    </Link>
                                    <span
                                        class="text-ink-muted hidden truncate rounded-md bg-black/5 px-2 py-0.5 text-[10px] font-semibold sm:inline"
                                    >
                                        {{ entry.type_label }}
                                    </span>
                                </div>
                                <p
                                    class="truncate text-[11px] leading-4 text-neutral-500"
                                >
                                    {{ entry.category_name
                                    }}<span
                                        v-if="entry.description"
                                        class="hidden lg:inline"
                                        >, {{ entry.description }}</span
                                    >
                                </p>
                            </div>
                        </div>

                        <div
                            class="border-line-soft flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5 border-t pt-1.5 sm:justify-end sm:border-0 sm:pt-0"
                        >
                            <!-- Stats: Sentimen, Views, Clicks (Visible on Mobile & Desktop) -->
                            <div
                                class="text-ink-muted flex items-center gap-2 text-[10px] sm:text-[11px]"
                            >
                                <span
                                    v-if="
                                        typeof entry.sentiment_score ===
                                        'number'
                                    "
                                    class="text-brand-strong inline-flex items-center gap-0.5 font-bold"
                                    title="Sentimen Netijen"
                                >
                                    Sentimen
                                    {{
                                        entry.sentiment_score.toLocaleString(
                                            'id-ID',
                                            { maximumFractionDigits: 1 },
                                        )
                                    }}
                                </span>
                                <span v-else class="text-neutral-500"
                                    >Tanpa skor</span
                                >

                                <span
                                    class="inline-flex items-center gap-1"
                                    title="Kunjungan detail"
                                >
                                    <Eye class="text-info size-3" />
                                    {{
                                        (entry.views_count || 0).toLocaleString(
                                            'id-ID',
                                        )
                                    }}
                                </span>

                                <span
                                    class="inline-flex items-center gap-1"
                                    title="Klik website"
                                >
                                    <MousePointerClick
                                        class="text-brand size-3"
                                    />
                                    {{
                                        (
                                            entry.clicks_count || 0
                                        ).toLocaleString('id-ID')
                                    }}
                                    klik
                                </span>
                            </div>

                            <!-- Total Sponsor -->
                            <div class="text-right">
                                <span
                                    class="text-paid text-xs font-black sm:text-sm"
                                >
                                    {{
                                        formatRupiah(entry.settled_total_amount)
                                    }}
                                </span>
                            </div>

                            <!-- Actions -->
                            <div
                                class="flex max-w-full flex-wrap items-center justify-end gap-1.5"
                            >
                                <a
                                    :href="
                                        getDirectWebsiteUrl(
                                            entry.website_url,
                                            entry.slug,
                                            { placement: 'leaderboard_top3' },
                                        )
                                    "
                                    :ping="`/api/sponsor/click/${entry.slug}`"
                                    target="_blank"
                                    rel="noopener"
                                    class="bg-sage text-brand-deep hover:bg-brand-tint-strong inline-flex min-h-11 items-center justify-center rounded-lg px-2.5 py-2.5 text-xs font-bold transition"
                                    @click="
                                        trackSponsorClick(entry.slug, {
                                            placement: 'leaderboard_top3',
                                            url: entry.website_url || undefined,
                                        })
                                    "
                                >
                                    Buka Situs
                                </a>
                                <button
                                    type="button"
                                    class="border-line text-brand-deep hover:border-line-strong hover:bg-sage inline-flex min-h-11 items-center justify-center rounded-lg border bg-white px-2.5 py-2.5 text-xs font-bold transition"
                                    @click="selectEntityAndScrollToForm(entry)"
                                >
                                    + Sponsor
                                </button>
                                <button
                                    type="button"
                                    class="border-star bg-paid-tint text-paid hover:bg-paid-soft inline-flex min-h-11 items-center justify-center rounded-lg border px-2.5 py-2.5 text-xs font-bold transition"
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
                    class="border-line bg-canvas text-ink-muted rounded-xl border border-dashed p-4 text-center text-xs"
                >
                    Belum ada entitas di Top 3 untuk periode ini.
                </div>
            </div>

            <!-- Sponsor Entry Form — inline on the page, not a popup (Pamerin/RankUp both keep
                 this as a plain section; only the final payment confirmation below is a modal). -->
            <div
                id="formSection"
                ref="formSection"
                class="border-line mt-5 scroll-mt-4 rounded-2xl border bg-white p-4 shadow-sm sm:p-5"
            >
                <h2 class="text-ink text-lg font-extrabold">
                    Pasang brand di Papan Sponsor
                </h2>
                <p class="text-ink-muted mt-1 text-xs">
                    Bayar via QRIS, tanpa perlu akun. Berlaku untuk periode
                    {{ activePeriod.name }}.
                </p>

                <!-- Mode Rebut Posisi Banner -->
                <div
                    v-if="rebutTarget"
                    class="border-star bg-paid-tint text-paid mt-4 flex flex-col gap-3 rounded-2xl border p-3 shadow-2xs sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="bg-star flex size-10 shrink-0 items-center justify-center rounded-xl text-white shadow-xs"
                        >
                            <Trophy class="size-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="bg-star rounded-full px-2.5 py-0.5 text-[10px] font-black text-white"
                                >
                                    Mode Rebut Posisi
                                </span>
                                <span class="text-paid-deep text-sm font-bold">
                                    Target: Posisi #{{ rebutTarget.rank }} ({{
                                        rebutTarget.name
                                    }})
                                </span>
                            </div>
                            <p class="text-paid mt-1 text-xs">
                                Sponsori minimal
                                <strong class="text-paid-deep font-extrabold">{{
                                    formatRupiah(rebutTarget.neededAmount)
                                }}</strong>
                                untuk langsung menggeser posisi
                                <strong>{{ rebutTarget.name }}</strong> di
                                leaderboard periode ini!
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            type="button"
                            class="border-star/30 text-paid inline-flex min-h-11 items-center rounded-xl border bg-white/90 px-3 py-2.5 text-xs font-bold transition hover:bg-white"
                            @click="cancelRebut()"
                        >
                            Batal Rebut
                        </button>
                    </div>
                </div>

                <ol class="mt-5 space-y-5">
                    <!-- Langkah 1: alamat situs. Disembunyikan bila entitas dipilih dari papan. -->
                    <li v-if="!(entityMode === 'existing' && !checkedUrl)">
                        <p
                            class="text-ink flex items-center gap-2 text-sm font-bold"
                        >
                            <span
                                class="bg-ink inline-flex size-6 items-center justify-center rounded-full text-xs text-white"
                                >1</span
                            >
                            Alamat situs resmi
                        </p>
                        <form
                            class="mt-2 flex flex-col gap-2 sm:flex-row"
                            @submit.prevent="checkUrl()"
                        >
                            <label for="sponsor-url" class="sr-only"
                                >Alamat situs resmi</label
                            >
                            <div class="relative flex-1">
                                <Globe
                                    class="text-ink-muted absolute top-3.5 left-3 size-4"
                                />
                                <input
                                    id="sponsor-url"
                                    v-model="urlInput"
                                    type="text"
                                    inputmode="url"
                                    autocomplete="url"
                                    placeholder="namabrand.com"
                                    class="border-line-strong text-ink placeholder-paid-muted focus:border-brand focus:ring-brand min-h-11 w-full rounded-xl border py-2.5 pr-4 pl-9 text-base focus:ring-1 focus:outline-none sm:text-sm"
                                />
                            </div>
                            <button
                                type="submit"
                                class="bg-ink hover:bg-ink inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-5 text-sm font-bold text-white transition disabled:opacity-60"
                                :disabled="isFetchingPreview"
                            >
                                <span
                                    v-if="isFetchingPreview"
                                    class="size-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white motion-reduce:animate-none"
                                />
                                {{
                                    isFetchingPreview
                                        ? 'Mengecek...'
                                        : 'Cek situs'
                                }}
                            </button>
                        </form>
                        <p
                            v-if="!checkedUrl && !previewError"
                            class="text-ink-muted mt-1.5 text-xs"
                        >
                            Kami cek apakah brand ini sudah terdaftar di
                            SuaraNetijen.
                        </p>
                        <p
                            v-if="previewError"
                            role="alert"
                            class="text-danger mt-2 text-xs font-semibold"
                        >
                            {{ previewError }}
                        </p>
                        <p
                            v-if="urlPreview"
                            class="text-brand-strong mt-2 flex items-center gap-1.5 text-xs"
                        >
                            <CheckCircle2 class="size-4 shrink-0" />
                            <span class="truncate"
                                >Situs ditemukan:
                                <strong>{{ urlPreview.title }}</strong></span
                            >
                        </p>
                        <p
                            v-else-if="checkedUrl && fetchError"
                            class="text-paid mt-2 text-xs"
                        >
                            Situs ini tidak bisa kami buka otomatis. Tidak
                            masalah, isi namanya di langkah 2.
                        </p>
                    </li>

                    <!-- Langkah 2: entitas -->
                    <li v-if="entityMode">
                        <p
                            class="text-ink flex items-center gap-2 text-sm font-bold"
                        >
                            <span
                                class="bg-ink inline-flex size-6 items-center justify-center rounded-full text-xs text-white"
                                >{{ checkedUrl ? 2 : 1 }}</span
                            >
                            {{
                                isNewEntityMode
                                    ? 'Daftarkan brand ini'
                                    : 'Brand yang disponsori'
                            }}
                        </p>

                        <div
                            v-if="selectedEntity"
                            class="border-brand-tint-strong bg-paper mt-2 rounded-xl border p-3 text-sm"
                        >
                            <p class="text-brand-strong text-xs">
                                {{
                                    checkedUrl
                                        ? 'Sudah terdaftar di SuaraNetijen'
                                        : 'Dipilih dari papan'
                                }}
                            </p>
                            <p class="text-ink mt-0.5 font-bold">
                                {{ selectedEntity.name }}
                            </p>
                            <p class="text-ink-muted text-xs">
                                {{ selectedEntity.type_label }},
                                {{ selectedEntity.category_name }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-x-4">
                                <button
                                    v-if="checkedUrl"
                                    type="button"
                                    class="text-brand inline-flex min-h-11 items-center text-xs font-semibold hover:underline"
                                    @click="registerAsNewEntity()"
                                >
                                    Bukan ini? Daftarkan sebagai brand baru
                                </button>
                                <button
                                    type="button"
                                    class="text-ink hover:text-paid-strong inline-flex min-h-11 items-center text-xs font-semibold"
                                    @click="changeEntity()"
                                >
                                    Ganti situs
                                </button>
                            </div>
                        </div>

                        <div
                            v-else
                            class="border-paid-line bg-paid-tint mt-2 rounded-xl border p-3"
                        >
                            <p class="text-paid-muted text-xs">
                                Brand ini belum ada di SuaraNetijen. Isi
                                datanya, lalu brand tampil di papan setelah
                                pembayaran terkonfirmasi.
                            </p>
                            <label
                                for="sponsor-new-name"
                                class="text-ink mt-3 block text-xs font-bold"
                                >Nama brand</label
                            >
                            <input
                                id="sponsor-new-name"
                                v-model="newEntityName"
                                type="text"
                                placeholder="Nama brand, produk, atau layanan"
                                class="border-line-strong text-ink placeholder-paid-muted focus:border-brand focus:ring-brand mt-1.5 min-h-11 w-full rounded-xl border bg-white px-4 py-2.5 text-base focus:ring-1 focus:outline-none sm:text-sm"
                            />
                            <label
                                for="sponsor-new-category"
                                class="text-ink mt-3 block text-xs font-bold"
                                >Kategori</label
                            >
                            <select
                                id="sponsor-new-category"
                                v-model="newEntityCategoryId"
                                class="border-line-strong text-ink focus:border-brand focus:ring-brand mt-1.5 min-h-11 w-full rounded-xl border bg-white px-4 py-2.5 text-base focus:ring-1 focus:outline-none sm:text-sm"
                            >
                                <option :value="null" disabled>
                                    Pilih kategori...
                                </option>
                                <option
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="cat.id"
                                >
                                    {{ cat.name }}
                                </option>
                            </select>
                            <label
                                for="sponsor-new-description"
                                class="text-ink mt-3 block text-xs font-bold"
                            >
                                Deskripsi singkat
                                <span class="text-ink-muted font-normal"
                                    >(opsional)</span
                                >
                            </label>
                            <textarea
                                id="sponsor-new-description"
                                v-model="newEntityDescription"
                                rows="2"
                                maxlength="500"
                                placeholder="Contoh: Kue kering dan takoyaki rumahan dari Bandung"
                                class="border-line-strong text-ink placeholder-paid-muted focus:border-brand focus:ring-brand mt-1.5 w-full rounded-xl border bg-white px-4 py-2.5 text-base focus:ring-1 focus:outline-none sm:text-sm"
                            ></textarea>

                            <div
                                v-if="candidates.length > 0"
                                class="border-paid-line mt-3 border-t pt-3"
                            >
                                <p class="text-ink-muted text-xs">
                                    Ternyata sudah terdaftar? Pilih di sini:
                                </p>
                                <div class="mt-1.5 flex flex-wrap gap-2">
                                    <button
                                        v-for="item in candidates"
                                        :key="item.id"
                                        type="button"
                                        class="border-line text-ink hover:border-line-strong inline-flex min-h-11 items-center rounded-lg border bg-white px-3 text-xs font-semibold"
                                        @click="chooseCandidate(item)"
                                    >
                                        {{ item.name }}
                                        <span
                                            class="text-ink-muted ml-1 font-normal"
                                            >· {{ item.category_name }}</span
                                        >
                                    </button>
                                </div>
                            </div>
                        </div>
                    </li>
                </ol>

                <!-- Langkah 3: nominal dan email, muncul setelah entitas jelas. -->
                <template v-if="hasSponsorTarget">
                    <p
                        class="text-ink mt-5 flex items-center gap-2 text-sm font-bold"
                    >
                        <span
                            class="bg-ink inline-flex size-6 items-center justify-center rounded-full text-xs text-white"
                            >{{ checkedUrl ? 3 : 2 }}</span
                        >
                        Nominal dan pembayaran
                    </p>
                    <!-- Contribution Amount -->
                    <div class="mt-2">
                        <label class="text-ink block text-xs font-bold">
                            Nominal sponsor (minimal
                            {{ formatRupiah(minAmount) }})
                        </label>

                        <!-- Presets -->
                        <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-5">
                            <button
                                v-for="preset in presetAmounts"
                                :key="preset"
                                type="button"
                                class="min-h-11 rounded-xl border py-2.5 text-xs font-bold transition"
                                :class="
                                    contributionAmount === preset
                                        ? 'border-star bg-danger-tint text-paid'
                                        : 'border-line text-ink-muted hover:bg-paper bg-white'
                                "
                                @click="selectPreset(preset)"
                            >
                                {{ formatRupiah(preset) }}
                            </button>
                        </div>

                        <!-- Custom input -->
                        <div class="relative mt-3">
                            <span
                                class="text-ink-muted absolute top-2.5 left-3 text-xs font-bold"
                                >Rp</span
                            >
                            <input
                                v-model="customAmount"
                                type="number"
                                :min="minAmount"
                                :step="incrementAmount || 100"
                                class="border-line-strong text-ink focus:border-brand focus:ring-brand w-full rounded-xl border py-2.5 pr-4 pl-10 text-sm font-bold focus:ring-1 focus:outline-none"
                                @input="handleCustomAmountChange"
                            />
                        </div>
                        <p class="text-ink-muted mt-1 text-[11px]">
                            Makin besar total sponsor, makin tinggi posisinya di
                            papan.
                        </p>

                        <!-- Quick Rebut Target Amount Button -->
                        <div v-if="rebutTarget" class="mt-2.5">
                            <button
                                type="button"
                                class="border-star bg-paid-tint text-paid hover:bg-paid-soft inline-flex min-h-11 items-center rounded-xl border px-3 py-2.5 text-xs font-bold shadow-2xs transition"
                                @click="selectPreset(rebutTarget.neededAmount)"
                            >
                                Pasang Nominal Rebut Posisi #{{
                                    rebutTarget.rank
                                }}: {{ formatRupiah(rebutTarget.neededAmount) }}
                            </button>
                        </div>
                    </div>

                    <!-- Rank Prediction Callout -->
                    <div
                        v-if="predictedRank"
                        class="border-paid-line bg-paid-tint text-paid mt-4 rounded-xl border p-3 text-xs"
                    >
                        <span class="font-bold">Estimasi Posisi:</span>
                        Dengan tambahan {{ formatRupiah(contributionAmount) }},
                        entitas ini diperkirakan menempati posisi
                        <strong class="text-paid font-extrabold"
                            >#{{ predictedRank }}</strong
                        >
                        di Leaderboard!
                    </div>

                    <!-- Guest email (no account required) -->
                    <div v-if="!currentUser" class="mt-4">
                        <label
                            for="sponsor-email"
                            class="text-ink block text-xs font-bold"
                        >
                            Email
                        </label>
                        <input
                            id="sponsor-email"
                            v-model="guestEmail"
                            autocomplete="email"
                            type="email"
                            placeholder="nama@email.com"
                            class="border-line-strong text-ink placeholder-paid-muted focus:border-brand focus:ring-brand mt-1.5 w-full rounded-xl border px-4 py-2.5 text-sm focus:ring-1 focus:outline-none"
                        />
                        <p class="text-ink-muted mt-1.5 text-[11px]">
                            Tidak perlu akun. Kami kirim link masuk ke email ini
                            agar Anda bisa cek status sponsor kapan saja.
                        </p>
                    </div>

                    <!-- Error message -->
                    <div
                        v-if="errorMessage"
                        role="alert"
                        class="bg-danger-tint text-danger mt-4 flex items-center gap-2 rounded-xl p-3 text-xs"
                    >
                        <AlertCircle class="size-4 shrink-0" />
                        <span>{{ errorMessage }}</span>
                    </div>

                    <div class="border-line-soft mt-4 border-t pt-3">
                        <button
                            type="button"
                            class="bg-paid-strong hover:bg-paid-deep min-h-11 w-full rounded-full py-3 text-sm font-bold text-white shadow-md transition disabled:opacity-50"
                            :disabled="isSubmitting"
                            @click="openConfirmModal()"
                        >
                            Lanjut ke Pembayaran QRIS ({{
                                formatRupiah(contributionAmount)
                            }})
                        </button>
                    </div>
                </template>
            </div>

            <!-- Leaderboard search (rankup.uno-style): filters the board below, never affects
                 the sponsor entry form above or organic search. -->
            <div v-if="leaderboard.length > 0" class="relative mt-8 w-full">
                <Search class="text-ink-muted absolute top-3 left-3 size-4" />
                <input
                    v-model="leaderboardQuery"
                    type="search"
                    placeholder="Cari di leaderboard..."
                    class="border-line text-ink placeholder-paid-muted focus:border-brand focus:ring-brand w-full rounded-xl border bg-white py-2.5 pr-4 pl-9 text-sm focus:ring-1 focus:outline-none"
                />
            </div>

            <!-- Empty state -->
            <div
                v-if="leaderboard.length === 0"
                class="border-line mt-10 rounded-3xl border border-dashed bg-white p-12 text-center"
            >
                <div
                    class="bg-paid-tint text-paid mx-auto flex size-16 items-center justify-center rounded-2xl"
                >
                    <Trophy class="size-8" />
                </div>
                <h3 class="text-ink mt-4 text-lg font-bold">
                    Belum ada entitas di leaderboard periode ini
                </h3>
                <p class="text-ink-muted mx-auto mt-2 max-w-md text-sm">
                    Jadilah yang pertama mengangkat brand, produk, atau layanan
                    pilihanmu ke posisi teratas Leaderboard periode ini!
                </p>
            </div>

            <div
                v-else-if="filteredLeaderboard.length === 0"
                class="border-line text-ink-muted mt-6 rounded-2xl border border-dashed bg-white p-8 text-center text-sm"
            >
                Tidak ada entitas di leaderboard yang cocok dengan "{{
                    leaderboardQuery
                }}".
            </div>

            <!-- Leaderboard Content -->
            <div v-else class="mt-5 space-y-2">
                <template
                    v-for="(section, sectionIndex) in leaderboardSections"
                    :key="section.key"
                >
                    <div
                        class="overflow-hidden rounded-2xl border shadow-sm"
                        :class="
                            sectionIndex === 0
                                ? 'border-line-strong bg-canvas'
                                : 'border-line bg-white'
                        "
                    >
                        <div
                            class="border-b px-4 py-3"
                            :class="
                                sectionIndex === 0
                                    ? 'border-line-strong bg-sage'
                                    : 'border-line bg-canvas'
                            "
                        >
                            <h3 class="text-ink text-sm font-bold">
                                {{ section.title }}
                            </h3>
                        </div>
                        <div class="divide-line-soft divide-y">
                            <div
                                v-for="entry in section.entries"
                                :key="entry.id"
                                class="hover:bg-canvas flex flex-col gap-2 p-2.5 transition sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                            >
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <span
                                        class="bg-sage text-ink-muted flex size-6 shrink-0 items-center justify-center rounded-md text-xs font-black"
                                    >
                                        #{{ entry.rank }}
                                    </span>
                                    <img
                                        v-if="getFaviconUrl(entry.website_url)"
                                        :src="getFaviconUrl(entry.website_url)!"
                                        :alt="entry.name"
                                        class="size-6 shrink-0 rounded-md border border-black/10 bg-white object-contain p-0.5"
                                        loading="lazy"
                                        @error="
                                            (e) =>
                                                ((
                                                    e.target as HTMLElement
                                                ).style.display = 'none')
                                        "
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex flex-wrap items-center gap-1.5"
                                        >
                                            <Link
                                                :href="showEntity(entry.slug)"
                                                class="text-ink hover:text-brand truncate text-sm font-bold"
                                            >
                                                {{ entry.name }}
                                            </Link>
                                            <span
                                                class="rounded-md bg-neutral-100 px-2 py-0.5 text-[10px] font-medium text-neutral-600"
                                            >
                                                {{ entry.category_name }}
                                            </span>
                                        </div>
                                        <p
                                            v-if="entry.description"
                                            class="text-ink-muted mt-0.5 truncate text-[11px] leading-4"
                                        >
                                            {{ entry.description }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="border-line-soft flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5 border-t pt-1.5 sm:justify-end sm:border-0 sm:pt-0"
                                >
                                    <!-- Stats: Sentimen, Views, Clicks (Visible on Mobile & Desktop) -->
                                    <div
                                        class="text-ink-muted flex items-center gap-2 text-[10px] sm:text-[11px]"
                                    >
                                        <span
                                            v-if="
                                                typeof entry.sentiment_score ===
                                                'number'
                                            "
                                            class="text-brand-strong inline-flex items-center gap-0.5 font-bold"
                                            title="Sentimen Netijen"
                                        >
                                            Sentimen
                                            {{
                                                entry.sentiment_score.toLocaleString(
                                                    'id-ID',
                                                    {
                                                        maximumFractionDigits: 1,
                                                    },
                                                )
                                            }}
                                        </span>
                                        <span v-else class="text-neutral-500"
                                            >Tanpa skor</span
                                        >

                                        <span
                                            class="inline-flex items-center gap-1"
                                            title="Kunjungan detail"
                                        >
                                            <Eye class="text-info size-3" />
                                            {{
                                                (
                                                    entry.views_count || 0
                                                ).toLocaleString('id-ID')
                                            }}
                                        </span>

                                        <span
                                            class="inline-flex items-center gap-1"
                                            title="Klik direct URL"
                                        >
                                            <MousePointerClick
                                                class="text-brand size-3"
                                            />
                                            {{
                                                (
                                                    entry.clicks_count || 0
                                                ).toLocaleString('id-ID')
                                            }}
                                            klik
                                        </span>
                                    </div>

                                    <!-- Total Sponsor -->
                                    <div class="text-right">
                                        <span
                                            class="text-paid text-xs font-black sm:text-sm"
                                        >
                                            {{
                                                formatRupiah(
                                                    entry.settled_total_amount,
                                                )
                                            }}
                                        </span>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex items-center gap-1.5">
                                        <a
                                            :href="
                                                getDirectWebsiteUrl(
                                                    entry.website_url,
                                                    entry.slug,
                                                    {
                                                        placement:
                                                            'leaderboard_row',
                                                    },
                                                )
                                            "
                                            :ping="`/api/sponsor/click/${entry.slug}`"
                                            target="_blank"
                                            rel="noopener"
                                            class="bg-sage text-brand-deep hover:bg-brand-tint-strong inline-flex min-h-11 items-center justify-center rounded-lg px-2.5 py-2.5 text-xs font-bold transition"
                                            @click="
                                                trackSponsorClick(entry.slug, {
                                                    placement:
                                                        'leaderboard_row',
                                                    url:
                                                        entry.website_url ||
                                                        undefined,
                                                })
                                            "
                                        >
                                            Buka Situs
                                        </a>
                                        <button
                                            type="button"
                                            class="border-line text-ink hover:border-line-strong hover:bg-sage inline-flex min-h-11 items-center justify-center rounded-lg border bg-white px-2.5 py-2.5 text-xs font-bold transition"
                                            @click="
                                                selectEntityAndScrollToForm(
                                                    entry,
                                                )
                                            "
                                        >
                                            + Sponsori
                                        </button>
                                        <button
                                            type="button"
                                            class="border-star bg-paid-tint text-paid hover:bg-paid-soft inline-flex min-h-11 items-center justify-center rounded-lg border px-2.5 py-2.5 text-xs font-bold transition"
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
                    <div
                        v-if="sectionIndex === 0 && stats"
                        class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-5"
                    >
                        <div
                            class="border-line rounded-2xl border bg-white p-3 shadow-2xs"
                        >
                            <div
                                class="text-ink-muted flex items-center gap-1.5 text-[11px] font-semibold"
                            >
                                <Trophy class="text-paid size-3.5" /> Total
                                Listing
                            </div>
                            <div class="text-ink mt-1 text-lg font-black">
                                {{ stats.total_listings }}
                            </div>
                            <div class="text-ink-muted mt-0.5 text-[10px]">
                                entitas aktif
                            </div>
                        </div>

                        <div
                            class="border-paid-line bg-canvas rounded-2xl border p-3 shadow-2xs"
                        >
                            <div
                                class="text-paid flex items-center gap-1.5 text-[11px] font-semibold"
                            >
                                <Award class="text-paid size-3.5" /> Total
                                Sponsor
                            </div>
                            <div class="text-paid mt-1 text-lg font-black">
                                {{ formatRupiah(stats.total_amount) }}
                            </div>
                            <div class="text-paid mt-0.5 text-[10px]">
                                terkonfirmasi
                            </div>
                        </div>

                        <div
                            class="border-line rounded-2xl border bg-white p-3 shadow-2xs"
                        >
                            <div
                                class="text-ink-muted flex items-center gap-1.5 text-[11px] font-semibold"
                            >
                                <MousePointerClick
                                    class="text-brand size-3.5"
                                />
                                Total Klik URL
                            </div>
                            <div class="text-ink mt-1 text-lg font-black">
                                {{ stats.total_clicks.toLocaleString('id-ID') }}
                            </div>
                            <div class="text-ink-muted mt-0.5 text-[10px]">
                                kunjungan direct link
                            </div>
                        </div>

                        <div
                            class="border-line rounded-2xl border bg-white p-3 shadow-2xs"
                        >
                            <div
                                class="text-ink-muted flex items-center gap-1.5 text-[11px] font-semibold"
                            >
                                <Eye class="text-info size-3.5" /> Total
                                Pengunjung
                            </div>
                            <div class="text-ink mt-1 text-lg font-black">
                                {{ stats.total_views.toLocaleString('id-ID') }}
                            </div>
                            <div class="text-ink-muted mt-0.5 text-[10px]">
                                tampilan halaman detail
                            </div>
                        </div>

                        <div
                            class="border-line col-span-2 rounded-2xl border bg-white p-3 shadow-2xs sm:col-span-1"
                        >
                            <div
                                class="text-ink-muted flex items-center gap-1.5 text-[11px] font-semibold"
                            >
                                <Award class="text-paid size-3.5" /> Peringkat
                                #1 Saat Ini
                            </div>
                            <div class="text-ink mt-1 text-lg font-black">
                                {{ formatRupiah(stats.highest_bid) }}
                            </div>
                            <div class="text-ink-muted mt-0.5 text-[10px]">
                                sponsor tertinggi
                            </div>
                        </div>
                    </div>
                </template>

                <div
                    v-if="canLoadMoreLeaderboard"
                    class="mt-4 flex justify-center"
                >
                    <button
                        type="button"
                        class="border-line-strong text-brand-deep hover:border-line-strong hover:bg-sage inline-flex min-h-11 items-center justify-center rounded-full border bg-white px-5 py-2 text-xs font-bold shadow-xs transition"
                        @click="loadMoreLeaderboard"
                    >
                        Muat 25 peringkat lagi
                    </button>
                </div>
            </div>

            <section
                class="border-line text-ink-muted mt-10 space-y-4 rounded-2xl border bg-white p-5 text-sm leading-relaxed sm:p-6"
            >
                <h2 class="text-ink text-lg font-bold">
                    Cara kerja Papan Sponsor
                </h2>
                <p>
                    Papan Sponsor adalah tempat brand, produk, atau layanan
                    tampil lebih menonjol dengan membayar posisi. Papan ini
                    terpisah dari ranking Sentimen Netijen. Nominal sponsor
                    tidak masuk ke skor, tidak mengubah urutan ranking kategori,
                    dan tidak memengaruhi hasil pencarian.
                </p>
                <ol class="list-decimal space-y-2 pl-5">
                    <li>
                        Tulis alamat situs resmi brand, lalu tekan Cek situs.
                        Kalau brand itu sudah terdaftar di SuaraNetijen, kami
                        pilihkan otomatis. Kalau belum, isi nama dan kategorinya
                        untuk mendaftarkannya sebagai brand baru.
                    </li>
                    <li>
                        Isi nominal, mulai {{ formatRupiah(minAmount) }}.
                        Pembayaran memakai QRIS. Kamu tidak perlu membuat akun
                        lebih dulu: cukup isi email, dan kami kirim link untuk
                        membuka status pesanan dari perangkat lain.
                    </li>
                    <li>
                        Posisi baru berubah setelah pembayaran terkonfirmasi.
                        Pesanan yang belum dibayar, kedaluwarsa, atau gagal
                        tidak dihitung. Entitas baru yang didaftarkan lewat
                        sponsor juga baru tampil setelah pembayarannya
                        terkonfirmasi.
                    </li>
                </ol>

                <h3 class="text-ink pt-2 text-base font-bold">Urutan papan</h3>
                <p>
                    Entitas diurutkan berdasarkan total nominal sponsor
                    terkonfirmasi pada periode yang dipilih. Kalau totalnya
                    sama, entitas yang lebih dulu terkonfirmasi ada di atas.
                    Papan aktif berganti tiap minggu, sedangkan tab Semua Waktu
                    menampilkan akumulasi sepanjang waktu.
                </p>
                <p>
                    Satu entitas bisa disponsori banyak orang, jadi total yang
                    tampil adalah jumlah semua kontribusi, bukan belanja satu
                    sponsor. Tombol Rebut menghitung nominal yang kamu butuhkan
                    untuk melewati posisi itu.
                </p>

                <h3 class="text-ink pt-2 text-base font-bold">
                    Yang dibeli dan yang tidak
                </h3>
                <p>
                    Sponsor membeli posisi tampil di papan ini saja. Posisi itu
                    tidak menjamin kunjungan, penjualan, atau sentimen yang
                    lebih baik, dan tidak menghapus opini negatif tentang
                    entitas tersebut. Tokoh publik dan politisi tidak bisa
                    disponsori. Admin bisa menjeda atau menghapus entri yang
                    melanggar
                    <Link
                        :href="terms()"
                        class="text-brand font-semibold hover:underline"
                        >syarat dan ketentuan</Link
                    >. Cara skor sentimen dihitung ada di halaman
                    <Link
                        :href="methodology()"
                        class="text-brand font-semibold hover:underline"
                        >metodologi</Link
                    >.
                </p>
            </section>
        </div>

        <!-- Payment confirmation modal — the ONLY modal in this flow; everything gathering
             input above is plain page content. -->
        <div
            v-if="isConfirmModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        >
            <div
                class="border-line relative w-full max-w-md rounded-3xl border bg-white p-6 shadow-2xl sm:p-8"
            >
                <button
                    type="button"
                    class="text-ink-muted hover:bg-paper hover:text-ink absolute top-4 right-4 flex size-11 items-center justify-center rounded-full"
                    @click="closeConfirmModal()"
                >
                    <X class="size-5" />
                </button>

                <div
                    class="text-paid flex items-center gap-2 text-xs font-bold"
                >
                    <Trophy class="size-4" />
                    KONFIRMASI PEMBAYARAN
                </div>
                <h3 class="text-ink mt-1 text-xl font-extrabold">
                    Ringkasan Pesanan Sponsor
                </h3>

                <div
                    class="border-line bg-canvas mt-5 space-y-3 rounded-2xl border p-4 text-sm"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-ink-muted">Entitas</span>
                        <span class="text-ink font-bold">{{
                            selectedEntity?.name || newEntityName
                        }}</span>
                    </div>
                    <div
                        v-if="isNewEntityMode"
                        class="flex items-center justify-between"
                    >
                        <span class="text-ink-muted">Status</span>
                        <span class="text-paid font-bold"
                            >Entitas baru, aktif setelah bayar</span
                        >
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-ink-muted">Nominal</span>
                        <span class="text-paid font-bold">{{
                            formatRupiah(contributionAmount)
                        }}</span>
                    </div>
                    <div
                        v-if="!currentUser"
                        class="flex items-center justify-between"
                    >
                        <span class="text-ink-muted">Email</span>
                        <span class="text-ink font-bold">{{ guestEmail }}</span>
                    </div>
                    <div
                        v-if="predictedRank"
                        class="flex items-center justify-between"
                    >
                        <span class="text-ink-muted">Estimasi Posisi</span>
                        <span class="text-paid font-bold"
                            >#{{ predictedRank }}</span
                        >
                    </div>
                </div>

                <div
                    v-if="errorMessage"
                    class="bg-danger-tint text-danger mt-4 flex items-center gap-2 rounded-xl p-3 text-xs"
                >
                    <AlertCircle class="size-4 shrink-0" />
                    <span>{{ errorMessage }}</span>
                </div>

                <div class="mt-6">
                    <button
                        type="button"
                        class="bg-paid-strong hover:bg-paid-deep min-h-11 w-full rounded-full py-3 text-sm font-bold text-white shadow-md transition disabled:opacity-50"
                        :disabled="isSubmitting"
                        @click="submitOrder()"
                    >
                        <span v-if="isSubmitting">Memproses ke QRIS...</span>
                        <span v-else>Bayar Sekarang</span>
                    </button>
                    <p class="text-ink-muted mt-2 text-center text-[11px]">
                        Pembayaran diproses aman melalui QRIS. Biaya gateway
                        QRIS ditanggung pembeli.
                    </p>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
