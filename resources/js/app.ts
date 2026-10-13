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

        const withSuffix = `${title} | ${appName}`;

        // Search results cut titles at about 60 characters; drop the suffix rather than the keyword.
        return title === appName ||
            title.endsWith(` | ${appName}`) ||
            withSuffix.length > 60
            ? title
            : withSuffix;
    },
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
            case name.startsWith('Entities/'):
            case name.startsWith('Search/'):
            case name.startsWith('Top/'):
            case name.startsWith('Category/'):
            case name.startsWith('Pages/'):
            case name.startsWith('Sponsor/'):
            case name.startsWith('Topics/'):
            case name.startsWith('Comparison/'):
            case name === 'ErrorPage':
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
        color: '#087f5b',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
