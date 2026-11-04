<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, Search, X } from '@lucide/vue';
import { ref } from 'vue';
import BrandLogo from '@/components/BrandLogo.vue';
import {
    about,
    contact,
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
import { index as rankingIndex } from '@/routes/rankings';
import { index as comparisonsIndex } from '@/routes/comparisons';
import { index as topicsIndex } from '@/routes/topics';
import { index as leaderboardPage } from '@/routes/leaderboard';
import { index as blogIndex } from '@/routes/blog';
import SocialLinks from '@/components/SocialLinks.vue';

const page = usePage();
const menuOpen = ref(false);
const navigation = [
    { label: 'Jelajahi', href: searchPage() },
    { label: 'Ranking', href: rankingIndex() },
    { label: 'Papan Sponsor', href: leaderboardPage() },
    { label: 'Blog', href: blogIndex() },
];
</script>

<template>
    <div class="public-site bg-canvas text-ink flex min-h-screen flex-col">
        <a
            href="#main-content"
            class="sr-only z-50 rounded-lg bg-white p-4 focus:not-sr-only focus:fixed focus:top-2 focus:left-2"
            >Langsung ke konten</a
        >
        <header class="border-line relative z-40 border-b bg-white">
            <div
                class="mx-auto flex h-20 max-w-6xl items-center justify-between gap-4 px-5 sm:px-8"
            >
                <Link :href="home()" aria-label="suaranetijen. Beranda"
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
                        class="hover:text-brand transition-colors"
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
                        v-if="page.props.auth?.user"
                        :href="dashboard()"
                        class="border-line hover:bg-sage hidden rounded-full border px-4 py-2.5 sm:block"
                        >Dashboard</Link
                    >
                    <template v-else>
                        <Link
                            :href="login()"
                            class="hover:text-brand hidden px-2 py-3 sm:block"
                            >Masuk</Link
                        >
                        <Link
                            :href="register()"
                            class="bg-brand-tint text-brand-deep hover:bg-brand-tint-strong hidden items-center rounded-full px-5 py-3 transition sm:flex"
                            >Gabung</Link
                        >
                    </template>
                    <button
                        type="button"
                        class="border-line flex size-11 items-center justify-center rounded-full border md:hidden"
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
                class="border-line absolute inset-x-0 top-full grid gap-1 border-b bg-white p-5 shadow-lg md:hidden"
            >
                <Link
                    v-for="item in navigation"
                    :key="item.label"
                    :href="item.href"
                    class="hover:bg-sage rounded-lg px-4 py-3"
                    @click="menuOpen = false"
                    >{{ item.label }}</Link
                >
                <template v-if="!page.props.auth?.user">
                    <Link
                        :href="login()"
                        class="hover:bg-sage rounded-lg px-4 py-3"
                        >Masuk</Link
                    >
                    <Link
                        :href="register()"
                        class="bg-brand-tint rounded-lg px-4 py-3"
                        >Gabung SuaraNetijen</Link
                    >
                </template>
                <Link
                    v-else
                    :href="dashboard()"
                    class="hover:bg-sage rounded-lg px-4 py-3"
                    >Dashboard</Link
                >
            </nav>
        </header>

        <div id="main-content" tabindex="-1" class="flex-1 outline-none">
            <slot />
        </div>

        <footer class="border-line bg-paper border-t">
            <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8">
                <div class="flex flex-col justify-between gap-10 md:flex-row">
                    <div class="max-w-xs">
                        <Link :href="home()" aria-label="suaranetijen. Beranda"
                            ><BrandLogo tagline
                        /></Link>
                        <p class="mt-5 text-sm leading-6 text-neutral-500">
                            Temukan gambaran opini publik tentang brand, produk,
                            dan layanan di Indonesia.
                        </p>
                        <SocialLinks class="mt-6" />
                    </div>
                    <div class="grid grid-cols-2 gap-12 text-sm sm:gap-20">
                        <div class="flex flex-col gap-3">
                            <span class="mb-1 font-bold"
                                >Kenali lebih dekat</span
                            ><Link :href="about()">Tentang SuaraNetijen</Link
                            ><Link :href="methodology()">Metodologi</Link
                            ><Link :href="sources()">Sumber data</Link
                            ><Link :href="contact()">Kontak dan koreksi</Link
                            ><a
                                href="https://github.com/azmifauzan/suaranetijen"
                                target="_blank"
                                rel="noopener noreferrer"
                                >Kode sumber di GitHub<span class="sr-only">
                                    (buka di tab baru)</span
                                ></a
                            >
                            <a
                                href="https://status.suaranetijen.id"
                                target="_blank"
                                rel="noopener noreferrer"
                                >Status layanan<span class="sr-only">
                                    (buka di tab baru)</span
                                ></a
                            >
                        </div>
                        <div class="flex flex-col gap-3">
                            <span class="mb-1 font-bold">Mulai di sini</span
                            ><Link
                                :href="searchPage()"
                                class="inline-flex items-center gap-2"
                                ><Search class="size-4" /> Cari & jelajahi</Link
                            ><Link :href="topicsIndex()">Topik populer</Link
                            ><Link :href="comparisonsIndex()">Perbandingan</Link
                            ><Link :href="leaderboardPage()"
                                >Pasang sponsor</Link
                            ><Link :href="terms()">Ketentuan penggunaan</Link
                            ><Link :href="privacy()">Privasi</Link>
                        </div>
                    </div>
                </div>
                <div
                    class="border-line mt-10 flex flex-col justify-between gap-3 border-t pt-6 text-xs text-neutral-500 sm:flex-row"
                >
                    <p>© {{ new Date().getFullYear() }} SuaraNetijen</p>
                    <p>Opini publik untuk keputusan yang lebih sadar.</p>
                </div>
            </div>
        </footer>
    </div>
</template>
