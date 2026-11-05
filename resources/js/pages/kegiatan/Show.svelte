<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Rincian kegiatan', href: '#' },
        ],
    };
</script>

<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import FotoController from '@/actions/App/Http/Controllers/FotoController';
    import KegiatanController from '@/actions/App/Http/Controllers/KegiatanController';
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
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
        berkasFoto,
        jenisKegiatans,
        kuitansi,
        nilaiAwal,
        kandidatRapel,
    }: {
        kegiatan: Rincian;
        berkasFoto: { id: number; nama: string; fotos: { id: number }[] }[];
        jenisKegiatans: { id: number; nama: string }[];
        kuitansi: KuitansiData | null;
        nilaiAwal: NilaiAwal | null;
        kandidatRapel: KandidatRapel[];
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

<div class="flex flex-col gap-6 p-4">
    <Heading
        title={kegiatan.nama}
        description={`${kegiatan.sekolah} · ${kegiatan.bulan ? namaBulan[kegiatan.bulan - 1] : 'Belum punya bulan'} ${kegiatan.tahun}`}
    />

    <div class="grid gap-4 md:grid-cols-2">
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Rincian</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-1 text-sm">
                <p>{kegiatan.uraian}</p>
                <p>{kegiatan.koefisien} × Rp {rupiah(kegiatan.harga)}</p>
                <p>Pajak Rp {rupiah(kegiatan.pajak)}</p>
                <p class="font-semibold">Jumlah Rp {rupiah(kegiatan.jumlah)}</p>
                {#if kegiatan.hilangDariAnggaran}
                    <p class="font-semibold text-red-700">
                        Tidak ada lagi di anggaran
                    </p>
                {/if}
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    Status
                    <StatusPill
                        status={kegiatan.status}
                        kurang={kegiatan.berkas.filter((b) => !b.ada).length}
                    />
                </CardTitle>
            </CardHeader>
            <CardContent class="grid gap-1 text-sm">
                {#if kegiatan.rapel}
                    <p>
                        Dirapel di kuitansi {kegiatan.rapel.nomor} ({namaBulan[
                            kegiatan.rapel.bulan - 1
                        ]}).
                    </p>
                {/if}
                {#each kegiatan.berkas as berkas (berkas.nama)}
                    <p>
                        {berkas.ada ? '✓' : '✗'}
                        {berkas.nama}
                        {#if berkas.jumlahFoto > 0}
                            ({berkas.jumlahFoto} foto)
                        {/if}
                    </p>
                {/each}
            </CardContent>
        </Card>
    </div>

    <section class="grid gap-4">
        <h2 class="text-base font-semibold">Foto berkas</h2>
        {#each berkasFoto as berkas (berkas.id)}
            <div class="grid gap-2 rounded-md border p-3">
                <h3 class="text-sm font-medium">{berkas.nama}</h3>
                {#if berkas.fotos.length > 0}
                    <div class="flex flex-wrap gap-2">
                        {#each berkas.fotos as foto (foto.id)}
                            <div class="grid gap-1">
                                <a
                                    href={FotoController.show.url(foto.id)}
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <img
                                        src={FotoController.show.url(foto.id)}
                                        alt={`Foto ${berkas.nama}`}
                                        loading="lazy"
                                        class="h-28 w-28 rounded-md border object-cover"
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
                <UnggahBerkas kegiatanId={kegiatan.id} jenisBerkasId={berkas.id} />
            </div>
        {/each}
    </section>

    <section class="grid gap-3">
        <h2 class="text-base font-semibold">Kuitansi</h2>
        {#if kuitansi !== null || nilaiAwal !== null}
            {#key kuitansi?.id ?? 'baru'}
                <FormKuitansi
                    kegiatanId={kegiatan.id}
                    tahun={kegiatan.tahun}
                    {kuitansi}
                    {nilaiAwal}
                    {kandidatRapel}
                />
            {/key}
        {:else}
            <p class="text-sm text-muted-foreground">
                Tentukan bulan kegiatan ini dulu sebelum membuat kuitansi.
            </p>
        {/if}
    </section>

    <form class="grid max-w-xl gap-3" onsubmit={simpanJenis}>
        <Label for="jenis">Jenis kegiatan</Label>
        <select
            id="jenis"
            class="h-9 rounded-md border border-input bg-background px-2 text-sm"
            bind:value={jenis.jenis_kegiatan_id}
        >
            {#each jenisKegiatans as j (j.id)}
                <option value={j.id}>{j.nama}</option>
            {/each}
        </select>
        <InputError message={jenis.errors.jenis_kegiatan_id} />
        <div>
            <Button type="submit" disabled={jenis.processing}>
                Simpan jenis
            </Button>
        </div>
    </form>

    <form class="grid max-w-xl gap-3" onsubmit={simpanStatus}>
        <Label for="status_manual">Status manual</Label>
        <select
            id="status_manual"
            class="h-9 rounded-md border border-input bg-background px-2 text-sm"
            bind:value={manual.status_manual}
        >
            <option value="">Ikuti kelengkapan berkas</option>
            <option value="di_luar_aplikasi">
                Sudah dikerjakan di luar aplikasi
            </option>
            <option value="tidak_dilaksanakan">Tidak dilaksanakan</option>
        </select>
        <InputError message={manual.errors.status_manual} />
        {#if manual.status_manual === 'tidak_dilaksanakan'}
            <Label for="catatan">Alasan singkat</Label>
            <Input id="catatan" bind:value={manual.catatan_status} />
            <InputError message={manual.errors.catatan_status} />
        {/if}
        <div>
            <Button type="submit" disabled={manual.processing}>
                Simpan status
            </Button>
        </div>
    </form>
</div>
