<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import KegiatanController from '@/actions/App/Http/Controllers/KegiatanController';
    import KuitansiController from '@/actions/App/Http/Controllers/KuitansiController';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { namaBulan, rupiah } from '@/types';

    export type NilaiAwal = {
        untuk_pembayaran: string;
        penerima: string | null;
        jumlah: number;
        nomor: string;
        sumber_dana: string;
        terbilang: string;
    };

    export type KuitansiData = {
        id: number;
        nomor: string;
        untuk_pembayaran: string;
        penerima: string;
        tanggal_lunas: string;
        jumlah: number;
        sumberDana: string;
        terbilang: string;
        bulanCakupan: number[];
    };

    export type KandidatRapel = { id: number; bulan: number; jumlah: number };

    let {
        kegiatanId,
        tahun,
        kuitansi,
        nilaiAwal,
        kandidatRapel,
    }: {
        kegiatanId: number;
        tahun: number;
        kuitansi: KuitansiData | null;
        nilaiAwal: NilaiAwal | null;
        kandidatRapel: KandidatRapel[];
    } = $props();

    const hariIni = new Date().toISOString().slice(0, 10);

    // Nilai awal formulir memang diambil sekali dari props.
    /* svelte-ignore state_referenced_locally */
    const form = useForm({
        nomor: kuitansi?.nomor ?? nilaiAwal?.nomor ?? '',
        untuk_pembayaran:
            kuitansi?.untuk_pembayaran ?? nilaiAwal?.untuk_pembayaran ?? '',
        penerima: kuitansi?.penerima ?? nilaiAwal?.penerima ?? '',
        tanggal_lunas:
            kuitansi?.tanggal_lunas ??
            (hariIni.startsWith(String(tahun)) ? hariIni : ''),
        jumlah: kuitansi?.jumlah ?? nilaiAwal?.jumlah ?? 0,
    });
    const hapus = useForm({});
    const galat = $derived(form.errors as Record<string, string | undefined>);

    let rapelDipilih = $state<number[]>([]);

    const terbilang = $derived(
        kuitansi !== null
            ? kuitansi.terbilang
            : nilaiAwal !== null && Number(form.jumlah) === nilaiAwal.jumlah
              ? nilaiAwal.terbilang
              : null,
    );

    function pilihRapel(id: number, aktif: boolean): void {
        rapelDipilih = aktif
            ? [...rapelDipilih, id]
            : rapelDipilih.filter((x) => x !== id);

        router.get(
            KegiatanController.show.url(kegiatanId),
            { rapel: rapelDipilih },
            {
                only: ['nilaiAwal'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: (page) => {
                    const baru = page.props.nilaiAwal as NilaiAwal | null;

                    if (baru) {
                        form.nomor = baru.nomor;
                        form.untuk_pembayaran = baru.untuk_pembayaran;
                        form.jumlah = baru.jumlah;
                    }
                },
            },
        );
    }

    function simpan(event: SubmitEvent): void {
        event.preventDefault();

        if (kuitansi !== null) {
            form.submit(KuitansiController.update(kuitansi.id), {
                preserveScroll: true,
            });

            return;
        }

        form.transform((data) => ({
            ...data,
            kegiatan_rapel_ids: rapelDipilih,
        })).submit(KuitansiController.store(kegiatanId), {
            preserveScroll: true,
        });
    }

    function hapusKuitansi(): void {
        if (
            kuitansi === null ||
            !confirm(
                'Hapus kuitansi ini? Foto berkas tidak ikut terhapus, tetapi nomornya dilepas.',
            )
        ) {
            return;
        }

        hapus.submit(KuitansiController.destroy(kuitansi.id), {
            preserveScroll: true,
        });
    }
</script>

<form class="grid max-w-xl gap-3" onsubmit={simpan}>
    {#if kuitansi !== null}
        <p class="text-sm text-muted-foreground">
            Mencakup {kuitansi.bulanCakupan
                .map((b) => namaBulan[b - 1])
                .join(', ')} · {kuitansi.sumberDana}
        </p>
    {:else if nilaiAwal !== null}
        <p class="text-sm text-muted-foreground">
            Sumber dana: {nilaiAwal.sumber_dana}
        </p>
    {/if}

    {#if kuitansi === null && kandidatRapel.length > 0}
        <fieldset class="grid gap-1 rounded-md border p-3 text-sm">
            <legend class="px-1 font-medium">
                Rapel: gabungkan bulan lain
            </legend>
            {#each kandidatRapel as kandidat (kandidat.id)}
                <label class="flex items-center gap-2">
                    <input
                        type="checkbox"
                        checked={rapelDipilih.includes(kandidat.id)}
                        onchange={(e) =>
                            pilihRapel(kandidat.id, e.currentTarget.checked)}
                    />
                    {namaBulan[kandidat.bulan - 1]} · Rp {rupiah(
                        kandidat.jumlah,
                    )}
                </label>
            {/each}
            <InputError message={galat.kegiatan_rapel_ids} />
        </fieldset>
    {/if}

    <InputError message={galat.kegiatan} />

    <Label for="kuitansi-nomor">Nomor bukti</Label>
    <Input id="kuitansi-nomor" bind:value={form.nomor} />
    <InputError message={form.errors.nomor} />

    <Label for="kuitansi-untuk">Untuk pembayaran</Label>
    <Input
        id="kuitansi-untuk"
        maxlength={200}
        bind:value={form.untuk_pembayaran}
    />
    <InputError message={form.errors.untuk_pembayaran} />

    <Label for="kuitansi-penerima">Penerima</Label>
    <Input id="kuitansi-penerima" bind:value={form.penerima} />
    <InputError message={form.errors.penerima} />

    <Label for="kuitansi-tanggal">Tanggal lunas</Label>
    <Input id="kuitansi-tanggal" type="date" bind:value={form.tanggal_lunas} />
    <InputError message={form.errors.tanggal_lunas} />

    <Label for="kuitansi-jumlah">Jumlah (Rp)</Label>
    <Input
        id="kuitansi-jumlah"
        type="number"
        min="1"
        step="1"
        bind:value={form.jumlah}
    />
    <InputError message={form.errors.jumlah} />
    <p class="text-sm">
        Terbilang:
        {#if terbilang !== null}
            <span class="font-medium">{terbilang}</span>
        {:else}
            <span class="text-muted-foreground">
                dihitung ulang setelah disimpan
            </span>
        {/if}
    </p>

    <div class="flex gap-2">
        <Button type="submit" disabled={form.processing}>
            {kuitansi === null ? 'Buat kuitansi' : 'Simpan kuitansi'}
        </Button>
        {#if kuitansi !== null}
            <Button
                type="button"
                variant="outline"
                disabled={hapus.processing}
                onclick={hapusKuitansi}
            >
                Hapus kuitansi
            </Button>
        {/if}
    </div>
</form>
