<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowRight,
    CheckCircle2,
    Clock,
    RefreshCw,
    Trophy,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PublicSeo from '@/components/PublicSeo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { show as showOrder } from '@/routes/api/sponsor/orders';
import { index as leaderboardPage } from '@/routes/leaderboard';

interface PaymentOrder {
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
    order: PaymentOrder;
}>();

const status = ref(props.order.status);
const statusLabel = ref(props.order.status_label);
const paymentLinkUrl = ref(props.order.payment_link_url);
const isChecking = ref(false);
const isRedirecting = ref(false);
const pollError = ref<string | null>(null);
const lastCheckedAt = ref<Date | null>(null);
const pollIntervalMs = 10_000;
let pollTimer: number | null = null;

const isPaid = computed(() => status.value === 'paid');
const isTerminal = computed(() =>
    ['failed', 'expired', 'refunded'].includes(status.value),
);

function formatRupiah(amount: number): string {
    return 'Rp' + amount.toLocaleString('id-ID');
}

function stopPolling(): void {
    if (pollTimer !== null) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
}

function redirectToLeaderboard(): void {
    if (isRedirecting.value) return;

    isRedirecting.value = true;
    stopPolling();
    router.visit(leaderboardPage());
}

async function checkOrderStatus(): Promise<void> {
    if (isChecking.value || isRedirecting.value || isTerminal.value) return;

    isChecking.value = true;

    try {
        const response = await fetch(showOrder(props.order.id).url, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });

        if (!response.ok) {
            throw new Error('Status pembayaran belum dapat dicek.');
        }

        const payload = (await response.json()) as { data: PaymentOrder };
        status.value = payload.data.status;
        statusLabel.value = payload.data.status_label;
        paymentLinkUrl.value = payload.data.payment_link_url;
        lastCheckedAt.value = new Date();
        pollError.value = null;

        if (isPaid.value) {
            redirectToLeaderboard();
        }
    } catch {
        pollError.value =
            'Pengecekan belum berhasil. Kami akan mencoba lagi otomatis.';
    } finally {
        isChecking.value = false;
    }
}

onMounted(() => {
    if (isPaid.value) {
        redirectToLeaderboard();
        return;
    }

    if (isTerminal.value) return;

    void checkOrderStatus();
    pollTimer = window.setInterval(
        () => void checkOrderStatus(),
        pollIntervalMs,
    );
});

onBeforeUnmount(stopPolling);
</script>

<template>
    <PublicLayout>
        <PublicSeo
            title="Memeriksa Pembayaran Sponsor"
            description="SuaraNetijen sedang memeriksa konfirmasi pembayaran sponsor."
            canonical-path="/sponsor/payment-status"
            robots="noindex, nofollow"
        />

        <main
            class="mx-auto flex max-w-3xl justify-center px-5 py-12 sm:px-8 sm:py-20"
        >
            <section
                class="border-line w-full rounded-3xl border bg-white p-6 text-center shadow-sm sm:p-10"
            >
                <div
                    class="mx-auto flex size-16 items-center justify-center rounded-2xl"
                    :class="
                        isPaid
                            ? 'bg-sage text-score-high'
                            : isTerminal
                              ? 'bg-danger-tint text-danger'
                              : 'bg-danger-tint text-paid'
                    "
                >
                    <CheckCircle2 v-if="isPaid" class="size-8" />
                    <AlertCircle v-else-if="isTerminal" class="size-8" />
                    <RefreshCw
                        v-else
                        class="size-8 animate-spin motion-reduce:animate-none"
                    />
                </div>

                <h1
                    class="text-ink mt-2 text-2xl font-black tracking-tight sm:text-3xl"
                >
                    {{
                        isPaid
                            ? 'Pembayaran diterima'
                            : isTerminal
                              ? 'Pembayaran belum berhasil'
                              : 'Sedang memeriksa pembayaran'
                    }}
                </h1>
                <p
                    class="text-ink-muted mx-auto mt-3 max-w-xl text-sm leading-6"
                >
                    <template v-if="isPaid">
                        Pembayaran sudah dikonfirmasi. Anda akan diarahkan ke
                        leaderboard.
                    </template>
                    <template v-else-if="isTerminal">
                        Status pembayaran saat ini: {{ statusLabel }}. Silakan
                        coba kembali atau buka leaderboard.
                    </template>
                    <template v-else>
                        Gateway pembayaran dan webhook sedang menyelesaikan
                        konfirmasi. Halaman ini mengecek otomatis setiap 10
                        detik.
                    </template>
                </p>

                <div
                    class="border-line bg-canvas mx-auto mt-8 max-w-md rounded-2xl border p-4 text-left text-sm"
                >
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-ink-muted">Entitas</span>
                        <strong class="text-ink text-right">{{
                            order.entity_name
                        }}</strong>
                    </div>
                    <div class="mt-3 flex items-center justify-between gap-4">
                        <span class="text-ink-muted">Nominal</span>
                        <strong class="text-paid">{{
                            formatRupiah(order.amount)
                        }}</strong>
                    </div>
                    <div class="mt-3 flex items-center justify-between gap-4">
                        <span class="text-ink-muted">Status</span>
                        <strong class="text-ink text-right">{{
                            statusLabel
                        }}</strong>
                    </div>
                </div>

                <p
                    v-if="isChecking"
                    class="text-ink-muted mt-5 inline-flex items-center gap-2 text-xs"
                    role="status"
                    aria-live="polite"
                >
                    <Clock class="size-3.5" /> Mengecek status terbaru...
                </p>
                <p
                    v-else-if="lastCheckedAt && !isPaid"
                    class="text-ink-muted mt-5 text-xs"
                    role="status"
                    aria-live="polite"
                >
                    Pengecekan terakhir baru saja dilakukan.
                </p>
                <p
                    v-if="pollError"
                    class="text-danger mt-3 text-xs"
                    role="alert"
                >
                    {{ pollError }}
                </p>

                <div
                    class="mt-8 flex flex-col justify-center gap-3 sm:flex-row"
                >
                    <a
                        v-if="!isPaid && paymentLinkUrl"
                        :href="paymentLinkUrl"
                        class="bg-paid-strong hover:bg-paid-deep inline-flex min-h-11 items-center justify-center gap-2 rounded-full px-5 py-3 text-sm font-bold text-white transition"
                    >
                        Kembali ke Pembayaran <ArrowRight class="size-4" />
                    </a>
                    <Link
                        v-if="!isPaid"
                        :href="leaderboardPage()"
                        class="border-line-strong text-ink hover:bg-sage inline-flex min-h-11 items-center justify-center gap-2 rounded-full border px-5 py-3 text-sm font-bold transition"
                    >
                        <Trophy class="size-4" /> Lihat Leaderboard
                    </Link>
                </div>
            </section>
        </main>
    </PublicLayout>
</template>
