import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'SuaraNetijen';

void createInertiaApp({
    title: (title) => {
        if (!title) {
            return appName;
        }

        return title === appName || title.endsWith(` | ${appName}`)
            ? title
            : `${title} | ${appName}`;
    },
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
            case name.startsWith('Entities/'):
            case name.startsWith('Search/'):
            case name.startsWith('Top/'):
            case name.startsWith('Category/'):
            case name.startsWith('Pages/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
