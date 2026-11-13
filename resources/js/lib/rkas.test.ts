import { expect, test } from 'vite-plus/test';
import type { BarisRkas } from '@/components/rkas/FormBarisRkas.svelte';
import { jenisPtkKegiatan, kelompokkanPerKegiatan, ringkasBulan } from './rkas';

const vol = (isi: Record<number, number>): Record<string, number> =>
    Object.fromEntries(
        Array.from({ length: 12 }, (_, i) => [String(i + 1), isi[i + 1] ?? 0]),
    );

test('sebaran bulan diringkas jadi frasa', () => {
    expect(ringkasBulan(vol({}))).toBe('Belum ada volume');
    expect(ringkasBulan(vol({ 1: 20, 7: 20 }))).toBe('Jan, Jul');
    expect(
        ringkasBulan(
            Object.fromEntries(
                Array.from({ length: 12 }, (_, i) => [String(i + 1), 1]),
            ),
        ),
    ).toBe('Tiap bulan');
    // Dua belas bulan terisi tetapi tidak rata tetap disebut satu per satu.
    expect(
        ringkasBulan({
            ...vol({}),
            ...Object.fromEntries(
                Array.from({ length: 12 }, (_, i) => [String(i + 1), i + 1]),
            ),
        }),
    ).toContain('Jan, Feb');
});

test('baris dikelompokkan per kegiatan dengan subtotal', () => {
    const baris = (
        id: number,
        kode: string,
        rekening: string,
        jumlah: number,
    ) =>
        ({
            id,
            urutan: id,
            ref_kegiatan_id: Number(kode.replaceAll('.', '')),
            jumlah,
            kegiatan: {
                kode,
                uraian: `Kegiatan ${kode}`,
                program: { kode: kode.slice(0, 2), uraian: 'Program' },
                induk: null,
            },
            rekening: { kode: rekening, nama: 'Rekening' },
        }) as unknown as BarisRkas;

    const hasil = kelompokkanPerKegiatan([
        baris(1, '03.01.01', '5.2', 100),
        baris(2, '02.03.01', '5.1', 50),
        baris(3, '03.01.01', '5.1', 25),
    ]);

    expect(hasil.map((k) => k.kode)).toEqual(['02.03.01', '03.01.01']);
    expect(hasil[1].subtotal).toBe(125);
    expect(hasil[1].baris.map((b) => b.id)).toEqual([3, 1]);
    expect(hasil[1].jalur).toBe('03 Program');
});

test('jenis PTK diambil dari digit terakhir kode kegiatan honor', () => {
    expect(jenisPtkKegiatan('07.12.03.')).toBe(3);
    expect(jenisPtkKegiatan('07.12.09.')).toBeNull();
    expect(jenisPtkKegiatan('07.')).toBeNull();
});
