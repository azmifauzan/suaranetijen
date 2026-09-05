<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    BookOpen,
    Bot,
    FolderGit2,
    FolderTree,
    HelpCircle,
    LayoutGrid,
    Radio,
    Sparkles,
    Tags,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import admin from '@/routes/admin';
import { dashboard, methodology } from '@/routes';
import type { Auth, NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const page = usePage<{ auth: Auth }>();
const isAdmin = computed(() => page.props.auth.user?.is_admin === true);

const adminNavItems: NavItem[] = [
    { title: 'Admin Overview', href: admin.dashboard(), icon: LayoutGrid },
    { title: 'Entitas', href: admin.entities.index(), icon: Tags },
    { title: 'Kategori', href: admin.categories.index(), icon: FolderTree },
    { title: 'Sumber & Kill Switch', href: admin.sources.index(), icon: Radio },
    {
        title: 'Crawl States',
        href: admin.operations.crawlStates(),
        icon: Activity,
    },
    {
        title: 'Ingestion Failures',
        href: admin.operations.ingestionFailures(),
        icon: AlertTriangle,
    },
    {
        title: 'Unmatched Mentions',
        href: admin.operations.unmatchedMentions(),
        icon: HelpCircle,
    },
    {
        title: 'Entity Candidates',
        href: admin.entityCandidates.index(),
        icon: Sparkles,
    },
    { title: 'LLM Settings', href: admin.llmSettings.edit(), icon: Bot },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Kode sumber',
        href: 'https://github.com/azmifauzan/suaranetijen',
        icon: FolderGit2,
    },
    {
        title: 'Metodologi',
        href: methodology(),
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain v-if="isAdmin" :items="adminNavItems" label="Admin" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
