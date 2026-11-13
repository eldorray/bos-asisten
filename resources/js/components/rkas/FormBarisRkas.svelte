<script module lang="ts">
    export type RefKode = { kode: string; uraian: string };
    export type PilihanKegiatan = {
        id: number;
        kode: string;
        uraian: string;
        induk: RefKode | null;
        program: RefKode | null;
        honor: boolean;
    };
    export type PilihanRekening = {
        id: number;
        kode: string;
        nama: string;
        punya_barang: boolean;
    };
    export type PilihanBarang = {
        id: number;
        nama: string;
        satuan: string;
        harga: number;
        batas_bawah: number;
        batas_atas: number;
        kode_rekening: string | null;
    };
    export type PilihanPtk = {
        id: number;
        nama: string;
        nuptk: string | null;
        jenis: number;
    };
    export type Pilihan = {
        kegiatan: PilihanKegiatan[];
        rekening: PilihanRekening[];
        barang: PilihanBarang[];
        satuan: { id: number; nama: string }[];
        ptk: PilihanPtk[];
    };
    export type BarisRkas = {
        id: number;
        uuid: string;
        urutan: number;
        ref_kegiatan_id: number;
        ref_rekening_id: number;
        ref_barang_id: number | null;
        ptk_id: number | null;
        kegiatan: {
            kode: string;
            uraian: string;
            induk: RefKode | null;
            program: RefKode | null;
        };
        rekening: { kode: string; nama: string };
        kegiatan_honor: boolean;
        rekening_punya_barang: boolean;
        barang: PilihanBarang | null;
        uraian: string;
        satuan: string;
        harga: number;
        volume_bulan: Record<string, number>;
        volume: number;
        jumlah: number;
        keterangan: string | null;
        masalah: string[];
        peringatan: string[];
    };
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import BarisRkasController from '@/actions/App/Http/Controllers/Rkas/BarisRkasController';
    import InputError from '@/components/InputError.svelte';
    import PilihCari from '@/components/PilihCari.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { BULAN } from '@/lib/rkas';

    let {
        kertasKerjaUuid,
        pilihan,
        baris = null,
        kegiatanAwal = '',
        sisaPagu,
        onSelesai,
    }: {
        kertasKerjaUuid: string;
        pilihan: Pilihan;
        baris?: BarisRkas | null;
        /** Kegiatan yang sudah terpilih saat form dibuka dari kelompoknya. */
        kegiatanAwal?: number | '';
        sisaPagu: number;
        onSelesai?: () => void;
    } = $props();

    let isiCepat = $state('1');
    let pesanTersimpan = $state('');

    function volumeAwal(): Record<string, string> {
        const isi: Record<string, string> = {};
        for (let bulan = 1; bulan <= 12; bulan++) {
            const nilai = baris?.volume_bulan[String(bulan)] ?? 0;
            isi[String(bulan)] = nilai === 0 ? '' : String(nilai);
        }

        return isi;
    }

    // Komponen ini dibuat ulang lewat {#key} oleh pemakainya, jadi nilai awal memang diambil sekali.
    /* svelte-ignore state_referenced_locally */
    const form = useForm({
        ref_kegiatan_id: baris?.ref_kegiatan_id ?? kegiatanAwal,
        ref_rekening_id: baris?.ref_rekening_id ?? ('' as number | ''),
        ref_barang_id: baris?.ref_barang_id ?? ('' as number | ''),
        ptk_id: baris?.ptk_id ?? ('' as number | ''),
        uraian: baris?.uraian ?? '',
        satuan: baris?.satuan ?? '',
        harga: baris ? String(baris.harga) : '',
        volume_bulan: volumeAwal(),
        keterangan: baris?.keterangan ?? '',
    });

    // Baris lama boleh tetap memakai kegiatan atau rekening yang sudah kedaluwarsa.
    const kegiatanOpsi = $derived(
        baris && !pilihan.kegiatan.some((k) => k.id === baris.ref_kegiatan_id)
            ? [
                  ...pilihan.kegiatan,
                  {
                      id: baris.ref_kegiatan_id,
                      kode: baris.kegiatan.kode,
                      uraian: `${baris.kegiatan.uraian} (kedaluwarsa)`,
                      honor: baris.kegiatan_honor,
                  },
              ]
            : pilihan.kegiatan,
    );
    const rekeningOpsi = $derived(
        baris && !pilihan.rekening.some((r) => r.id === baris.ref_rekening_id)
            ? [
                  ...pilihan.rekening,
                  {
                      id: baris.ref_rekening_id,
                      kode: baris.rekening.kode,
                      nama: `${baris.rekening.nama} (kedaluwarsa)`,
                      punya_barang: baris.rekening_punya_barang,
                  },
              ]
            : pilihan.rekening,
    );

    const kegiatan = $derived(
        kegiatanOpsi.find((k) => k.id === form.ref_kegiatan_id),
    );
    const rekening = $derived(
        rekeningOpsi.find((r) => r.id === form.ref_rekening_id),
    );
    const semuaBarang = $derived(
        baris?.barang && !pilihan.barang.some((b) => b.id === baris.barang?.id)
            ? [...pilihan.barang, baris.barang]
            : pilihan.barang,
    );
    const honor = $derived(kegiatan?.honor ?? false);
    const pakaiBarang = $derived(
        !honor && rekening !== undefined && rekening.punya_barang,
    );
    const barangOpsi = $derived.by(() => {
        if (!rekening) {
            return [];
        }
        const sendiri = semuaBarang.filter(
            (b) => b.kode_rekening === rekening.kode,
        );

        return sendiri.length > 0 || !rekening.kode.startsWith('5.2')
            ? sendiri
            : semuaBarang.filter((b) => b.kode_rekening === null);
    });
    const barang = $derived(
        semuaBarang.find((b) => b.id === form.ref_barang_id),
    );

    // Total dan jumlah hanya untuk tampilan; angka resmi dihitung server.
    const totalVolume = $derived(
        Object.values(form.volume_bulan).reduce(
            (jumlah, v) => jumlah + (Number(v) || 0),
            0,
        ),
    );
    const jumlah = $derived(totalVolume * (Number(form.harga) || 0));
    // Saat mengubah, jumlah lama baris ini dikembalikan dulu ke sisa.
    const sisaSetelah = $derived(sisaPagu + (baris?.jumlah ?? 0) - jumlah);

    function isiBulan(dari: number, sampai: number, nilai: string): void {
        for (let bulan = dari; bulan <= sampai; bulan++) {
            form.volume_bulan[String(bulan)] = nilai;
        }
    }

    const opsiKegiatan = $derived(
        kegiatanOpsi.map((k) => ({ value: k.id, label: `${k.kode} ${k.uraian}` })),
    );
    const opsiRekening = $derived(
        rekeningOpsi.map((r) => ({ value: r.id, label: `${r.kode} ${r.nama}` })),
    );
    const opsiBarang = $derived(
        barangOpsi.map((b) => ({ value: b.id, label: `${b.nama} (${b.satuan})` })),
    );
    const opsiPtk = $derived(
        pilihan.ptk.map((p) => ({
            value: p.id,
            label: p.nuptk ? `${p.nama} (${p.nuptk})` : p.nama,
        })),
    );
    const opsiSatuan = $derived(
        pilihan.satuan.map((s) => ({ value: s.nama, label: s.nama })),
    );

    function rupiah(n: number): string {
        return `Rp ${n.toLocaleString('id-ID')}`;
    }

    function pilihBarang(id: number | ''): void {
        form.ref_barang_id = id;
        const dipilih = semuaBarang.find((b) => b.id === id);
        if (dipilih) {
            form.harga = String(dipilih.harga);
        }
    }

    function angka(nilai: string): string {
        return nilai.replace(/\D/g, '');
    }

    const galat = $derived(form.errors as Record<string, string>);
    // Galat untuk kolom yang tidak tampil pada mode ini tetap harus terlihat.
    const kolomTampil = $derived([
        'kertas_kerja',
        'ref_kegiatan_id',
        'ref_rekening_id',
        'harga',
        'keterangan',
        ...(honor ? ['ptk_id', 'satuan'] : []),
        ...(pakaiBarang ? ['ref_barang_id'] : []),
        ...(!honor && !pakaiBarang ? ['uraian', 'satuan'] : []),
    ]);
    const galatLain = $derived(
        Object.entries(galat)
            .filter(
                ([kunci]) =>
                    !kolomTampil.includes(kunci) &&
                    !kunci.startsWith('volume_bulan'),
            )
            .map(([kunci, pesan]) => ({ kunci, pesan })),
    );
    const galatVolume = $derived(
        Object.entries(galat)
            .filter(([kunci]) => kunci.startsWith('volume_bulan'))
            .map(([, pesan]) => pesan),
    );

    function kirim(event: SubmitEvent): void {
        event.preventDefault();
        simpan(false);
    }

    function simpan(lagi: boolean): void {
        pesanTersimpan = '';
        form
            .transform((data) => ({
                ref_kegiatan_id: data.ref_kegiatan_id || null,
                ref_rekening_id: data.ref_rekening_id || null,
                ref_barang_id: pakaiBarang ? data.ref_barang_id || null : null,
                ptk_id: honor ? data.ptk_id || null : null,
                uraian: pakaiBarang || honor ? null : data.uraian,
                satuan: pakaiBarang ? null : data.satuan,
                harga: Number(data.harga) || 0,
                volume_bulan: Object.fromEntries(
                    Object.entries(data.volume_bulan).map(([bulan, v]) => [
                        bulan,
                        Number(v) || 0,
                    ]),
                ),
                keterangan: data.keterangan || null,
            }))
            .submit(
                baris === null
                    ? BarisRkasController.store(kertasKerjaUuid)
                    : BarisRkasController.update(baris.uuid),
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        const kegiatanTerakhir = form.ref_kegiatan_id;
                        form.reset();

                        if (!lagi) {
                            onSelesai?.();

                            return;
                        }

                        // Belanja berikutnya biasanya masih di kegiatan yang sama.
                        form.ref_kegiatan_id = kegiatanTerakhir;
                        pesanTersimpan =
                            'Tersimpan. Kegiatan tetap, tinggal isi belanja berikutnya.';
                    },
                },
            );
    }
</script>

<form class="grid grid-cols-1 gap-4" onsubmit={kirim}>
    {#if pesanTersimpan}
        <p
            role="status"
            class="rounded-[10px] bg-selesai px-3 py-2.5 text-sm text-selesai-foreground"
        >
            {pesanTersimpan}
        </p>
    {/if}
    <InputError message={galat.kertas_kerja} />
    {#each galatLain as { kunci, pesan } (kunci)}
        <InputError message={pesan} />
    {/each}

    <div class="grid gap-4 md:grid-cols-2">
        <div class="grid content-start gap-1.5">
            <Label for="baris-kegiatan">Kegiatan</Label>
            <PilihCari
                id="baris-kegiatan"
                opsi={opsiKegiatan}
                kosong="Pilih kegiatan"
                value={form.ref_kegiatan_id}
                onchange={(v) => (form.ref_kegiatan_id = v as number | '')}
            />
            <InputError message={form.errors.ref_kegiatan_id} />
        </div>

        <div class="grid content-start gap-1.5">
            <Label for="baris-rekening">Rekening belanja</Label>
            <PilihCari
                id="baris-rekening"
                opsi={opsiRekening}
                kosong="Pilih rekening"
                value={form.ref_rekening_id}
                onchange={(v) => {
                    form.ref_rekening_id = v as number | '';
                    form.ref_barang_id = '';
                }}
            />
            <InputError message={form.errors.ref_rekening_id} />
        </div>
    </div>

    {#if honor}
        <div class="grid content-start gap-1.5">
            <Label for="baris-ptk">PTK</Label>
            <PilihCari
                id="baris-ptk"
                opsi={opsiPtk}
                kosong="Pilih PTK"
                value={form.ptk_id}
                onchange={(v) => (form.ptk_id = v as number | '')}
            />
            <InputError message={form.errors.ptk_id} />
        </div>
    {:else if pakaiBarang}
        <div class="grid content-start gap-1.5">
            <Label for="baris-barang">Barang/jasa</Label>
            <PilihCari
                id="baris-barang"
                opsi={opsiBarang}
                kosong="Pilih barang/jasa"
                value={form.ref_barang_id}
                onchange={(v) => pilihBarang(v as number | '')}
            />
            {#if barang}
                <p class="text-xs text-muted-foreground">
                    Satuan {barang.satuan} · batas harga {rupiah(barang.batas_bawah)}
                    {barang.batas_atas > 0
                        ? `sampai ${rupiah(barang.batas_atas)}`
                        : 'tanpa batas atas'}
                </p>
            {/if}
            <InputError message={form.errors.ref_barang_id} />
        </div>
    {:else}
        <div class="grid gap-4 md:grid-cols-[1fr_12rem]">
            <div class="grid content-start gap-1.5">
                <Label for="baris-uraian">Uraian</Label>
                <Input
                    id="baris-uraian"
                    maxlength={500}
                    bind:value={form.uraian}
                />
                <InputError message={form.errors.uraian} />
            </div>
            <div class="grid content-start gap-1.5">
                <Label for="baris-satuan">Satuan</Label>
                <PilihCari
                    id="baris-satuan"
                    opsi={opsiSatuan}
                    kosong="Pilih satuan"
                    value={form.satuan}
                    onchange={(v) => (form.satuan = String(v))}
                />
                <InputError message={form.errors.satuan} />
            </div>
        </div>
    {/if}

    {#if honor}
        <div class="grid content-start gap-1.5 md:max-w-48">
            <Label for="baris-satuan-honor">Satuan</Label>
            <PilihCari
                id="baris-satuan-honor"
                opsi={opsiSatuan}
                kosong="Pilih satuan"
                value={form.satuan}
                onchange={(v) => (form.satuan = String(v))}
            />
            <InputError message={form.errors.satuan} />
        </div>
    {/if}

    <div class="grid content-start gap-1.5 md:max-w-64">
        <Label for="baris-harga">Harga satuan</Label>
        <Input
            id="baris-harga"
            inputmode="numeric"
            value={form.harga}
            oninput={(e) =>
                (form.harga = angka((e.target as HTMLInputElement).value))}
        />
        <InputError message={form.errors.harga} />
    </div>

    <fieldset class="grid gap-2.5 rounded-xl border p-3">
        <legend class="px-1 text-sm font-medium">Volume per bulan</legend>
        <div class="flex flex-wrap items-center gap-2">
            <Label for="baris-isi-cepat" class="text-muted-foreground">
                Isi cepat
            </Label>
            <Input
                id="baris-isi-cepat"
                class="w-18 text-center"
                inputmode="numeric"
                maxlength={6}
                value={isiCepat}
                oninput={(e) =>
                    (isiCepat = angka((e.target as HTMLInputElement).value))}
            />
            <Button
                type="button"
                variant="secondary"
                onclick={() => isiBulan(1, 12, isiCepat)}
            >
                Tiap bulan
            </Button>
            <Button
                type="button"
                variant="secondary"
                onclick={() => isiBulan(1, 6, isiCepat)}
            >
                Tahap 1
            </Button>
            <Button
                type="button"
                variant="secondary"
                onclick={() => isiBulan(7, 12, isiCepat)}
            >
                Tahap 2
            </Button>
            <Button
                type="button"
                variant="ghost"
                onclick={() => isiBulan(1, 12, '')}
            >
                Kosongkan
            </Button>
        </div>
        <div class="grid grid-cols-4 gap-2 md:grid-cols-6">
            {#each BULAN as nama, i (nama)}
                <div class="grid gap-0.5">
                    <span class="text-xs text-muted-foreground">{nama}</span>
                    <Input
                        aria-label={`Volume ${nama}`}
                        inputmode="numeric"
                        maxlength={6}
                        value={form.volume_bulan[String(i + 1)]}
                        oninput={(e) =>
                            (form.volume_bulan[String(i + 1)] = angka(
                                (e.target as HTMLInputElement).value,
                            ))}
                    />
                </div>
            {/each}
        </div>
        {#each galatVolume as pesan (pesan)}
            <InputError message={pesan} />
        {/each}
    </fieldset>

    <div class="grid content-start gap-1.5">
        <Label for="baris-keterangan">Keterangan (opsional)</Label>
        <Input
            id="baris-keterangan"
            maxlength={500}
            bind:value={form.keterangan}
        />
        <InputError message={form.errors.keterangan} />
    </div>

    <!-- Menempel di bawah dialog, supaya jumlah dan tombol simpan selalu terlihat. -->
    <div
        class="sticky -bottom-6 -mx-6 -mb-6 grid gap-2.5 border-t bg-background px-6 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]"
    >
        <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 text-sm">
            <span>
                Volume
                <strong class="font-semibold tabular-nums">
                    {totalVolume.toLocaleString('id-ID')}
                </strong>
                × harga =
                <strong class="font-semibold tabular-nums">
                    {rupiah(jumlah)}
                </strong>
            </span>
            <span
                class={sisaSetelah < 0
                    ? 'text-belum-foreground'
                    : 'text-muted-foreground'}
            >
                Sisa pagu setelah ini
                <strong class="font-semibold tabular-nums">
                    {sisaSetelah < 0 ? '-' : ''}{rupiah(Math.abs(sisaSetelah))}
                </strong>
            </span>
        </div>
        <div class="flex flex-wrap justify-end gap-2">
            <Button
                type="button"
                variant="ghost"
                onclick={() => onSelesai?.()}
            >
                Tutup
            </Button>
            <Button
                type="submit"
                variant={baris === null ? 'outline' : 'default'}
                disabled={form.processing}
            >
                Simpan
            </Button>
            {#if baris === null}
                <Button
                    type="button"
                    class="max-md:flex-1"
                    disabled={form.processing}
                    onclick={() => simpan(true)}
                >
                    Simpan dan tambah lagi
                </Button>
            {/if}
        </div>
    </div>
</form>
