import { expect, test, vi } from 'vite-plus/test';

vi.mock('@inertiajs/svelte', () => ({ page: {}, router: {} }));

const { arahTransisi } = await import('./arahTransisi');

const h = (url: string, props: Record<string, unknown> = {}) => ({
    url,
    props,
});

test('arah mengikuti kedalaman halaman', () => {
    expect(
        arahTransisi(h('/dashboard?bulan=2026-11'), h('/kegiatan/abc')),
    ).toBe('maju');
    expect(arahTransisi(h('/kegiatan/abc'), h('/dashboard'))).toBe('mundur');
    expect(arahTransisi(h('/dashboard'), h('/impor'))).toBe('setara');
    expect(arahTransisi(h('/lainnya'), h('/rkas'))).toBe('maju');
    expect(arahTransisi(h('/rkas'), h('/rkas/7'))).toBe('maju');
    expect(arahTransisi(h('/pengaturan/sekolah'), h('/lainnya'))).toBe(
        'mundur',
    );
    expect(
        arahTransisi(
            h('/dashboard?bulan=2026-10'),
            h('/dashboard?bulan=2026-11'),
        ),
    ).toBe('setara');
});

test('tombol sebelumnya dan berikutnya membalik halaman', () => {
    const tetangga = { sebelumnya: { uuid: 'a' }, berikutnya: { uuid: 'c' } };
    const lama = h('/kegiatan/b', { tetangga });

    expect(
        arahTransisi(lama, h('/kegiatan/c', { kegiatan: { uuid: 'c' } })),
    ).toBe('maju');
    expect(
        arahTransisi(lama, h('/kegiatan/a', { kegiatan: { uuid: 'a' } })),
    ).toBe('mundur');
    expect(
        arahTransisi(lama, h('/kegiatan/z', { kegiatan: { uuid: 'z' } })),
    ).toBe('setara');
});
