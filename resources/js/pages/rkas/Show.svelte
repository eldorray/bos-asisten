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
    import PerbandinganController from '@/actions/App/Http/Controllers/Rkas/PerbandinganController';
    import PdfKertasKerjaController from '@/actions/App/Http/Controllers/Rkas/PdfKertasKerjaController';
    import StatusKertasKerjaController from '@/actions/App/Http/Controllers/Rkas/StatusKertasKerjaController';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import DialogRevisi from '@/components/rkas/DialogRevisi.svelte';
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
    import Plus from '@lucide/svelte/icons/plus';
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
        uuid: string;
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
        bisa_direvisi: boolean;
        sekolah: { id: number; uuid: string; nama: string; identitas: string };
    };
    type PemberiDana = { id: number; nama: string; jumlah: number };
    type VersiRantai = {
        id: number;
        uuid: string;
        versi: string;
        status_label: string;
        tanggal_revisi: string | null;
    };

    let {
        kertasKerja,
        pemberiDana,
        rantai,
        baris,
        pilihan,
        ringkasan,
    }: {
        kertasKerja: KertasKerja;
        pemberiDana: PemberiDana[];
        rantai: VersiRantai[];
        baris: BarisRkas[];
        pilihan: Pilihan;
        ringkasan: Ringkasan;
    } = $props();

    let sedangDiubah = $state<BarisRkas | null>(null);
    let dialogBaris = $state(false);

    function bukaFormBaris(b: BarisRkas | null): void {
        sedangDiubah = b;
        dialogBaris = true;
    }

    function tutupFormBaris(buka: boolean): void {
        if (!buka) {
            sedangDiubah = null;
        }
    }

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
                                kertasKerja.uuid,
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
                                kertasKerja.uuid,
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
                                            kertasKerja.uuid,
                                        ),
                                    });
                                }}
                            >
                                Ya, tandai disahkan
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            {:else if kertasKerja.bisa_direvisi}
                {#key kertasKerja.id}
                    <DialogRevisi
                        kertasKerjaUuid={kertasKerja.uuid}
                        jenis="pergeseran"
                        paguAwal={kertasKerja.pagu}
                        tampilkanPagu={false}
                    />
                    <DialogRevisi
                        kertasKerjaUuid={kertasKerja.uuid}
                        jenis="perubahan"
                        paguAwal={kertasKerja.pagu}
                        tampilkanPagu={kertasKerja.sumber_dana === 'bosp_reguler'}
                    />
                {/key}
            {/if}
            <Badge
                variant={kertasKerja.status === 'disahkan'
                    ? 'default'
                    : 'secondary'}
            >
                {kertasKerja.status_label}
            </Badge>
            <a
                href={PdfKertasKerjaController.url(kertasKerja.uuid)}
                class="text-sm underline-offset-4 hover:underline"
            >
                Cetak rincian (PDF)
            </a>
            {#if kertasKerja.sumber_dana === 'bosp_reguler'}
                <Link
                    href={PerbandinganController.url(kertasKerja.uuid)}
                    class="text-sm underline-offset-4 hover:underline"
                >
                    Bandingkan dengan impor ARKAS
                </Link>
            {/if}
            <Link
                href={KertasKerjaController.index({
                    query: {
                        tahun: kertasKerja.tahun,
                        sekolah: kertasKerja.sekolah.uuid,
                    },
                })}
                class="text-sm underline-offset-4 hover:underline"
            >
                Kembali ke daftar
            </Link>
        </div>
    </div>

    {#if rantai.length > 1}
        <Card>
            <CardContent class="grid gap-2">
                <h2 class="text-base font-semibold">Riwayat versi</h2>
                <ul class="grid gap-1 text-sm">
                    {#each rantai as versi (versi.id)}
                        <li class="flex flex-wrap justify-between gap-2">
                            {#if versi.id === kertasKerja.id}
                                <span class="font-medium">{versi.versi} (dibuka)</span>
                            {:else}
                                <Link
                                    href={KertasKerjaController.show(versi.uuid)}
                                    class="underline-offset-4 hover:underline"
                                >
                                    {versi.versi}
                                </Link>
                            {/if}
                            <span class="text-muted-foreground">
                                {versi.status_label}{versi.tanggal_revisi
                                    ? ` · ${versi.tanggal_revisi}`
                                    : ''}
                            </span>
                        </li>
                    {/each}
                </ul>
            </CardContent>
        </Card>
    {/if}

    {#if pemeriksaan}
        <HasilPemeriksaan hasil={pemeriksaan} />
    {/if}

    <RingkasanRkas {ringkasan} />

    {#if kertasKerja.bisa_diubah}
        <details
            class="rounded-xl border bg-card px-5 py-4 text-sm"
            open={baris.length === 0}
        >
            <summary class="cursor-pointer text-base font-semibold">
                Cara mengisi
            </summary>
            <ol class="mt-3 grid list-decimal gap-2 pl-5">
                <li>
                    <strong>Atur pagu</strong> di kartu Pagu di bawah, lalu simpan.
                    Total semua baris belanja nanti harus sama dengan pagu ini.
                </li>
                <li>
                    Klik <strong>Tambah belanja</strong>, satu baris untuk satu
                    belanja. Pilih <em>Kegiatan</em> dan
                    <em>Rekening belanja</em>. Lalu pilih
                    <em>Barang/jasa</em> (harga terisi sendiri, boleh diubah),
                    atau ketik <em>Uraian</em> dan <em>Satuan</em> bila tidak ada
                    pilihan barang. Untuk kegiatan honor, pilih <em>PTK</em>.
                </li>
                <li>
                    <strong>Isi volume per bulan</strong> pada bulan belanja itu
                    dilakukan. Jumlah uang dihitung otomatis: total volume × harga
                    satuan.
                </li>
                <li>
                    Ulangi sampai <strong>Sisa</strong> di ringkasan atas
                    menjadi Rp 0. Baris yang sudah tersimpan muncul di tabel dan
                    bisa diubah atau dihapus.
                </li>
                <li>
                    Klik <strong>Nyatakan Siap</strong>. Bila ada yang kurang,
                    daftarnya muncul. Setelah siap dan benar, klik
                    <strong>Tandai Disahkan</strong>.
                </li>
            </ol>
        </details>
    {/if}

    <Card>
        <CardContent class="grid gap-4">
            <div class="flex items-baseline justify-between">
                <span class="text-sm text-muted-foreground">
                    {kertasKerja.bisa_diubah ? 'Langkah 1 · Pagu' : 'Pagu'}
                </span>
                <span class="text-xl font-semibold tabular-nums">
                    {rupiah(kertasKerja.pagu)}
                </span>
            </div>

            {#if kertasKerja.sumber_dana === 'lainnya' && (!kertasKerja.bisa_diubah || kertasKerja.jenis_versi === 'pergeseran')}
                <ul class="grid gap-1 text-sm">
                    {#each pemberiDana as pemberi (pemberi.id)}
                        <li class="flex justify-between">
                            <span>{pemberi.nama}</span>
                            <span class="tabular-nums">{rupiah(pemberi.jumlah)}</span>
                        </li>
                    {/each}
                </ul>
            {/if}

            {#if kertasKerja.bisa_diubah && kertasKerja.jenis_versi !== 'pergeseran'}
                {#key kertasKerja.id}
                    <FormKertasKerja
                        sekolahId={kertasKerja.sekolah.id}
                        tahun={kertasKerja.tahun}
                        pilihanSumberDana={[]}
                        kertasKerjaUuid={kertasKerja.uuid}
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
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold">Langkah 2 · Baris belanja</h2>
            <Button type="button" onclick={() => bukaFormBaris(null)}>
                <Plus class="size-4" />
                Tambah belanja
            </Button>
        </div>

        <Dialog bind:open={dialogBaris} onOpenChange={tutupFormBaris}>
            <DialogContent
                class="mx-3 max-h-[90dvh] max-w-5xl overflow-y-auto"
            >
                <DialogTitle class="mb-4">
                    {sedangDiubah ? 'Ubah baris belanja' : 'Tambah baris belanja'}
                </DialogTitle>
                {#key `${kertasKerja.id}-${sedangDiubah?.id ?? 'baru'}`}
                    <FormBarisRkas
                        kertasKerjaUuid={kertasKerja.uuid}
                        {pilihan}
                        baris={sedangDiubah}
                        onSelesai={() => {
                            dialogBaris = false;
                            sedangDiubah = null;
                        }}
                    />
                {/key}
            </DialogContent>
        </Dialog>
    {/if}

    <TabelBarisRkas
        {baris}
        bisaDiubah={kertasKerja.bisa_diubah}
        onUbah={bukaFormBaris}
    />
</div>
