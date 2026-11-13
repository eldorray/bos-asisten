import type { BarisRkas } from '@/components/rkas/FormBarisRkas.svelte';

export const BULAN = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
];

/** Sebaran volume dalam satu frasa pendek, pengganti 12 kolom bulan di tabel. */
export function ringkasBulan(volume: Record<string, number>): string {
    const isi = BULAN.map((nama, i) => ({
        nama,
        nilai: volume[String(i + 1)] ?? 0,
    })).filter((b) => b.nilai > 0);

    if (isi.length === 0) {
        return 'Belum ada volume';
    }

    if (isi.length === 12 && isi.every((b) => b.nilai === isi[0].nilai)) {
        return 'Tiap bulan';
    }

    return isi.map((b) => b.nama).join(', ');
}

export type KelompokKegiatan = {
    refKegiatanId: number;
    kode: string;
    nama: string;
    jalur: string;
    subtotal: number;
    baris: BarisRkas[];
};

/** Satu kelompok per kegiatan, urut kode; baris di dalamnya urut rekening lalu urutan. */
export function kelompokkanPerKegiatan(baris: BarisRkas[]): KelompokKegiatan[] {
    const kelompok = new Map<string, KelompokKegiatan>();

    for (const b of baris) {
        let k = kelompok.get(b.kegiatan.kode);

        if (!k) {
            k = {
                refKegiatanId: b.ref_kegiatan_id,
                kode: b.kegiatan.kode,
                nama: b.kegiatan.uraian,
                jalur: [b.kegiatan.program, b.kegiatan.induk]
                    .filter((r) => r !== null)
                    .map((r) => `${r.kode} ${r.uraian}`)
                    .join(' › '),
                subtotal: 0,
                baris: [],
            };
            kelompok.set(b.kegiatan.kode, k);
        }

        k.subtotal += b.jumlah;
        k.baris.push(b);
    }

    for (const k of kelompok.values()) {
        k.baris.sort(
            (x, y) =>
                x.rekening.kode.localeCompare(y.rekening.kode) ||
                x.urutan - y.urutan ||
                x.id - y.id,
        );
    }

    return [...kelompok.values()].sort((x, y) => x.kode.localeCompare(y.kode));
}
