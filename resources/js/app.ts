import { createInertiaApp, router } from '@inertiajs/svelte';
import { toast } from 'svelte-sonner';
import AppLayout from '@/layouts/AppLayout.svelte';
import AuthLayout from '@/layouts/AuthLayout.svelte';
import SettingsLayout from '@/layouts/settings/Layout.svelte';
import { antreanFoto } from '@/lib/antreanFoto.svelte';
import { initializeFlashToast } from '@/lib/flash-toast';
import { pasangAplikasi } from '@/lib/pasangAplikasi.svelte';
import { initializeTheme } from '@/lib/theme.svelte';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
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

if (typeof window !== 'undefined') {
    void antreanFoto.mulai();
    pasangAplikasi.mulai();

    router.on('networkError', () => {
        toast.error('Tidak ada sinyal. Coba lagi saat online.');
    });

    // Di dev, Vite menyajikan modul sendiri; service worker hanya untuk hasil build.
    if (import.meta.env.PROD && 'serviceWorker' in navigator) {
        void navigator.serviceWorker
            .register('/sw.js')
            .then(() => navigator.serviceWorker.ready)
            .then((sw) => sw.active?.postMessage('segarkan-aset'));
    }
}
