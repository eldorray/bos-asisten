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
    import { router, useForm } from '@inertiajs/svelte';
    import Check from '@lucide/svelte/icons/check';
    import X from '@lucide/svelte/icons/x';
    import FotoController from '@/actions/App/Http/Controllers/FotoController';
    import KegiatanController from '@/actions/App/Http/Controllers/KegiatanController';
    import AcaraController from '@/actions/App/Http/Controllers/AcaraController';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import FormKuitansi from '@/components/spj/FormKuitansi.svelte';
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
    import { namaBulan, rupiah } from '@/types';
    import type { BerkasStatus, StatusKode } from '@/types';

    type Rincian = {
        id: number;
        nama: string;
        uraian: string;
        sekolah: string;
        tahun: number;
        bulan: number | null;
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
    }: {
        kegiatan: Rincian;
        acara: { id: number; nama: string; anggota: { id: number; nama: string }[] } | null;
        berkasFoto: { id: number; nama: string; fotos: { id: number }[] }[];
        jenisKegiatans: { id: number; nama: string }[];
        kuitansi: KuitansiData | null;
        nilaiAwal: NilaiAwal | null;
        kandidatRapel: KandidatRapel[];
        rapelDipilih: number[];
    } = $props();

    // Nilai awal formulir memang diambil sekali dari props.
    /* svelte-ignore state_referenced_locally */
    const jenis = useForm({ jenis_kegiatan_id: kegiatan.jenisKegiatanId });
    /* svelte-ignore state_referenced_locally */
    const manual = useForm({
        status_manual: kegiatan.statusManual ?? '',
        catatan_status: kegiatan.catatanStatus ?? '',
    });

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

<div class="flex flex-col gap-5 p-5 md:p-7">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <Heading
            title={kegiatan.nama}
            description={`${kegiatan.sekolah} · ${kegiatan.bulan ? namaBulan[kegiatan.bulan - 1] : 'Belum punya bulan'} ${kegiatan.tahun}`}
        />
        <StatusPill
            status={kegiatan.status}
            kurang={kegiatan.berkas.filter((b) => !b.ada).length}
        />
    </div>

    {#if kegiatan.hilangDariAnggaran}
        <p
            role="alert"
            class="rounded-xl bg-belum px-4 py-3 text-sm font-semibold text-belum-foreground"
        >
            Tidak ada lagi di anggaran
        </p>
    {/if}

    <section
        aria-label="Rincian anggaran"
        class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-3.5"
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

    <div class="grid items-start gap-3.5 lg:grid-cols-[minmax(0,1.6fr)_minmax(320px,1fr)]">
        <div class="grid min-w-0 gap-3.5">
            <p class="text-sm text-muted-foreground">{kegiatan.uraian}</p>

            {#if acara}
                <section
                    aria-label="Satu acara"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-[14px] bg-dirapel px-3.5 py-3 text-sm text-dirapel-foreground"
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

            <section class="grid gap-3">
                <h2 class="mt-1 text-lg font-semibold tracking-tight">
                    Foto berkas
                </h2>
                {#each berkasFoto as berkas (berkas.id)}
                    <div class="grid gap-3 rounded-[14px] border bg-card p-3.5">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-[15px] font-semibold">
                                {berkas.nama}
                            </h3>
                            <span
                                class={`rounded-md px-1.5 py-0.5 text-xs font-medium ${berkas.fotos.length > 0 ? 'bg-selesai text-selesai-foreground' : 'bg-belum text-belum-foreground'}`}
                            >
                                {berkas.fotos.length > 0
                                    ? `${berkas.fotos.length} foto`
                                    : 'Belum ada'}
                            </span>
                        </div>
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
                        />
                    </div>
                {/each}
            </section>
        </div>

        <div class="grid min-w-0 gap-3.5">
            <section
                aria-label="Kelengkapan"
                class="grid gap-2.5 rounded-[14px] border bg-card p-4 text-sm"
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
                class="grid gap-3 rounded-[14px] border bg-card p-4"
            >
                <h2 class="text-[15px] font-semibold">Kuitansi</h2>
                {#if kuitansi !== null || nilaiAwal !== null}
                    {#key kuitansi?.id ?? 'baru'}
                        <FormKuitansi
                            kegiatanId={kegiatan.id}
                            tahun={kegiatan.tahun}
                            {kuitansi}
                            {nilaiAwal}
                            {kandidatRapel}
                            rapelAwal={rapelDipilih}
                        />
                    {/key}
                {:else}
                    <p class="text-sm text-muted-foreground">
                        Tentukan bulan kegiatan ini dulu sebelum membuat
                        kuitansi.
                    </p>
                {/if}
            </section>

            <div class="grid gap-4 rounded-[14px] border bg-card p-4">
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
