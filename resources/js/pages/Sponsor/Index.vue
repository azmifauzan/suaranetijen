<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowUpRight,
    Award,
    CheckCircle2,
    Clock,
    Search,
    Shield,
    Sparkles,
    Trophy,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PublicSeo from '@/components/PublicSeo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { show as showEntity } from '@/routes/entities';
import { index as sponsorPage } from '@/routes/sponsor';

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
    settled_total_amount: number;
    first_settled_at: string | null;
    clicks_count: number;
    sentiment_score: number | null;
    opinion_count: number;
    rating_average: number | null;
    rating_count: number;
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
    minAmount: number;
    incrementAmount: number;
    userOrder?: UserOrderInfo | null;
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

// Sponsor entry form state — inline on the page (Pamerin/RankUp both keep this as a plain page
// section, never a popup), with a confirm-and-pay modal only at the final step.
const formSection = ref<HTMLElement | null>(null);
const urlInput = ref('');
const isFetchingPreview = ref(false);
const previewError = ref<string | null>(null);
const urlPreview = ref<{ title: string; url: string } | null>(null);
const candidates = ref<Array<{ id: number; name: string; slug: string; category_name: string; type_label: string }>>([]);
const selectedEntity = ref<{ id: number; name: string; slug: string } | null>(null);
const contributionAmount = ref<number>(10000);
const customAmount = ref<string>('10000');
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);
const guestEmail = ref<string>('');
const isConfirmModalOpen = ref(false);

const isValidGuestEmail = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(guestEmail.value.trim()));

const presetAmounts = [10000, 25000, 50000, 100000, 250000];

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
// "the rest" is a list of existing entities matched by that title (never a new listing, per
// this app's entity-centric constraint).
function isLikelyUrl(value: string): boolean {
    try {
        const parsed = new URL(value.trim());
        return parsed.protocol === 'http:' || parsed.protocol === 'https:';
    } catch {
        return false;
    }
}

let previewTimer: ReturnType<typeof setTimeout> | null = null;
watch(urlInput, (value) => {
    if (previewTimer) clearTimeout(previewTimer);
    urlPreview.value = null;
    candidates.value = [];
    previewError.value = null;
    selectedEntity.value = null;

    if (!isLikelyUrl(value)) {
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
                body: JSON.stringify({ url: value.trim() }),
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
        } catch {
            previewError.value = 'Gagal mengambil informasi dari URL tersebut.';
        } finally {
            isFetchingPreview.value = false;
        }
    }, 400);
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
    if (!selectedEntity.value) {
        errorMessage.value = 'Silakan pilih entitas yang ingin disponsori.';
        return;
    }

    if (contributionAmount.value < props.minAmount) {
        errorMessage.value = `Nominal minimal adalah Rp${props.minAmount.toLocaleString('id-ID')}.`;
        return;
    }

    if (contributionAmount.value % props.incrementAmount !== 0) {
        errorMessage.value = `Nominal harus kelipatan Rp${props.incrementAmount.toLocaleString('id-ID')}.`;
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
    if (!selectedEntity.value) return;

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
                entity_id: selectedEntity.value.id,
                amount: contributionAmount.value,
                // No redirect_url: the backend builds the correct post-payment URL itself
                // (with the real order id, known only after creation) as its own default.
                ...(currentUser.value ? {} : { email: guestEmail.value.trim() }),
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
            router.visit(sponsorPage({ query: { order_id: data.data?.id } }));
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
            title="Papan Peringkat Sponsor Brand, Produk, dan Layanan Indonesia"
            description="Papan peringkat sponsor (leaderboard) SuaraNetijen: peringkat exposure publik untuk brand, produk, dan layanan Indonesia berdasarkan nominal sponsor terkonfirmasi."
            canonical-path="/sponsor"
        />

        <div class="mx-auto max-w-6xl px-5 py-8 sm:px-8 sm:py-12">
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
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#d97706] px-4 py-2 text-xs font-semibold text-white transition hover:bg-[#b45309]"
                            >
                                Bayar Sekarang via QRIS
                                <ArrowUpRight class="size-4" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hero Section -->
            <div class="relative overflow-hidden rounded-3xl border border-[#e8d7be] bg-gradient-to-br from-[#fffbf4] via-[#fffdf9] to-[#f7f3ea] p-6 sm:p-10">
                <div class="pointer-events-none absolute -top-16 -right-16 size-72 rounded-full bg-[#fcedd2]/40 blur-3xl"></div>

                <div class="relative z-10">
                    <div class="inline-flex items-center gap-2 rounded-full border border-[#e4ccab] bg-[#fff6e6] px-3.5 py-1.5 text-xs font-bold tracking-wide text-[#8a5d1a]">
                        <Trophy class="size-4 text-[#d97706]" />
                        PAPAN SPONSOR SUARANETIJEN
                    </div>

                    <div class="mt-4 max-w-2xl">
                        <h1 class="text-3xl font-extrabold tracking-tight text-[#2b2419] sm:text-4xl">
                            Papan Peringkat Sponsor
                        </h1>
                        <p class="mt-2 text-sm leading-relaxed text-[#6b5d49] sm:text-base">
                            Tampilkan dan dukung brand, produk, atau layanan favoritmu. Peringkat exposure disusun berdasarkan nominal kontribusi sponsor terkonfirmasi.
                        </p>
                    </div>

                    <!-- Mandatory Legal / Disclosure Notice -->
                    <div class="mt-6 flex items-start gap-3 rounded-2xl border border-[#e5d4b8] bg-white/80 p-4 text-xs leading-relaxed text-[#7c694e] shadow-sm">
                        <AlertCircle class="size-4 shrink-0 text-[#b45309]" />
                        <div>
                            <strong class="font-semibold text-[#54432c]">Keterbukaan & Independensi:</strong>
                            Urutan papan ini ditentukan murni berdasarkan nominal pembayaran sponsor terkonfirmasi. Penempatan sponsor sama sekali <em>tidak memengaruhi</em> Sentimen Netijen, Rating Netijen, tema suara netijen, ataupun hasil algoritma pencarian.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sponsor Entry Form — inline on the page, not a popup (Pamerin/RankUp both keep
                 this as a plain section; only the final payment confirmation below is a modal). -->
            <div ref="formSection" class="mt-8 rounded-3xl border border-[#e5e9e2] bg-white p-6 shadow-sm sm:p-8">
                <div class="flex items-center gap-2 text-xs font-bold text-[#d97706]">
                    <Trophy class="size-4" />
                    SPONSORI ENTITAS
                </div>
                <h3 class="mt-1 text-xl font-extrabold text-[#18392d]">
                    Tingkatkan Posisi Papan
                </h3>
                <p class="mt-1 text-xs text-[#637568]">
                    Dukungan Anda terakumulasi pada entitas pilihan untuk periode {{ activePeriod.name }}.
                </p>

                <!-- Step 1: URL first — fetch the site, then match against existing entities. -->
                <div class="mt-6 max-w-lg">
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

                    <!-- No match: SuaraNetijen never auto-creates a listing from a URL, so this
                         is a dead end pending the existing admin/entity workflow (docs/26). -->
                    <div
                        v-if="urlPreview && !isFetchingPreview && candidates.length === 0 && !selectedEntity"
                        class="mt-2 rounded-xl border border-[#e5d4b8] bg-[#fffaf0] p-3 text-xs text-[#7c694e]"
                    >
                        Entitas untuk situs ini belum terdaftar di SuaraNetijen. Hubungi admin untuk menambahkannya
                        sebelum bisa disponsori.
                    </div>

                    <div
                        v-if="selectedEntity"
                        class="mt-2 flex items-center justify-between rounded-xl border border-[#bfe2ca] bg-[#f0faf2] p-3 text-xs"
                    >
                        <span class="font-bold text-[#1b6b44]">
                            ✓ Terpilih: {{ selectedEntity.name }}
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

                <!-- Step 2: appears only once an entity is confirmed. -->
                <template v-if="selectedEntity">
                    <!-- Guest email (no account required) -->
                    <div v-if="!currentUser" class="mt-6 max-w-lg">
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
                    <div class="mt-6 max-w-lg">
                        <label class="block text-xs font-bold text-[#31483b]">
                            Nominal Sponsor (Kelipatan Rp{{ incrementAmount.toLocaleString('id-ID') }})
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
                                :step="incrementAmount"
                                class="w-full rounded-xl border border-[#cfd9ce] py-2.5 pr-4 pl-10 text-sm font-bold text-[#18392d] focus:border-[#087f5b] focus:ring-1 focus:ring-[#087f5b] focus:outline-none"
                                @input="handleCustomAmountChange"
                            />
                        </div>
                    </div>

                    <!-- Rank Prediction Callout -->
                    <div
                        v-if="predictedRank"
                        class="mt-4 max-w-lg rounded-xl border border-[#fed7aa] bg-[#fffbf2] p-3 text-xs text-[#9a6a24]"
                    >
                        <span class="font-bold">Estimasi Posisi:</span>
                        Dengan tambahan {{ formatRupiah(contributionAmount) }}, entitas ini diperkirakan menempati posisi
                        <strong class="font-extrabold text-[#92400e]">#{{ predictedRank }}</strong> di Papan Sponsor!
                    </div>

                    <!-- Error message -->
                    <div
                        v-if="errorMessage"
                        class="mt-4 flex max-w-lg items-center gap-2 rounded-xl bg-[#fdf2f2] p-3 text-xs text-[#b91c1c]"
                    >
                        <AlertCircle class="size-4 shrink-0" />
                        <span>{{ errorMessage }}</span>
                    </div>

                    <div class="mt-6 max-w-lg pt-4 border-t border-[#edf1eb]">
                        <button
                            type="button"
                            class="w-full rounded-full bg-[#d97706] py-3 text-sm font-bold text-white shadow-md transition hover:bg-[#b45309] disabled:opacity-50"
                            :disabled="!currentUser && !isValidGuestEmail"
                            @click="openConfirmModal()"
                        >
                            Lanjut ke Pembayaran QRIS ({{ formatRupiah(contributionAmount) }})
                        </button>
                    </div>
                </template>
            </div>

            <!-- Controls & Period Switcher -->
            <div class="mt-8 flex flex-col justify-between gap-4 border-b border-[#e5e9e2] pb-5 sm:flex-row sm:items-center">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#738378] mr-2">
                        Periode:
                    </span>
                    <Link
                        v-for="p in periods"
                        :key="p.id"
                        :href="sponsorPage({ query: { period: p.key } })"
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

                <div class="flex items-center gap-4 text-xs text-[#66776b]">
                    <span class="flex items-center gap-1.5">
                        <Sparkles class="size-4 text-[#d97706]" /> Model Komunitas (Terakumulasi)
                    </span>
                    <span class="flex items-center gap-1.5">
                        <Shield class="size-4 text-[#16a34a]" /> Pembayaran Resmi QRIS
                    </span>
                </div>
            </div>

            <!-- Leaderboard search (rankup.uno-style): filters the board below, never affects
                 the sponsor entry form above or organic search. -->
            <div v-if="leaderboard.length > 0" class="relative mt-5 max-w-md">
                <Search class="absolute top-3 left-3 size-4 text-[#8e9f93]" />
                <input
                    v-model="leaderboardQuery"
                    type="search"
                    placeholder="Cari di papan sponsor..."
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
                    Belum ada entitas yang disponsori di periode ini
                </h3>
                <p class="mx-auto mt-2 max-w-md text-sm text-[#6c7d70]">
                    Jadilah yang pertama mengangkat brand, produk, atau layanan pilihanmu ke posisi teratas Papan Sponsor minggu ini!
                </p>
            </div>

            <div
                v-else-if="filteredLeaderboard.length === 0"
                class="mt-6 rounded-2xl border border-dashed border-[#dce3db] bg-white p-8 text-center text-sm text-[#6c7d70]"
            >
                Tidak ada entitas di papan sponsor yang cocok dengan "{{ leaderboardQuery }}".
            </div>

            <!-- Leaderboard Content -->
            <div v-else class="mt-6 space-y-4">
                <!-- Top 3 Podium Cards -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div
                        v-for="entry in filteredLeaderboard.slice(0, 3)"
                        :key="entry.id"
                        class="relative flex flex-col justify-between rounded-3xl border p-6 transition duration-200 hover:-translate-y-1 hover:shadow-lg"
                        :class="
                            entry.rank === 1
                                ? 'border-[#e8cb97] bg-gradient-to-b from-[#fffbf0] to-white shadow-sm ring-1 ring-[#f4d9a6]'
                                : entry.rank === 2
                                  ? 'border-[#d4dfd6] bg-gradient-to-b from-[#f9faf9] to-white'
                                  : 'border-[#dfd8cc] bg-gradient-to-b from-[#fdfbf9] to-white'
                        "
                    >
                        <div>
                            <!-- Header rank & category -->
                            <div class="flex items-center justify-between">
                                <span
                                    class="inline-flex size-9 items-center justify-center rounded-xl font-extrabold text-sm shadow-sm"
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

                                <span class="rounded-full bg-black/5 px-2.5 py-1 text-[11px] font-semibold text-[#5a6b60]">
                                    {{ entry.type_label }}
                                </span>
                            </div>

                            <!-- Entity Title -->
                            <div class="mt-4">
                                <Link
                                    :href="showEntity(entry.slug)"
                                    class="group inline-flex items-center gap-1.5 text-xl font-bold tracking-tight text-[#18392d] hover:text-[#087f5b]"
                                >
                                    <span>{{ entry.name }}</span>
                                    <ArrowUpRight class="size-4 text-[#8b998f] transition group-hover:text-[#087f5b]" />
                                </Link>
                                <p class="text-xs text-[#738478]">
                                    {{ entry.category_name }}
                                </p>
                            </div>

                            <!-- Sponsor Amount Badge (Amber/Gold) -->
                            <div class="mt-5 rounded-2xl border border-[#fed7aa] bg-[#fffaf0] p-3.5">
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-[#9a6a24]">
                                    Total Sponsor
                                </span>
                                <div class="mt-0.5 text-2xl font-extrabold text-[#92400e]">
                                    {{ formatRupiah(entry.settled_total_amount) }}
                                </div>
                            </div>

                            <!-- Organic Sentiment Pill (Green, separate) -->
                            <div class="mt-3 flex items-center justify-between rounded-xl bg-[#f3f7f2] px-3 py-2 text-xs">
                                <span class="font-medium text-[#5c6e62]">Sentimen Netijen:</span>
                                <span
                                    v-if="entry.sentiment_score !== null"
                                    class="font-bold text-[#1b6b44]"
                                >
                                    {{ entry.sentiment_score.toLocaleString('id-ID', { maximumFractionDigits: 1 }) }} / 100
                                </span>
                                <span v-else class="text-[#7c8d82]">
                                    Belum cukup data
                                </span>
                            </div>
                        </div>

                        <!-- Top-up Button -->
                        <div class="mt-6 pt-4 border-t border-[#f0f3ee]">
                            <button
                                type="button"
                                class="w-full rounded-xl border border-[#d8e3d6] bg-white py-2.5 text-xs font-bold text-[#1f4a38] transition hover:border-[#8cb896] hover:bg-[#edf6ee]"
                                @click="selectEntityAndScrollToForm({ id: entry.entity_id, name: entry.name, slug: entry.slug })"
                            >
                                + Tambah Sponsor Entitas Ini
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Ranked 4+ Table List -->
                <div v-if="filteredLeaderboard.length > 3" class="mt-8 overflow-hidden rounded-2xl border border-[#dfe5dc] bg-white shadow-sm">
                    <div class="border-b border-[#dfe5dc] bg-[#f8faf7] px-6 py-4">
                        <h3 class="text-sm font-bold text-[#18392d]">
                            Daftar Entitas Lainnya (#4 ke atas)
                        </h3>
                    </div>
                    <div class="divide-y divide-[#edf1ec]">
                        <div
                            v-for="entry in filteredLeaderboard.slice(3)"
                            :key="entry.id"
                            class="flex flex-col items-start justify-between gap-4 p-5 transition sm:flex-row sm:items-center hover:bg-[#fafcfa]"
                        >
                            <div class="flex items-center gap-4">
                                <span class="flex size-9 items-center justify-center rounded-xl bg-[#f0f4ef] text-sm font-extrabold text-[#45574a]">
                                    #{{ entry.rank }}
                                </span>
                                <div>
                                    <Link
                                        :href="showEntity(entry.slug)"
                                        class="font-bold text-[#18392d] hover:text-[#087f5b]"
                                    >
                                        {{ entry.name }}
                                    </Link>
                                    <div class="flex items-center gap-2 text-xs text-[#758479]">
                                        <span>{{ entry.type_label }}</span>
                                        <span>·</span>
                                        <span>{{ entry.category_name }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex w-full flex-wrap items-center justify-between gap-4 sm:w-auto sm:justify-end">
                                <div class="text-right">
                                    <div class="text-base font-extrabold text-[#92400e]">
                                        {{ formatRupiah(entry.settled_total_amount) }}
                                    </div>
                                    <div class="text-[11px] text-[#718276]">
                                        Sentimen:
                                        <span v-if="entry.sentiment_score !== null" class="font-bold text-[#1b6b44]">
                                            {{ entry.sentiment_score }} / 100
                                        </span>
                                        <span v-else>—</span>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="rounded-xl border border-[#d8e3d6] px-4 py-2 text-xs font-bold text-[#18392d] transition hover:border-[#8cb896] hover:bg-[#f0f7f0]"
                                    @click="selectEntityAndScrollToForm({ id: entry.entity_id, name: entry.name, slug: entry.slug })"
                                >
                                    + Sponsori
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Educational / FAQ Section -->
            <div class="mt-14 rounded-3xl border border-[#e2e8df] bg-[#f9fbf8] p-6 sm:p-10">
                <h3 class="text-lg font-bold text-[#18392d]">
                    Tentang Papan Sponsor SuaraNetijen
                </h3>
                <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div class="rounded-2xl border border-[#e5ebe2] bg-white p-5">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-[#fff6e6] text-[#d97706]">
                            <Award class="size-5" />
                        </div>
                        <h4 class="mt-3 font-bold text-sm text-[#18392d]">
                            Model Komunitas
                        </h4>
                        <p class="mt-1 text-xs leading-relaxed text-[#68796d]">
                            Siapa pun dapat mensponsori atau menambah nominal (top up) pada entitas apa pun. Total yang tertera adalah akumulasi dukungan dari pengguna.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-[#e5ebe2] bg-white p-5">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-[#f0faf3] text-[#16a34a]">
                            <Shield class="size-5" />
                        </div>
                        <h4 class="mt-3 font-bold text-sm text-[#18392d]">
                            Skor Bersih & Terpisah
                        </h4>
                        <p class="mt-1 text-xs leading-relaxed text-[#68796d]">
                            Penempatan sponsor tidak akan pernah mengubah indeks sentimen, rating bintang, tema suara, ataupun urutan pada hasil pencarian organik.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-[#e5ebe2] bg-white p-5">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-[#eff6ff] text-[#2563eb]">
                            <Clock class="size-5" />
                        </div>
                        <h4 class="mt-3 font-bold text-sm text-[#18392d]">
                            Musim Mingguan
                        </h4>
                        <p class="mt-1 text-xs leading-relaxed text-[#68796d]">
                            Papan berjalan dalam siklus mingguan baru setiap pekan, memberikan kesempatan yang adil bagi entitas baru untuk tampil teratas.
                        </p>
                    </div>
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
                        <span class="font-bold text-[#18392d]">{{ selectedEntity?.name }}</span>
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
