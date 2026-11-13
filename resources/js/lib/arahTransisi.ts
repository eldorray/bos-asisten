import { page, router } from '@inertiajs/svelte';

export type Arah = 'maju' | 'mundur' | 'setara';

type Halaman = { url: string; props: Record<string, unknown> };

const HALAMAN_TAB = ['/dashboard', '/impor', '/lainnya'];

// ponytail: kedalaman dibaca dari URL (tab = 0, selain itu jumlah segmen), jadi tidak ada daftar halaman yang harus dirawat.
function kedalaman(url: string): number {
    const jalur = new URL(url, 'http://x').pathname.replace(/\/+$/, '');

    return HALAMAN_TAB.includes(jalur) ? 0 : jalur.split('/').length - 1;
}

/**
 * Arah gerak pindah halaman di HP: masuk lebih dalam bergeser dari kanan,
 * kembali bergeser ke kanan, dan halaman setingkat hanya berganti.
 */
export function arahTransisi(lama: Halaman, baru: Halaman): Arah {
    const selisih = kedalaman(baru.url) - kedalaman(lama.url);

    if (selisih !== 0) {
        return selisih > 0 ? 'maju' : 'mundur';
    }

    // Tombol sebelumnya/berikutnya di rincian kegiatan bergeser seperti membalik halaman.
    const tetangga = lama.props.tetangga as
        | Record<'sebelumnya' | 'berikutnya', { uuid: string } | null>
        | null
        | undefined;
    const tujuan = (baru.props.kegiatan as { uuid?: string } | undefined)?.uuid;

    if (tujuan !== undefined && tetangga?.berikutnya?.uuid === tujuan) {
        return 'maju';
    }

    if (tujuan !== undefined && tetangga?.sebelumnya?.uuid === tujuan) {
        return 'mundur';
    }

    return 'setara';
}

export function setArah(arah: Arah): void {
    document.documentElement.dataset.arah = arah;
}

export function mulaiArahTransisi(): void {
    router.on('beforeUpdate', (event) => {
        const baru = event.detail.page;

        setArah(arahTransisi({ url: page.url, props: page.props }, baru));
        // Potret halaman lama ditempel di posisi halaman baru; geseran ini menjaganya tetap di posisi gulirnya.
        document.documentElement.style.setProperty(
            '--gulir-lama',
            `${window.scrollY}px`,
        );
    });
}
