import { flushSync } from 'svelte';
import { setArah } from '@/lib/arahTransisi';

/**
 * Perubahan tampilan di halaman yang sama, seperti ganti tab, memakai gerak yang
 * sama dengan pindah halaman. Tanpa dukungan browser, perubahan langsung terjadi.
 */
export function transisi(ubah: () => void): void {
    if (typeof document === 'undefined' || !document.startViewTransition) {
        ubah();

        return;
    }

    setArah('setara');
    document.startViewTransition(() => {
        ubah();
        flushSync();
    });
}
