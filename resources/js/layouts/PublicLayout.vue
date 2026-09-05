<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Menu, Search, X } from '@lucide/vue';
import { ref } from 'vue';
import BrandLogo from '@/components/BrandLogo.vue';
import {
    about,
    dashboard,
    home,
    login,
    methodology,
    privacy,
    register,
    sources,
    terms,
} from '@/routes';
import { index as searchPage } from '@/routes/search';

const page = usePage();
const menuOpen = ref(false);
const navigation = [
    { label: 'Jelajahi', href: searchPage() },
    { label: 'Cara kerja', href: methodology() },
    { label: 'Tentang kami', href: about() },
];
</script>

<template>
    <div
        class="public-site flex min-h-screen flex-col bg-[#fafaf7] text-[#18392d]"
    >
        <a
            href="#main-content"
            class="sr-only z-50 rounded-lg bg-white p-4 focus:not-sr-only focus:fixed focus:top-2 focus:left-2"
            >Langsung ke konten</a
        >
        <header class="relative z-40 border-b border-[#e5e9e2] bg-white">
            <div
                class="mx-auto flex h-20 max-w-6xl items-center justify-between gap-4 px-5 sm:px-8"
            >
                <Link :href="home()" aria-label="SuaraNetijen — Beranda"
                    ><BrandLogo
                /></Link>
                <nav
                    aria-label="Navigasi utama"
                    class="hidden items-center gap-8 text-sm font-semibold md:flex"
                >
                    <Link
                        v-for="item in navigation"
                        :key="item.label"
                        :href="item.href"
                        class="transition-colors hover:text-[#087f5b]"
                        :aria-current="
                            page.url.split('?')[0] === item.href.url
                                ? 'page'
                                : undefined
                        "
                        >{{ item.label }}</Link
                    >
                </nav>
                <div class="flex items-center gap-3 text-sm font-semibold">
                    <Link
                        v-if="page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-full border border-[#cbd8cf] px-4 py-2.5 hover:bg-[#edf8f0]"
                        >Dashboard</Link
                    >
                    <template v-else>
                        <Link
                            :href="login()"
                            class="hidden px-2 py-3 hover:text-[#087f5b] sm:block"
                            >Masuk</Link
                        >
                        <Link
                            :href="register()"
                            class="hidden items-center gap-2 rounded-full bg-[#d5f5df] px-5 py-3 text-[#185b3b] transition hover:bg-[#bceccb] sm:flex"
                            >Gabung <ArrowUpRight class="size-4"
                        /></Link>
                    </template>
                    <button
                        type="button"
                        class="flex size-11 items-center justify-center rounded-full border border-[#dce3db] md:hidden"
                        :aria-expanded="menuOpen"
                        aria-controls="mobile-navigation"
                        :aria-label="menuOpen ? 'Tutup menu' : 'Buka menu'"
                        @click="menuOpen = !menuOpen"
                    >
                        <X v-if="menuOpen" class="size-5" /><Menu
                            v-else
                            class="size-5"
                        />
                    </button>
                </div>
            </div>
            <nav
                v-if="menuOpen"
                id="mobile-navigation"
                aria-label="Navigasi seluler"
                class="absolute inset-x-0 top-full grid gap-1 border-b border-[#dce3db] bg-white p-5 shadow-lg md:hidden"
            >
                <Link
                    v-for="item in navigation"
                    :key="item.label"
                    :href="item.href"
                    class="rounded-lg px-4 py-3 hover:bg-[#edf8f0]"
                    @click="menuOpen = false"
                    >{{ item.label }}</Link
                >
                <template v-if="!page.props.auth.user">
                    <Link
                        :href="login()"
                        class="rounded-lg px-4 py-3 hover:bg-[#edf8f0]"
                        >Masuk</Link
                    >
                    <Link
                        :href="register()"
                        class="rounded-lg bg-[#d5f5df] px-4 py-3"
                        >Gabung SuaraNetijen</Link
                    >
                </template>
            </nav>
        </header>

        <div id="main-content" tabindex="-1" class="flex-1 outline-none">
            <slot />
        </div>

        <footer class="border-t border-[#e0e6dc] bg-[#f0f3eb]">
            <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8">
                <div class="flex flex-col justify-between gap-10 md:flex-row">
                    <div class="max-w-xs">
                        <Link :href="home()" aria-label="SuaraNetijen — Beranda"
                            ><BrandLogo tagline
                        /></Link>
                        <p class="mt-5 text-sm leading-6 text-[#68746b]">
                            Temukan gambaran opini publik tentang brand, produk,
                            dan layanan di Indonesia.
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-12 text-sm sm:gap-20">
                        <div class="flex flex-col gap-3">
                            <span class="mb-1 font-bold"
                                >Kenali lebih dekat</span
                            ><Link :href="about()">Tentang SuaraNetijen</Link
                            ><Link :href="methodology()">Metodologi</Link
                            ><Link :href="sources()">Sumber data</Link>
                        </div>
                        <div class="flex flex-col gap-3">
                            <span class="mb-1 font-bold">Mulai di sini</span
                            ><Link
                                :href="searchPage()"
                                class="inline-flex items-center gap-2"
                                ><Search class="size-4" /> Cari & jelajahi</Link
                            ><Link :href="terms()">Ketentuan penggunaan</Link
                            ><Link :href="privacy()">Privasi</Link>
                        </div>
                    </div>
                </div>
                <div
                    class="mt-10 flex flex-col justify-between gap-3 border-t border-[#dce3d7] pt-6 text-xs text-[#68746b] sm:flex-row"
                >
                    <p>© {{ new Date().getFullYear() }} SuaraNetijen</p>
                    <p>Opini publik untuk keputusan yang lebih sadar.</p>
                </div>
            </div>
        </footer>
    </div>
</template>
