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
    import { Link, router } from '@inertiajs/svelte';
    import { toast } from 'svelte-sonner';
    import StatusKertasKerjaController from '@/actions/App/Http/Controllers/Rkas/StatusKertasKerjaController';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import FormBarisRkas from '@/components/rkas/FormBarisRkas.svelte';
    import type {
        BarisRkas,
        Pilihan,
    } from '@/components/rkas/FormBarisRkas.svelte';
    import HasilPemeriksaan from '@/components/rkas/HasilPemeriksaan.svelte';
    import type { HasilPemeriksaan as Pemeriksaan } from '@/components/rkas/HasilPemeriksaan.svelte';
    import RingkasanRkas from '@/components/rkas/RingkasanRkas.svelte';
    import type { Ringkasan } from '@/components/rkas/RingkasanRkas.svelte';
    import TabelBarisRkas from '@/components/rkas/TabelBarisRkas.svelte';
    import FormKertasKerja from '@/components/rkas/FormKertasKerja.svelte';
    import type { SumberDana } from '@/components/rkas/FormKertasKerja.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
        DialogTrigger,
    } from '@/components/ui/dialog';

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
        ringkasan,
    }: {
        kertasKerja: KertasKerja;
        pemberiDana: PemberiDana[];
        baris: BarisRkas[];
        pilihan: Pilihan;
        ringkasan: Ringkasan;
    } = $props();

    let sedangDiubah = $state<BarisRkas | null>(null);

    const namaSumber = $derived(
        kertasKerja.sumber_dana === 'lainnya' ? 'Lainnya' : 'BOSP Reguler',
    );

    let pemeriksaan = $state<Pemeriksaan | null>(null);
    let sedangMengubahStatus = $state(false);
    let dialogDisahkan = $state(false);

    function ubahStatus(rute: { url: string }): void {
        pemeriksaan = null;
        router.post(
            rute.url,
            {},
            {
                preserveScroll: true,
                onStart: () => (sedangMengubahStatus = true),
                onFinish: () => (sedangMengubahStatus = false),
                onFlash: (flash) => {
                    const hasil = (flash as { pemeriksaan?: Pemeriksaan })
                        .pemeriksaan;
                    pemeriksaan = hasil ?? null;
                },
                onError: (errors) => {
                    const pesan = errors.kertas_kerja;
                    if (pesan) {
                        toast.error(pesan);
                    }
                },
            },
        );
    }

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
        <div class="flex flex-wrap items-center gap-2.5">
            {#if kertasKerja.status === 'draf'}
                <Button
                    disabled={sedangMengubahStatus}
                    onclick={() =>
                        ubahStatus({
                            url: StatusKertasKerjaController.siap.url(
                                kertasKerja.id,
                            ),
                        })}
                >
                    Nyatakan Siap
                </Button>
            {:else if kertasKerja.status === 'siap'}
                <Button
                    variant="secondary"
                    disabled={sedangMengubahStatus}
                    onclick={() =>
                        ubahStatus({
                            url: StatusKertasKerjaController.draf.url(
                                kertasKerja.id,
                            ),
                        })}
                >
                    Kembalikan ke Draf
                </Button>
                <Dialog bind:open={dialogDisahkan}>
                    <DialogTrigger asChild>
                        {#snippet children(props)}
                            <Button
                                disabled={sedangMengubahStatus}
                                onclick={props.onClick}
                            >
                                Tandai Disahkan
                            </Button>
                        {/snippet}
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Tandai disahkan</DialogTitle>
                        <DialogDescription>
                            Setelah disahkan, kertas kerja menjadi RKAS dan
                            tidak bisa diubah lagi. Lanjutkan?
                        </DialogDescription>
                        <DialogFooter>
                            <DialogClose asChild>
                                {#snippet children(props)}
                                    <Button
                                        variant="secondary"
                                        onclick={props.onClick}
                                    >
                                        Batal
                                    </Button>
                                {/snippet}
                            </DialogClose>
                            <Button
                                onclick={() => {
                                    dialogDisahkan = false;
                                    ubahStatus({
                                        url: StatusKertasKerjaController.disahkan.url(
                                            kertasKerja.id,
                                        ),
                                    });
                                }}
                            >
                                Ya, tandai disahkan
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            {/if}
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

    {#if pemeriksaan}
        <HasilPemeriksaan hasil={pemeriksaan} />
    {/if}

    <RingkasanRkas {ringkasan} />

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
</div>
