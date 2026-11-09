<script module lang="ts">
    import ReferensiRkasController from '@/actions/App/Http/Controllers/Rkas/ReferensiRkasController';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Referensi RKAS',
                href: ReferensiRkasController.index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import PilihCari from '@/components/PilihCari.svelte';
    import FormReferensi from '@/components/rkas/FormReferensi.svelte';
    import type { JenisReferensi } from '@/components/rkas/FormReferensi.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type Baris = Record<string, string | number | null>;
    type Halaman = {
        data: Baris[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    type Sekolah = { id: number; uuid: string; nama: string };

    let {
        jenis,
        tahun,
        sekolah,
        pilihanSekolah,
        daftar,
        dapatDisalin,
        pilihanJenisPtk,
    }: {
        jenis: JenisReferensi;
        tahun: number;
        sekolah: Sekolah;
        pilihanSekolah: Sekolah[];
        daftar: Halaman;
        dapatDisalin: boolean;
        pilihanJenisPtk: { nilai: number; nama: string }[];
    } = $props();

    const tab: { jenis: JenisReferensi; label: string }[] = [
        { jenis: 'kegiatan', label: 'Kegiatan' },
        { jenis: 'rekening', label: 'Rekening' },
        { jenis: 'barang', label: 'Barang acuan' },
        { jenis: 'satuan', label: 'Satuan' },
        { jenis: 'ptk', label: 'PTK' },
    ];

    const kolom: Record<JenisReferensi, { kunci: string; label: string }[]> = {
        kegiatan: [
            { kunci: 'kode', label: 'Kode' },
            { kunci: 'uraian', label: 'Uraian' },
            { kunci: 'level', label: 'Level' },
            { kunci: 'kedaluwarsa', label: 'Kedaluwarsa' },
        ],
        rekening: [
            { kunci: 'kode', label: 'Kode' },
            { kunci: 'nama', label: 'Nama' },
            { kunci: 'kedaluwarsa', label: 'Kedaluwarsa' },
        ],
        barang: [
            { kunci: 'nama', label: 'Nama' },
            { kunci: 'kode_rekening', label: 'Rekening' },
            { kunci: 'satuan', label: 'Satuan' },
            { kunci: 'harga', label: 'Harga' },
            { kunci: 'batas_bawah', label: 'Batas bawah' },
            { kunci: 'batas_atas', label: 'Batas atas' },
            { kunci: 'kedaluwarsa', label: 'Kedaluwarsa' },
        ],
        satuan: [{ kunci: 'nama', label: 'Nama' }],
        ptk: [
            { kunci: 'nama', label: 'Nama' },
            { kunci: 'nuptk', label: 'NUPTK' },
            { kunci: 'jenis', label: 'Jenis' },
        ],
    };

    const angka = ['harga', 'batas_bawah', 'batas_atas'];

    function tampil(kunci: string, nilai: string | number | null): string {
        if (nilai === null || nilai === '') {
            return '-';
        }
        if (kunci === 'jenis' && jenis === 'ptk') {
            return (
                pilihanJenisPtk.find((p) => p.nilai === nilai)?.nama ??
                String(nilai)
            );
        }
        if (angka.includes(kunci)) {
            return Number(nilai).toLocaleString('id-ID');
        }
        return String(nilai);
    }

    function bukaHalaman(
        ubah: Partial<{ jenis: string; tahun: number; sekolah: string }>,
    ): void {
        router.get(
            ReferensiRkasController.index({
                query: {
                    jenis,
                    tahun,
                    sekolah: sekolah.uuid,
                    ...ubah,
                },
            }).url,
        );
    }

    function hapus(uuid: string): void {
        if (!confirm('Hapus referensi ini?')) {
            return;
        }
        router.delete(
            ReferensiRkasController.destroy({ jenis, uuid }).url,
            { preserveScroll: true },
        );
    }

    function salin(): void {
        router.post(
            ReferensiRkasController.salin().url,
            { dari: tahun - 1, ke: tahun },
            { preserveScroll: true },
        );
    }

    function muatKegiatan(): void {
        router.post(
            ReferensiRkasController.muatKegiatan().url,
            { tahun },
            { preserveScroll: true },
        );
    }

    const errors = $derived(page.props.errors as Record<string, string>);
    const perluTahun = $derived(jenis !== 'satuan' && jenis !== 'ptk');
</script>

<AppHead title="Referensi RKAS" />

<div class="flex flex-col gap-5 p-5 md:p-7">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <Heading
            title="Referensi RKAS"
            description="Kegiatan, rekening, barang acuan, satuan, dan PTK untuk draf RKAS."
        />

        <div class="flex flex-wrap items-center gap-2.5">
            {#if jenis === 'ptk' && pilihanSekolah.length > 1}
                <Label for="sekolah" class="shrink-0">Sekolah</Label>
                <PilihCari
                    id="sekolah"
                    class="w-56"
                    opsi={pilihanSekolah.map((p) => ({ value: p.uuid, label: p.nama }))}
                    value={sekolah.uuid}
                    onchange={(v) => bukaHalaman({ sekolah: String(v) })}
                />
            {/if}
            {#if perluTahun}
                <Label for="tahun" class="shrink-0">Tahun anggaran</Label>
                <Input
                    id="tahun"
                    class="w-24"
                    type="number"
                    min="2000"
                    max="2100"
                    value={tahun}
                    onchange={(e) =>
                        bukaHalaman({
                            tahun: Number((e.target as HTMLInputElement).value),
                        })}
                />
            {/if}
        </div>
    </div>

    <nav class="flex flex-wrap gap-1.5" aria-label="Jenis referensi">
        {#each tab as t (t.jenis)}
            <Link
                href={ReferensiRkasController.index({
                    query: { jenis: t.jenis, tahun, sekolah: sekolah.uuid },
                })}
                class={[
                    'rounded-md border px-3 py-1.5 text-sm font-medium',
                    t.jenis === jenis
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-background hover:bg-muted',
                ]}
                aria-current={t.jenis === jenis ? 'page' : undefined}
            >
                {t.label}
            </Link>
        {/each}
    </nav>

    {#if dapatDisalin}
        <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/50 p-3 text-sm"
        >
            <span>Belum ada data untuk {tahun}.</span>
            <Button variant="outline" onclick={salin}>
                Salin dari {tahun - 1}
            </Button>
        </div>
    {/if}

    {#if jenis === 'kegiatan' && daftar.total === 0}
        <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/50 p-3 text-sm"
        >
            <span>
                Kode kegiatan BOS {tahun} belum ada. Muat dari tabel resmi ARKAS 4
                (151 kegiatan).
            </span>
            <Button variant="outline" onclick={muatKegiatan}>
                Muat kode resmi ARKAS
            </Button>
        </div>
    {/if}

    <InputError message={errors.hapus} />
    <InputError message={errors.ke} />

    <Card>
        <CardContent class="grid gap-5">
            {#key `${jenis}-${tahun}-${sekolah.id}`}
                <FormReferensi
                    {jenis}
                    {tahun}
                    sekolahId={sekolah.id}
                    {pilihanJenisPtk}
                />
            {/key}

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-muted-foreground">
                            {#each kolom[jenis] as k (k.kunci)}
                                <th class="px-2 py-2 font-medium">{k.label}</th>
                            {/each}
                            <th class="w-10 px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each daftar.data as baris (baris.id)}
                            <tr class="border-b last:border-0">
                                {#each kolom[jenis] as k (k.kunci)}
                                    <td
                                        class={[
                                            'px-2 py-2',
                                            angka.includes(k.kunci) &&
                                                'text-right tabular-nums',
                                        ]}
                                    >
                                        {tampil(k.kunci, baris[k.kunci])}
                                    </td>
                                {/each}
                                <td class="px-2 py-2 text-right">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Hapus"
                                        onclick={() => hapus(String(baris.uuid))}
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td
                                    class="px-2 py-6 text-center text-muted-foreground"
                                    colspan={kolom[jenis].length + 1}
                                >
                                    Belum ada data.
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            {#if daftar.last_page > 1}
                <div
                    class="flex items-center justify-between text-sm text-muted-foreground"
                >
                    <span>
                        Halaman {daftar.current_page} dari {daftar.last_page}
                        ({daftar.total} data)
                    </span>
                    <div class="flex gap-2">
                        {#if daftar.prev_page_url}
                            <Button
                                variant="outline"
                                size="sm"
                                onclick={() =>
                                    router.get(daftar.prev_page_url ?? '')}
                            >
                                Sebelumnya
                            </Button>
                        {/if}
                        {#if daftar.next_page_url}
                            <Button
                                variant="outline"
                                size="sm"
                                onclick={() =>
                                    router.get(daftar.next_page_url ?? '')}
                            >
                                Berikutnya
                            </Button>
                        {/if}
                    </div>
                </div>
            {/if}
        </CardContent>
    </Card>
</div>
