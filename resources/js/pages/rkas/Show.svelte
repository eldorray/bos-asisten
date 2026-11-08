<script module lang="ts">
    import KertasKerjaController from '@/actions/App/Http/Controllers/Rkas/KertasKerjaController';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Draf RKAS',
                href: KertasKerjaController.index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import FormBarisRkas from '@/components/rkas/FormBarisRkas.svelte';
    import type {
        BarisRkas,
        Pilihan,
    } from '@/components/rkas/FormBarisRkas.svelte';
    import TabelBarisRkas from '@/components/rkas/TabelBarisRkas.svelte';
    import FormKertasKerja from '@/components/rkas/FormKertasKerja.svelte';
    import type { SumberDana } from '@/components/rkas/FormKertasKerja.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Card, CardContent } from '@/components/ui/card';

    type KertasKerja = {
        id: number;
        label: string;
        tahun: number;
        sumber_dana: SumberDana;
        pagu: number;
        status: string;
        status_label: string;
        jenis_versi: string;
        versi: string;
        nomor_pergeseran: number;
        nomor_perubahan: number;
        tanggal_revisi: string | null;
        bisa_diubah: boolean;
        sekolah: { id: number; nama: string; identitas: string };
    };
    type PemberiDana = { id: number; nama: string; jumlah: number };

    let {
        kertasKerja,
        pemberiDana,
        baris,
        pilihan,
    }: {
        kertasKerja: KertasKerja;
        pemberiDana: PemberiDana[];
        baris: BarisRkas[];
        pilihan: Pilihan;
    } = $props();

    let sedangDiubah = $state<BarisRkas | null>(null);

    const namaSumber = $derived(
        kertasKerja.sumber_dana === 'lainnya' ? 'Lainnya' : 'BOSP Reguler',
    );

    function rupiah(n: number): string {
        return `Rp ${n.toLocaleString('id-ID')}`;
    }
</script>

<AppHead title={`${kertasKerja.label} ${kertasKerja.tahun}`} />

<div class="flex flex-col gap-5 p-5 md:p-7">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <Heading
            title={`${kertasKerja.label} ${kertasKerja.tahun}`}
            description={`${kertasKerja.sekolah.nama} (${kertasKerja.sekolah.identitas}) · ${namaSumber} · ${kertasKerja.versi}`}
        />
        <div class="flex items-center gap-2.5">
            <Badge
                variant={kertasKerja.status === 'disahkan'
                    ? 'default'
                    : 'secondary'}
            >
                {kertasKerja.status_label}
            </Badge>
            <Link
                href={KertasKerjaController.index({
                    query: {
                        tahun: kertasKerja.tahun,
                        sekolah_id: kertasKerja.sekolah.id,
                    },
                })}
                class="text-sm underline-offset-4 hover:underline"
            >
                Kembali ke daftar
            </Link>
        </div>
    </div>

    <Card>
        <CardContent class="grid gap-4">
            <div class="flex items-baseline justify-between">
                <span class="text-sm text-muted-foreground">Pagu</span>
                <span class="text-xl font-semibold tabular-nums">
                    {rupiah(kertasKerja.pagu)}
                </span>
            </div>

            {#if kertasKerja.sumber_dana === 'lainnya' && !kertasKerja.bisa_diubah}
                <ul class="grid gap-1 text-sm">
                    {#each pemberiDana as pemberi (pemberi.id)}
                        <li class="flex justify-between">
                            <span>{pemberi.nama}</span>
                            <span class="tabular-nums">{rupiah(pemberi.jumlah)}</span>
                        </li>
                    {/each}
                </ul>
            {/if}

            {#if kertasKerja.bisa_diubah}
                {#key kertasKerja.id}
                    <FormKertasKerja
                        sekolahId={kertasKerja.sekolah.id}
                        tahun={kertasKerja.tahun}
                        pilihanSumberDana={[]}
                        kertasKerjaId={kertasKerja.id}
                        sumberDana={kertasKerja.sumber_dana}
                        pagu={kertasKerja.pagu}
                        pemberiDana={pemberiDana.map((p) => ({
                            nama: p.nama,
                            jumlah: p.jumlah,
                        }))}
                    />
                {/key}
            {/if}
        </CardContent>
    </Card>

    {#if kertasKerja.bisa_diubah}
        <Card>
            <CardContent class="grid gap-3">
                <h2 class="text-base font-semibold">
                    {sedangDiubah ? 'Ubah baris belanja' : 'Tambah baris belanja'}
                </h2>
                {#key `${kertasKerja.id}-${sedangDiubah?.id ?? 'baru'}`}
                    <FormBarisRkas
                        kertasKerjaId={kertasKerja.id}
                        {pilihan}
                        baris={sedangDiubah}
                        onSelesai={() => (sedangDiubah = null)}
                    />
                {/key}
            </CardContent>
        </Card>
    {/if}

    <TabelBarisRkas
        {baris}
        bisaDiubah={kertasKerja.bisa_diubah}
        onUbah={(b) => (sedangDiubah = b)}
    />

    <!-- Task 6: ringkasan dan pemeriksaan juknis. -->
</div>
