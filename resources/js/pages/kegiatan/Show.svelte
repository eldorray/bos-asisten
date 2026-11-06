<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [
            { title: 'Bulanan', href: dashboard() },
            { title: 'Rincian kegiatan', href: '#' },
        ],
    };
</script>

<script lang="ts">
    import { Link, router, useForm } from '@inertiajs/svelte';
    import Camera from '@lucide/svelte/icons/camera';
    import Check from '@lucide/svelte/icons/check';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import FileText from '@lucide/svelte/icons/file-text';
    import X from '@lucide/svelte/icons/x';
    import FotoController from '@/actions/App/Http/Controllers/FotoController';
    import KegiatanController from '@/actions/App/Http/Controllers/KegiatanController';
    import AcaraController from '@/actions/App/Http/Controllers/AcaraController';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import FormKuitansi from '@/components/spj/FormKuitansi.svelte';
    import LembarBawah from '@/components/spj/LembarBawah.svelte';
    import type {
        KandidatRapel,
        KuitansiData,
        NilaiAwal,
    } from '@/components/spj/FormKuitansi.svelte';
    import UnggahBerkas from '@/components/spj/UnggahBerkas.svelte';
    import StatusPill from '@/components/spj/StatusPill.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { antreanFoto } from '@/lib/antreanFoto.svelte';
    import { cn } from '@/lib/utils';
    import { dashboard as halamanBulan } from '@/routes';
    import { namaBulan, rupiah } from '@/types';
    import type { BerkasStatus, StatusKode } from '@/types';

    type Rincian = {
        id: number;
        nama: string;
        uraian: string;
        sekolah: string;
        tahun: number;
        bulan: number | null;
        bulanPelaksanaan: number | null;
        perluDiperiksa: string | null;
        tahap: number | null;
        koefisien: string;
        harga: number;
        pajak: number;
        jumlah: number;
        jenisKegiatanId: number;
        jenisDikonfirmasi: boolean;
        statusManual: 'tidak_dilaksanakan' | 'di_luar_aplikasi' | null;
        catatanStatus: string | null;
        hilangDariAnggaran: boolean;
        status: StatusKode;
        berkas: BerkasStatus[];
        rapel: { bulan: number; nomor: string } | null;
    };

    let {
        kegiatan,
        acara,
        berkasFoto,
        jenisKegiatans,
        kuitansi,
        nilaiAwal,
        kandidatRapel,
        rapelDipilih,
        pilihanBulan,
        tunda,
        kandidatAcara,
        selisihAnggaran,
    }: {
        kegiatan: Rincian;
        acara: { id: number; nama: string; anggota: { id: number; nama: string }[] } | null;
        berkasFoto: { id: number; nama: string; fotos: { id: number }[] }[];
        jenisKegiatans: { id: number; nama: string }[];
        kuitansi: KuitansiData | null;
        nilaiAwal: NilaiAwal | null;
        kandidatRapel: KandidatRapel[];
        rapelDipilih: number[];
        pilihanBulan: number[];
        tunda: { pilihan: number[]; alasan: string | null } | null;
        kandidatAcara: { id: number; nama: string; jumlah: number }[];
        selisihAnggaran: { kuitansi: number; anggaran: number } | null;
    } = $props();

    let kuitansiTerbuka = $state(false);
    let berkasTerbuka = $state<number | null>(null);
    let pengaturanTerbuka = $state(false);

    const jumlahFoto = (berkas: { id: number; fotos: unknown[] }): number =>
        berkas.fotos.length +
        antreanFoto.untuk(kegiatan.id, berkas.id).length;

    const adaKuitansi = $derived(kuitansi !== null || kegiatan.rapel !== null);
    const bisaBuatKuitansi = $derived(kuitansi !== null || nilaiAwal !== null);

    const jumlahLengkap = $derived(
        (adaKuitansi ? 1 : 0) +
            berkasFoto.filter((b) => jumlahFoto(b) > 0).length,
    );

    // Tombol utama di HP selalu menunjuk satu pekerjaan berikutnya.
    const berkasBerikutnya = $derived(
        berkasFoto.find((b) => jumlahFoto(b) === 0),
    );

    const bulanKerja = $derived(kegiatan.bulanPelaksanaan ?? kegiatan.bulan);

    const tautanBulan = $derived(
        bulanKerja === null
            ? halamanBulan()
            : halamanBulan({
                  query: {
                      bulan: `${kegiatan.tahun}-${String(bulanKerja).padStart(2, '0')}`,
                  },
              }),
    );

    /* svelte-ignore state_referenced_locally */
    const formTunda = useForm({
        bulan_pelaksanaan: (kegiatan.bulanPelaksanaan ?? '') as number | '',
    });

    function simpanTunda(event: SubmitEvent): void {
        event.preventDefault();
        formTunda
            .transform((data) => ({
                bulan_pelaksanaan:
                    data.bulan_pelaksanaan === '' ? null : data.bulan_pelaksanaan,
            }))
            .submit(KegiatanController.update(kegiatan.id), {
                preserveScroll: true,
            });
    }

    // Nilai awal formulir memang diambil sekali dari props.
    /* svelte-ignore state_referenced_locally */
    const jenis = useForm({ jenis_kegiatan_id: kegiatan.jenisKegiatanId });
    /* svelte-ignore state_referenced_locally */
    const manual = useForm({
        status_manual: kegiatan.statusManual ?? '',
        catatan_status: kegiatan.catatanStatus ?? '',
    });

    /* svelte-ignore state_referenced_locally */
    const pindah = useForm({ bulan: kegiatan.bulan ?? '' });
    /* svelte-ignore state_referenced_locally */
    const gabung = useForm({
        nama: kegiatan.nama,
        // Baris lain yang dicentang; baris ini ikut ditambahkan saat dikirim.
        kegiatan_ids: [] as number[],
    });

    function simpanBulan(event: SubmitEvent): void {
        event.preventDefault();
        pindah.submit(KegiatanController.update(kegiatan.id), {
            preserveScroll: true,
        });
    }

    function simpanGabung(event: SubmitEvent): void {
        event.preventDefault();
        gabung
            .transform((data) => ({
                ...data,
                kegiatan_ids: [kegiatan.id, ...data.kegiatan_ids],
            }))
            .submit(AcaraController.store(), { preserveScroll: true });
    }

    function simpanJenis(event: SubmitEvent): void {
        event.preventDefault();
        jenis.submit(KegiatanController.update(kegiatan.id), {
            preserveScroll: true,
        });
    }

    function pisahAcara(): void {
        if (
            !confirm(
                'Pisahkan acara ini? Semua foto berkas akan pindah ke baris pertama.',
            )
        ) {
            return;
        }

        router.delete(AcaraController.destroy(acara!.id).url, {
            preserveScroll: true,
        });
    }

    function hapusFoto(id: number): void {
        if (!confirm('Hapus foto ini?')) {
            return;
        }

        router.delete(FotoController.destroy(id), { preserveScroll: true });
    }

    function simpanStatus(event: SubmitEvent): void {
        event.preventDefault();
        manual
            .transform((data) => ({
                status_manual: data.status_manual === '' ? null : data.status_manual,
                catatan_status:
                    data.status_manual === 'tidak_dilaksanakan'
                        ? data.catatan_status
                        : null,
            }))
            .submit(KegiatanController.update(kegiatan.id), {
                preserveScroll: true,
            });
    }
</script>

<AppHead title={kegiatan.nama} />

<div class="flex flex-col gap-3.5 p-4 max-md:pb-28 md:gap-5 md:p-7">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <Heading
            title={kegiatan.nama}
            description={kegiatan.bulanPelaksanaan && kegiatan.bulan
                ? `${kegiatan.sekolah} · Anggaran ${namaBulan[kegiatan.bulan - 1]}, dilaksanakan ${namaBulan[kegiatan.bulanPelaksanaan - 1]} ${kegiatan.tahun}`
                : `${kegiatan.sekolah} · ${kegiatan.bulan ? namaBulan[kegiatan.bulan - 1] : 'Belum punya bulan'} ${kegiatan.tahun}`}
        />
        <StatusPill
            status={kegiatan.status}
            kurang={kegiatan.berkas.filter((b) => !b.ada).length}
        />
    </div>

    {#if kegiatan.perluDiperiksa || kegiatan.hilangDariAnggaran}
        <p
            role="alert"
            class="rounded-xl bg-belum px-4 py-3 text-sm font-semibold text-belum-foreground"
        >
            {kegiatan.perluDiperiksa ?? 'Tidak ada lagi di anggaran'}
        </p>
    {/if}

    <p class="text-[15px] text-muted-foreground tabular-nums md:hidden">
        {kegiatan.koefisien} · Harga Rp {rupiah(kegiatan.harga)} · Jumlah
        <strong class="font-semibold text-foreground">
            Rp {rupiah(kegiatan.jumlah)}
        </strong>
    </p>

    <section
        aria-label="Rincian anggaran"
        class="hidden grid-cols-2 gap-3 md:grid lg:grid-cols-4 lg:gap-3.5"
    >
        <div class="rounded-[14px] border bg-card px-4 py-3.5">
            <div class="text-sm text-muted-foreground">Koefisien</div>
            <div class="mt-1 text-lg font-semibold tabular-nums">
                {kegiatan.koefisien}
            </div>
        </div>
        <div class="rounded-[14px] border bg-card px-4 py-3.5">
            <div class="text-sm text-muted-foreground">Harga satuan</div>
            <div class="mt-1 text-lg font-semibold tabular-nums">
                Rp {rupiah(kegiatan.harga)}
            </div>
        </div>
        <div class="rounded-[14px] border bg-card px-4 py-3.5">
            <div class="text-sm text-muted-foreground">Pajak</div>
            <div class="mt-1 text-lg font-semibold tabular-nums">
                Rp {rupiah(kegiatan.pajak)}
            </div>
        </div>
        <div
            class="rounded-[14px] bg-primary px-4 py-3.5 text-primary-foreground"
        >
            <div class="text-sm opacity-80">Jumlah</div>
            <div class="mt-1 text-lg font-semibold tabular-nums">
                Rp {rupiah(kegiatan.jumlah)}
            </div>
        </div>
    </section>

    <!-- Di HP kedua kolom melebur (contents) agar urutannya bisa mengikuti alur kerja. -->
    <div class="grid grid-cols-1 items-start gap-3.5 lg:grid-cols-[minmax(0,1.6fr)_minmax(320px,1fr)]">
        <div class="grid min-w-0 gap-3.5 max-md:contents">
            <p class="text-sm text-muted-foreground max-md:hidden">
                {kegiatan.uraian}
            </p>

            {#if acara}
                <section
                    aria-label="Satu acara"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-[14px] bg-dirapel px-3.5 py-3 text-sm text-dirapel-foreground max-md:order-1"
                >
                    <div class="min-w-60 flex-1">
                        <p class="font-semibold">Satu acara: {acara.nama}</p>
                        <p class="mt-0.5">
                            Foto berkas dipakai bersama oleh {acara.anggota
                                .map((a) => a.nama)
                                .join(', ')}. Kuitansi tetap per baris.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        class="text-foreground"
                        onclick={pisahAcara}
                    >
                        Pisahkan acara
                    </Button>
                </section>
            {/if}

            {#if !acara && kandidatAcara.length > 0}
                <form
                    aria-label="Gabung jadi satu acara"
                    class={cn(
                        'gap-3 rounded-[14px] border bg-card p-3.5 text-sm max-md:order-4 md:grid',
                        pengaturanTerbuka ? 'grid' : 'hidden',
                    )}
                    onsubmit={simpanGabung}
                >
                    <div>
                        <h2 class="text-[15px] font-semibold">
                            Gabung jadi satu acara
                        </h2>
                        <p class="mt-0.5 text-muted-foreground">
                            Dokumen cukup diunggah sekali. Kuitansi tetap per
                            baris.
                        </p>
                    </div>
                    {#each kandidatAcara as k (k.id)}
                        <label class="flex items-center gap-2.5">
                            <input
                                type="checkbox"
                                class="size-4"
                                value={k.id}
                                bind:group={gabung.kegiatan_ids}
                            />
                            <span class="flex-1">{k.nama}</span>
                            <span class="text-muted-foreground tabular-nums">
                                Rp {rupiah(k.jumlah)}
                            </span>
                        </label>
                    {/each}
                    <Label for="nama_acara">Nama acara</Label>
                    <Input id="nama_acara" bind:value={gabung.nama} />
                    <InputError
                        message={gabung.errors.nama ?? gabung.errors.kegiatan_ids}
                    />
                    <div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={gabung.processing ||
                                gabung.kegiatan_ids.length === 0}
                        >
                            Gabung
                        </Button>
                    </div>
                </form>
            {/if}

            <section class="grid grid-cols-1 gap-2.5 max-md:order-2 md:gap-3">
                <div class="mt-1 flex items-baseline justify-between gap-2">
                    <h2 class="text-lg font-semibold tracking-tight">
                        <span class="md:hidden">Berkas</span>
                        <span class="max-md:hidden">Foto berkas</span>
                    </h2>
                    <span
                        class="text-sm font-semibold tabular-nums md:hidden"
                    >
                        {jumlahLengkap} dari {berkasFoto.length + 1} lengkap
                    </span>
                </div>

                <div
                    class="flex items-center gap-3 rounded-[14px] border bg-card py-2.5 pr-2.5 pl-3.5 md:hidden"
                >
                    {#if adaKuitansi}
                        <span
                            class="grid size-7 shrink-0 place-items-center rounded-full bg-selesai text-selesai-foreground"
                        >
                            <Check class="size-4" aria-label="Ada" />
                        </span>
                    {:else}
                        <span
                            role="img"
                            aria-label="Belum ada"
                            class="size-7 shrink-0 rounded-full border-2 border-dashed border-belum-foreground"
                        ></span>
                    {/if}
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold">Kuitansi</span>
                        <span class="block text-[13px] text-muted-foreground">
                            {#if kegiatan.rapel}
                                Dirapel di {kegiatan.rapel.nomor} ({namaBulan[
                                    kegiatan.rapel.bulan - 1
                                ]})
                            {:else if kuitansi}
                                {kuitansi.nomor}
                            {:else}
                                Belum dibuat
                            {/if}
                        </span>
                    </span>
                    {#if !kegiatan.rapel}
                        <Button
                            variant={kuitansi ? 'secondary' : 'default'}
                            onclick={() => (kuitansiTerbuka = true)}
                        >
                            {kuitansi ? 'Buka' : 'Buat'}
                        </Button>
                    {/if}
                </div>

                {#each berkasFoto as berkas (berkas.id)}
                    {@const menunggu = antreanFoto.untuk(kegiatan.id, berkas.id)}
                    <div
                        class="grid grid-cols-1 gap-3 rounded-[14px] border bg-card py-2.5 pr-2.5 pl-3.5 md:p-3.5"
                    >
                        <div class="flex items-center gap-3">
                            {#if jumlahFoto(berkas) > 0}
                                <span
                                    class="grid size-7 shrink-0 place-items-center rounded-full bg-selesai text-selesai-foreground md:hidden"
                                >
                                    <Check class="size-4" aria-label="Ada" />
                                </span>
                            {:else}
                                <span
                                    role="img"
                                    aria-label="Belum ada"
                                    class="size-7 shrink-0 rounded-full border-2 border-dashed border-belum-foreground md:hidden"
                                ></span>
                            {/if}
                            <span class="min-w-0 flex-1">
                                <h3 class="font-semibold md:text-[15px]">
                                    {berkas.nama}
                                </h3>
                                <span
                                    class={cn(
                                        'block text-[13px] md:hidden',
                                        menunggu.length > 0
                                            ? 'text-kurang-foreground'
                                            : jumlahFoto(berkas) > 0
                                              ? 'text-muted-foreground'
                                              : 'text-belum-foreground',
                                    )}
                                >
                                    {#if menunggu.length > 0}
                                        {menunggu.length} foto menunggu dikirim
                                    {:else if berkas.fotos.length > 0}
                                        {berkas.fotos.length} foto
                                    {:else}
                                        Belum ada
                                    {/if}
                                </span>
                            </span>
                            <span
                                class={`rounded-md px-1.5 py-0.5 text-xs font-medium max-md:hidden ${berkas.fotos.length > 0 ? 'bg-selesai text-selesai-foreground' : 'bg-belum text-belum-foreground'}`}
                            >
                                {berkas.fotos.length > 0
                                    ? `${berkas.fotos.length} foto`
                                    : 'Belum ada'}
                            </span>
                            <Button
                                class="md:hidden"
                                variant={jumlahFoto(berkas) > 0
                                    ? 'secondary'
                                    : 'default'}
                                aria-label={`${jumlahFoto(berkas) > 0 ? 'Atur' : 'Foto'} ${berkas.nama}`}
                                onclick={() => (berkasTerbuka = berkas.id)}
                            >
                                <Camera class="size-4" />
                                {jumlahFoto(berkas) > 0 ? 'Atur' : 'Foto'}
                            </Button>
                        </div>
                        <LembarBawah
                            judul={berkas.nama}
                            sebarisDiDesktop
                            terbuka={berkasTerbuka === berkas.id}
                            ontutup={() => {
                                if (berkasTerbuka === berkas.id) {
                                    berkasTerbuka = null;
                                }
                            }}
                        >
                        <p class="text-[15px] text-muted-foreground md:hidden">
                            Pastikan tulisan terbaca dan seluruh kertas masuk
                            bingkai.
                        </p>
                        {#if berkas.fotos.length > 0}
                            <div class="flex flex-wrap gap-2.5">
                                {#each berkas.fotos as foto (foto.id)}
                                    <div class="grid w-28 gap-1.5">
                                        <a
                                            href={FotoController.show.url(foto.id)}
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <img
                                                src={FotoController.show.url(
                                                    foto.id,
                                                )}
                                                alt={`Foto ${berkas.nama}`}
                                                loading="lazy"
                                                class="size-28 rounded-[10px] border object-cover"
                                            />
                                        </a>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onclick={() => hapusFoto(foto.id)}
                                        >
                                            Hapus
                                        </Button>
                                    </div>
                                {/each}
                            </div>
                        {/if}
                        <UnggahBerkas
                            kegiatanId={kegiatan.id}
                            jenisBerkasId={berkas.id}
                            onpilih={() => (berkasTerbuka = null)}
                        />
                        </LembarBawah>
                    </div>
                {/each}
            </section>
        </div>

        <div class="grid min-w-0 gap-3.5 max-md:contents">
            <section
                aria-label="Kelengkapan"
                class="hidden gap-2.5 rounded-[14px] border bg-card p-4 text-sm md:grid"
            >
                <h2 class="text-[15px] font-semibold">Kelengkapan</h2>
                {#if kegiatan.rapel}
                    <p>
                        Dirapel di kuitansi {kegiatan.rapel.nomor} ({namaBulan[
                            kegiatan.rapel.bulan - 1
                        ]}).
                    </p>
                {/if}
                <ul class="grid gap-2">
                    {#each kegiatan.berkas as berkas (berkas.nama)}
                        <li
                            class={`flex items-center gap-2 ${berkas.ada ? '' : 'font-semibold text-belum-foreground'}`}
                        >
                            {#if berkas.ada}
                                <Check class="size-4 shrink-0" aria-label="Ada" />
                            {:else}
                                <X class="size-4 shrink-0" aria-label="Kurang" />
                            {/if}
                            <span class="flex-1">{berkas.nama}</span>
                            {#if berkas.jumlahFoto > 0}
                                <span class="text-[13px] text-muted-foreground">
                                    {berkas.jumlahFoto} foto
                                </span>
                            {/if}
                        </li>
                    {/each}
                </ul>
            </section>

            <section
                aria-label="Kuitansi"
                class="max-md:contents md:rounded-[14px] md:border md:bg-card md:p-4"
            >
                <LembarBawah
                    judul="Kuitansi"
                    sebarisDiDesktop
                    penuh
                    terbuka={kuitansiTerbuka}
                    ontutup={() => (kuitansiTerbuka = false)}
                >
                <h2 class="text-[15px] font-semibold max-md:hidden">Kuitansi</h2>
                {#if selisihAnggaran}
                    <p
                        role="alert"
                        class="rounded-xl bg-kurang px-3 py-2 text-sm text-kurang-foreground md:hidden"
                    >
                        Kuitansi Rp {rupiah(selisihAnggaran.kuitansi)}, anggaran
                        Rp {rupiah(selisihAnggaran.anggaran)}. Periksa sebelum
                        ditandatangani.
                    </p>
                {/if}
                {#if kuitansi !== null || nilaiAwal !== null}
                    {#key `${kuitansi?.id ?? 'baru'}-${kegiatan.bulan}`}
                        <FormKuitansi
                            kegiatanId={kegiatan.id}
                            tahun={kegiatan.tahun}
                            bulan={kegiatan.bulan}
                            {kuitansi}
                            {nilaiAwal}
                            {kandidatRapel}
                            rapelAwal={rapelDipilih}
                            onsimpan={() => (kuitansiTerbuka = false)}
                        />
                    {/key}
                {:else}
                    <p class="text-sm text-muted-foreground">
                        Tentukan bulan kegiatan ini dulu sebelum membuat
                        kuitansi.
                    </p>
                {/if}
                </LembarBawah>
            </section>

            <button
                type="button"
                aria-expanded={pengaturanTerbuka}
                class="flex min-h-13 items-center justify-between rounded-[14px] border bg-card px-3.5 text-left font-semibold max-md:order-3 md:hidden"
                onclick={() => (pengaturanTerbuka = !pengaturanTerbuka)}
            >
                <span>
                    Pengaturan kegiatan
                    <span class="block text-[13px] font-normal text-muted-foreground">
                        Tunda, jenis, dan status
                    </span>
                </span>
                <ChevronDown
                    class={cn('size-5', pengaturanTerbuka && 'rotate-180')}
                />
            </button>

            <div
                class={cn(
                    'gap-4 rounded-[14px] border bg-card p-4 max-md:order-5 md:grid',
                    pengaturanTerbuka ? 'grid' : 'hidden',
                )}
            >
                {#if tunda}
                    <form class="grid gap-3 border-b pb-4" onsubmit={simpanTunda}>
                        <Label for="bulan_pelaksanaan">Bulan pelaksanaan</Label>
                        {#if tunda.alasan}
                            <p class="text-sm text-muted-foreground">
                                {tunda.alasan}
                            </p>
                        {:else if tunda.pilihan.length === 0}
                            <p class="text-sm text-muted-foreground">
                                Tidak ada bulan berikutnya di tahun ini.
                            </p>
                        {:else}
                            <select
                                id="bulan_pelaksanaan"
                                class="h-11 rounded-[10px] border border-input bg-card px-2.5 text-sm"
                                bind:value={formTunda.bulan_pelaksanaan}
                            >
                                <option value="">
                                    Sesuai anggaran ({kegiatan.bulan
                                        ? namaBulan[kegiatan.bulan - 1]
                                        : ''})
                                </option>
                                {#each tunda.pilihan as b (b)}
                                    <option value={b}>
                                        Ditunda ke {namaBulan[b - 1]}
                                    </option>
                                {/each}
                            </select>
                            <InputError
                                message={formTunda.errors.bulan_pelaksanaan}
                            />
                            <div>
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={formTunda.processing}
                                >
                                    Simpan bulan pelaksanaan
                                </Button>
                            </div>
                        {/if}
                    </form>
                {/if}

                {#if pilihanBulan.length > 0}
                    <form class="grid gap-3 border-b pb-4" onsubmit={simpanBulan}>
                        <Label for="bulan_kegiatan">Bulan kegiatan</Label>
                        <select
                            id="bulan_kegiatan"
                            class="h-11 rounded-[10px] border border-input bg-card px-2.5 text-sm"
                            bind:value={pindah.bulan}
                        >
                            <option value="" disabled>Pilih bulan</option>
                            {#each pilihanBulan as b (b)}
                                <option value={b}>{namaBulan[b - 1]}</option>
                            {/each}
                        </select>
                        <InputError message={pindah.errors.bulan} />
                        <div>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={pindah.processing}
                            >
                                Simpan bulan
                            </Button>
                        </div>
                    </form>
                {/if}

                <form class="grid gap-3" onsubmit={simpanJenis}>
                    <Label for="jenis">Jenis kegiatan</Label>
                    <select
                        id="jenis"
                        class="h-11 rounded-[10px] border border-input bg-card px-2.5 text-sm"
                        bind:value={jenis.jenis_kegiatan_id}
                    >
                        {#each jenisKegiatans as j (j.id)}
                            <option value={j.id}>{j.nama}</option>
                        {/each}
                    </select>
                    <InputError message={jenis.errors.jenis_kegiatan_id} />
                    <div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={jenis.processing}
                        >
                            Simpan jenis
                        </Button>
                    </div>
                </form>

                <form class="grid gap-3 border-t pt-4" onsubmit={simpanStatus}>
                    <Label for="status_manual">Status manual</Label>
                    <select
                        id="status_manual"
                        class="h-11 rounded-[10px] border border-input bg-card px-2.5 text-sm"
                        bind:value={manual.status_manual}
                    >
                        <option value="">Ikuti kelengkapan berkas</option>
                        <option value="di_luar_aplikasi">
                            Sudah dikerjakan di luar aplikasi
                        </option>
                        <option value="tidak_dilaksanakan">
                            Tidak dilaksanakan
                        </option>
                    </select>
                    <InputError message={manual.errors.status_manual} />
                    {#if manual.status_manual === 'tidak_dilaksanakan'}
                        <Label for="catatan">Alasan singkat</Label>
                        <Input id="catatan" bind:value={manual.catatan_status} />
                        <InputError message={manual.errors.catatan_status} />
                    {/if}
                    <div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={manual.processing}
                        >
                            Simpan status
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div
    class="fixed inset-x-0 bottom-0 z-20 border-t bg-card px-4 pt-2.5 pb-[calc(0.75rem+env(safe-area-inset-bottom))] md:hidden"
>
    {#if kegiatan.statusManual !== null || kegiatan.rapel !== null}
        {@render tautanKembali('Kembali ke daftar', false)}
    {:else if !adaKuitansi}
        <Button
            class="h-14 w-full text-base font-semibold"
            onclick={() => {
                if (bisaBuatKuitansi) {
                    kuitansiTerbuka = true;
                } else {
                    pengaturanTerbuka = true;
                }
            }}
        >
            <FileText class="size-5" />
            {bisaBuatKuitansi ? 'Buat kuitansi' : 'Tentukan bulan dulu'}
        </Button>
    {:else if berkasBerikutnya}
        <Button
            class="h-14 w-full text-base font-semibold"
            onclick={() => (berkasTerbuka = berkasBerikutnya.id)}
        >
            <Camera class="size-5" />
            Foto {berkasBerikutnya.nama}
        </Button>
    {:else}
        {@render tautanKembali('Lengkap, kembali ke daftar', true)}
    {/if}
</div>

{#snippet tautanKembali(teks: string, lengkap: boolean)}
    <Link
        href={tautanBulan}
        class={cn(
            'flex h-14 w-full items-center justify-center gap-2 rounded-[10px] text-base font-semibold',
            lengkap
                ? 'bg-selesai text-selesai-foreground'
                : 'border border-input',
        )}
    >
        {#if lengkap}
            <Check class="size-5" />
        {/if}
        {teks}
    </Link>
{/snippet}
