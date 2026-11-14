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
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import X from '@lucide/svelte/icons/x';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
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
    let kegiatanAwal = $state<number | ''>('');
    let dialogBaris = $state(false);
    let dialogPagu = $state(false);
    let dialogCara = $state(false);
    let ringkasanBuka = $state(false);

    function bukaFormBaris(
        b: BarisRkas | null,
        refKegiatanId: number | null = null,
    ): void {
        sedangDiubah = b;
        kegiatanAwal = refKegiatanId ?? '';
        dialogBaris = true;
    }

    const paguBisaDiubah = $derived(
        kertasKerja.bisa_diubah && kertasKerja.jenis_versi !== 'pergeseran',
    );
    const persenDianggarkan = $derived(
        kertasKerja.pagu > 0
            ? Math.min(
                  100,
                  Math.round((ringkasan.dianggarkan / kertasKerja.pagu) * 100),
              )
            : 0,
    );
    const jumlahPerhatian = $derived(
        ringkasan.peringatan.length +
            (ringkasan.jumlah_baris_bermasalah > 0 ? 1 : 0),
    );
    const KELAS_MENU = 'min-h-11 px-2.5';

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

    /** Porsi terhadap pagu, dua desimal seperti persentase juknis. */
    function persen(n: number): string {
        if (kertasKerja.pagu <= 0) {
            return '';
        }

        return `${((n / kertasKerja.pagu) * 100).toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`;
    }
</script>

<AppHead title={`${kertasKerja.label} ${kertasKerja.tahun}`} />

<div class="flex flex-col gap-4 p-4 md:gap-5 md:p-7">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1
                    class="text-[26px] leading-tight font-semibold tracking-tight"
                >
                    {kertasKerja.label}
                    {kertasKerja.tahun}
                </h1>
                <Badge
                    variant={kertasKerja.status === 'disahkan'
                        ? 'default'
                        : 'secondary'}
                >
                    {kertasKerja.status_label}
                </Badge>
            </div>
            <p class="mt-1 text-[15px] text-muted-foreground">
                {kertasKerja.sekolah.nama} ({kertasKerja.sekolah.identitas}) · {namaSumber}
                · {kertasKerja.versi}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    {#snippet children(props)}
                        <Button
                            variant="outline"
                            onclick={props.onclick}
                            aria-expanded={props['aria-expanded']}
                        >
                            Lainnya
                            <ChevronDown class="size-4" />
                        </Button>
                    {/snippet}
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    sideOffset={6}
                    class="w-64 max-md:right-auto max-md:left-0"
                >
                    <DropdownMenuItem asChild class={KELAS_MENU}>
                        {#snippet children(props)}
                            <a
                                href={PdfKertasKerjaController.url(
                                    kertasKerja.uuid,
                                )}
                                class={props.class}
                                onclick={props.onClick}
                            >
                                Cetak rincian (PDF)
                            </a>
                        {/snippet}
                    </DropdownMenuItem>
                    {#if kertasKerja.sumber_dana === 'bosp_reguler'}
                        <DropdownMenuItem asChild class={KELAS_MENU}>
                            {#snippet children(props)}
                                <Link
                                    href={PerbandinganController.url(
                                        kertasKerja.uuid,
                                    )}
                                    class={props.class}
                                    onclick={props.onClick}
                                >
                                    Bandingkan dengan impor ARKAS
                                </Link>
                            {/snippet}
                        </DropdownMenuItem>
                    {/if}
                    {#if paguBisaDiubah}
                        <DropdownMenuItem asChild class={KELAS_MENU}>
                            {#snippet children(props)}
                                <button
                                    type="button"
                                    class={props.class}
                                    onclick={() => {
                                        props.onClick?.();
                                        dialogPagu = true;
                                    }}
                                >
                                    Ubah pagu
                                </button>
                            {/snippet}
                        </DropdownMenuItem>
                    {/if}
                    {#if kertasKerja.bisa_diubah}
                        <DropdownMenuItem asChild class={KELAS_MENU}>
                            {#snippet children(props)}
                                <button
                                    type="button"
                                    class={props.class}
                                    onclick={() => {
                                        props.onClick?.();
                                        dialogCara = true;
                                    }}
                                >
                                    Cara mengisi
                                </button>
                            {/snippet}
                        </DropdownMenuItem>
                    {/if}
                    <DropdownMenuItem asChild class={KELAS_MENU}>
                        {#snippet children(props)}
                            <Link
                                href={KertasKerjaController.index({
                                    query: {
                                        tahun: kertasKerja.tahun,
                                        sekolah: kertasKerja.sekolah.uuid,
                                    },
                                })}
                                class={props.class}
                                onclick={props.onClick}
                            >
                                Kembali ke daftar
                            </Link>
                        {/snippet}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

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
        </div>
    </div>

    <!-- Selalu terlihat saat menggulir: angka inilah yang dikejar sampai sisa Rp 0. -->
    <!-- Di HP tombol turun ke baris total supaya sisa per tahap mendapat satu baris penuh. -->
    {#snippet tombolRingkasan(kelas: string)}
        <Button
            variant="outline"
            class={kelas}
            aria-expanded={ringkasanBuka}
            onclick={() => (ringkasanBuka = !ringkasanBuka)}
        >
            {ringkasanBuka ? 'Tutup' : 'Ringkasan'}<span
                class={ringkasanBuka ? '-ml-1 max-md:hidden' : 'hidden'}
            >
                ringkasan
            </span>
            {#if jumlahPerhatian > 0}
                <span
                    aria-label={`${jumlahPerhatian} hal perlu diperhatikan`}
                    class="min-w-5 rounded-full bg-kurang px-1.5 text-center text-xs font-semibold text-kurang-foreground tabular-nums"
                >
                    {jumlahPerhatian}
                </span>
            {/if}
        </Button>
    {/snippet}

    <section
        aria-label="Ringkasan pagu"
        class="sticky top-[57px] z-10 -mx-2 bg-background p-2 md:top-0"
    >
        <div class="grid gap-3 rounded-[14px] border bg-card px-4 py-3.5">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 md:gap-x-8">
                <div class="max-md:hidden">
                    <div class="text-meta text-muted-foreground">Pagu</div>
                    <div class="text-lg font-semibold tabular-nums">
                        {rupiah(kertasKerja.pagu)}
                    </div>
                </div>
                <div class="max-md:hidden">
                    <div class="text-meta text-muted-foreground">
                        Dianggarkan
                    </div>
                    <div class="text-lg font-semibold tabular-nums">
                        {rupiah(ringkasan.dianggarkan)}
                        <span
                            class="text-sm font-normal text-muted-foreground"
                        >
                            {persen(ringkasan.dianggarkan)}
                        </span>
                    </div>
                </div>
                {#each ringkasan.tahap as t (t.tahap)}
                    <div class="min-w-0 max-md:basis-[calc(50%-0.375rem)]">
                        <div class="text-meta text-muted-foreground">
                            {t.selisih < 0 ? 'Lebih' : 'Sisa'} tahap {t.tahap}
                        </div>
                        <div
                            class={[
                                'font-semibold whitespace-nowrap tabular-nums md:text-lg',
                                t.selisih < 0 && 'text-belum-foreground',
                                t.selisih === 0 && 'text-selesai-foreground',
                            ]}
                        >
                            {rupiah(Math.abs(t.selisih))}
                        </div>
                    </div>
                {:else}
                    <div class="min-w-0 flex-1">
                        <div class="text-meta text-muted-foreground">
                            {ringkasan.sisa < 0
                                ? 'Melebihi pagu'
                                : ringkasan.sisa === 0
                                  ? 'Sisa (pas)'
                                  : 'Sisa'}
                        </div>
                        <div
                            class={[
                                'text-lg font-semibold tabular-nums',
                                ringkasan.sisa < 0 && 'text-belum-foreground',
                                ringkasan.sisa === 0 && 'text-selesai-foreground',
                            ]}
                        >
                            <span class="whitespace-nowrap">
                                {rupiah(Math.abs(ringkasan.sisa))}
                            </span>
                            <span
                                class="text-sm font-normal text-muted-foreground max-md:hidden"
                            >
                                {persen(Math.abs(ringkasan.sisa))}
                            </span>
                        </div>
                        <div
                            class="flex flex-wrap gap-x-1 text-meta text-muted-foreground tabular-nums md:hidden"
                        >
                            <span>{persen(Math.abs(ringkasan.sisa))}</span>
                            <span class="whitespace-nowrap">
                                dari {rupiah(kertasKerja.pagu)}
                            </span>
                        </div>
                    </div>
                {/each}
                {@render tombolRingkasan('ml-auto max-md:hidden')}
            </div>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                <div
                    role="progressbar"
                    aria-label="Porsi pagu yang sudah dianggarkan"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={persenDianggarkan}
                    class="h-2 min-w-32 flex-1 overflow-hidden rounded-full bg-muted max-md:basis-full"
                >
                    <div
                        class={[
                            'h-full rounded-full',
                            ringkasan.sisa < 0
                                ? 'bg-belum-foreground'
                                : ringkasan.sisa === 0
                                  ? 'bg-selesai-foreground'
                                  : 'bg-primary',
                        ]}
                        style:width={`${persenDianggarkan}%`}
                    ></div>
                </div>
                {#if ringkasan.tahap.length > 0}
                    <span
                        class={[
                            'text-meta whitespace-nowrap tabular-nums',
                            ringkasan.sisa < 0
                                ? 'font-medium text-belum-foreground'
                                : 'text-muted-foreground',
                        ]}
                    >
                        {ringkasan.sisa < 0 ? 'Melebihi pagu' : 'Sisa'}
                        {rupiah(Math.abs(ringkasan.sisa))}
                        · {persen(Math.abs(ringkasan.sisa))}
                    </span>
                {/if}
                {@render tombolRingkasan('ml-auto md:hidden')}
            </div>
        </div>
    </section>

    {#if ringkasanBuka}
        <RingkasanRkas {ringkasan} tanpaTotal />

        {#if kertasKerja.sumber_dana === 'lainnya' && pemberiDana.length > 0}
            <Card>
                <CardContent class="grid gap-2">
                    <span class="text-sm font-medium">Pemberi dana</span>
                    <ul class="grid gap-1 text-sm">
                        {#each pemberiDana as pemberi (pemberi.id)}
                            <li class="flex justify-between">
                                <span>{pemberi.nama}</span>
                                <span class="tabular-nums">
                                    {rupiah(pemberi.jumlah)}
                                </span>
                            </li>
                        {/each}
                    </ul>
                </CardContent>
            </Card>
        {/if}
    {/if}

    {#if pemeriksaan}
        <HasilPemeriksaan hasil={pemeriksaan} />
    {/if}

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

    {#if kertasKerja.bisa_diubah && baris.length === 0}
        <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-[14px] bg-muted px-4 py-3 text-sm"
        >
            <span>
                Baru pertama kali menyusun? Lihat urutan pengisiannya dulu.
            </span>
            <Button variant="outline" onclick={() => (dialogCara = true)}>
                Cara mengisi
            </Button>
        </div>
    {/if}

    <TabelBarisRkas
        {baris}
        bisaDiubah={kertasKerja.bisa_diubah}
        kertasKerjaUuid={kertasKerja.uuid}
        sisaPagu={ringkasan.sisa}
        onUbah={(b) => bukaFormBaris(b)}
        onTambah={(refKegiatanId) => bukaFormBaris(null, refKegiatanId)}
    />
</div>

{#if kertasKerja.bisa_diubah}
    <Dialog bind:open={dialogBaris} onOpenChange={tutupFormBaris}>
        <DialogContent
            class="max-h-[90dvh] max-w-3xl overflow-y-auto max-md:h-dvh max-md:max-h-dvh max-md:max-w-none max-md:rounded-none max-md:border-0"
        >
            <DialogTitle class="mb-4">
                {sedangDiubah ? 'Ubah belanja' : 'Tambah belanja'}
            </DialogTitle>
            {#key `${kertasKerja.id}-${sedangDiubah?.id ?? 'baru'}`}
                <FormBarisRkas
                    kertasKerjaUuid={kertasKerja.uuid}
                    {pilihan}
                    baris={sedangDiubah}
                    {kegiatanAwal}
                    sisaPagu={ringkasan.sisa}
                    onSelesai={() => {
                        dialogBaris = false;
                        sedangDiubah = null;
                    }}
                />
            {/key}
        </DialogContent>
    </Dialog>

    <Dialog bind:open={dialogCara}>
        <DialogContent
            class="max-h-[90dvh] overflow-y-auto max-md:max-w-[calc(100%-2rem)]"
        >
            <DialogTitle class="mb-3">Cara mengisi</DialogTitle>
            <ol class="grid list-decimal gap-2 pl-5 text-sm">
                <li>
                    Pastikan <strong>pagu</strong> sudah benar. Pagu diubah lewat
                    menu <em>Lainnya</em>, lalu <em>Ubah pagu</em>. Total semua
                    belanja nanti harus sama dengan pagu ini.
                </li>
                <li>
                    Klik <strong>Tambah belanja</strong>, satu untuk tiap
                    belanja. Pilih <em>Kegiatan</em> dan
                    <em>Rekening belanja</em>. Lalu pilih
                    <em>Barang/jasa</em> (harga terisi sendiri, boleh diubah),
                    atau ketik <em>Uraian</em> dan <em>Satuan</em> bila tidak ada
                    pilihan barang. Untuk kegiatan honor, pilih <em>PTK</em>.
                </li>
                <li>
                    <strong>Isi volume</strong> pada bulan belanja itu dilakukan.
                    Untuk belanja rutin, ketik angkanya di <em>Isi cepat</em>
                    lalu pilih <em>Tiap bulan</em> atau per tahap. Jumlah uang
                    dihitung otomatis.
                </li>
                <li>
                    Pakai <strong>Simpan dan tambah lagi</strong> untuk belanja
                    berikutnya di kegiatan yang sama, atau
                    <strong>Tambah di sini</strong> pada kegiatan yang sudah
                    ada. Ulangi sampai <strong>Sisa</strong> di atas menjadi Rp
                    0.
                </li>
                <li>
                    Klik <strong>Nyatakan Siap</strong>. Bila ada yang kurang,
                    daftarnya muncul. Setelah siap dan benar, klik
                    <strong>Tandai Disahkan</strong>.
                </li>
            </ol>
            <div class="mt-4 flex justify-end">
                <Button onclick={() => (dialogCara = false)}>Mengerti</Button>
            </div>
        </DialogContent>
    </Dialog>
{/if}

{#if paguBisaDiubah}
    <Dialog bind:open={dialogPagu}>
        <DialogContent
            class="max-h-[90dvh] overflow-y-auto max-md:max-w-[calc(100%-2rem)]"
        >
            <div class="mb-3 flex items-center justify-between gap-2">
                <DialogTitle>Ubah pagu</DialogTitle>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Tutup"
                    onclick={() => (dialogPagu = false)}
                >
                    <X class="size-5" />
                </Button>
            </div>
            {#key `${kertasKerja.id}-${dialogPagu}`}
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
                    onSelesai={() => (dialogPagu = false)}
                />
            {/key}
        </DialogContent>
    </Dialog>
{/if}
