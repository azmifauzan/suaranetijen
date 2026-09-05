<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Search, ShieldCheck, Star } from '@lucide/vue';
import { computed } from 'vue';
import { about, dashboard, methodology } from '@/routes';
import { index as searchPage } from '@/routes/search';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const page = usePage();
const userName = computed(
    () => page.props.auth.user?.name?.split(' ')[0] ?? 'Netijen',
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <section
            class="relative overflow-hidden rounded-3xl border border-[#cfe3c6] bg-[#eaf3df] p-7 sm:p-10"
        >
            <div class="relative z-10 max-w-2xl">
                <p class="text-sm font-semibold tracking-wide text-[#087f5b]">
                    Selamat datang di SuaraNetijen
                </p>
                <h1
                    class="mt-3 text-3xl font-bold tracking-tight text-[#18392d] sm:text-4xl"
                >
                    Halo, {{ userName }}. Siap cek suara netizen?
                </h1>
                <p
                    class="mt-4 max-w-xl text-sm leading-6 text-[#68746b] sm:text-base"
                >
                    Cari brand, produk, atau layanan untuk melihat sentimen
                    publik, tema yang paling sering dibicarakan, dan rating
                    pengguna.
                </p>
                <Link
                    :href="searchPage()"
                    class="mt-7 inline-flex items-center gap-2 rounded-full bg-[#087f5b] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#066446]"
                >
                    Mulai menjelajah <ArrowRight class="size-4" />
                </Link>
            </div>
            <div
                class="absolute -right-16 -bottom-24 size-64 rounded-full bg-[#c9e7c5]/70 sm:size-80"
                aria-hidden="true"
            />
            <div
                class="absolute top-8 right-12 hidden size-20 rounded-full border-8 border-[#d6ecd1] sm:block"
                aria-hidden="true"
            />
        </section>

        <section
            class="grid gap-4 md:grid-cols-3"
            aria-label="Metrik SuaraNetijen"
        >
            <div class="rounded-2xl border border-[#dbe7d2] bg-white p-6">
                <div
                    class="flex size-11 items-center justify-center rounded-xl bg-[#d5f5df] text-[#087f5b]"
                >
                    <Search class="size-5" />
                </div>
                <h2 class="mt-5 font-bold text-[#18392d]">Cari opini publik</h2>
                <p class="mt-2 text-sm leading-6 text-[#68746b]">
                    Temukan entitas dan lihat rangkuman opini yang relevan.
                </p>
            </div>
            <div class="rounded-2xl border border-[#dbe7d2] bg-white p-6">
                <div
                    class="flex size-11 items-center justify-center rounded-xl bg-[#eaf3df] text-[#6d8c56]"
                >
                    <ShieldCheck class="size-5" />
                </div>
                <h2 class="mt-5 font-bold text-[#18392d]">Sentimen terpisah</h2>
                <p class="mt-2 text-sm leading-6 text-[#68746b]">
                    Sentimen publik dan rating pengguna dihitung sebagai metrik
                    berbeda.
                </p>
            </div>
            <div class="rounded-2xl border border-[#dbe7d2] bg-white p-6">
                <div
                    class="flex size-11 items-center justify-center rounded-xl bg-[#fbf0df] text-[#b3935a]"
                >
                    <Star class="size-5" />
                </div>
                <h2 class="mt-5 font-bold text-[#18392d]">Baca dengan sadar</h2>
                <p class="mt-2 text-sm leading-6 text-[#68746b]">
                    Kenali sumber dan cara kerja indeks sebelum mengambil
                    keputusan.
                </p>
            </div>
        </section>

        <section
            class="flex flex-col justify-between gap-5 rounded-2xl border border-[#dbe7d2] bg-[#f0f3eb] p-6 sm:flex-row sm:items-center sm:p-7"
        >
            <div>
                <h2 class="font-bold text-[#18392d]">
                    Ingin tahu cara kerjanya?
                </h2>
                <p class="mt-2 text-sm leading-6 text-[#68746b]">
                    Pelajari metodologi dan prinsip transparansi SuaraNetijen.
                </p>
            </div>
            <div class="flex flex-wrap gap-3 text-sm font-semibold">
                <Link
                    :href="methodology()"
                    class="inline-flex items-center gap-2 rounded-full border border-[#b8cba9] bg-white px-4 py-2.5 text-[#185b3b] hover:bg-[#eaf3df]"
                >
                    Metodologi <ArrowRight class="size-4" />
                </Link>
                <Link
                    :href="about()"
                    class="inline-flex items-center gap-2 rounded-full px-4 py-2.5 text-[#087f5b] hover:bg-white"
                >
                    Tentang kami
                </Link>
            </div>
        </section>
    </div>
</template>
