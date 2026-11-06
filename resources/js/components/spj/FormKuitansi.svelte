<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import KegiatanController from '@/actions/App/Http/Controllers/KegiatanController';
    import KuitansiController from '@/actions/App/Http/Controllers/KuitansiController';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { cn } from '@/lib/utils';
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
        rapelAwal,
        bulan,
        onsimpan,
    }: {
        kegiatanId: number;
        tahun: number;
        kuitansi: KuitansiData | null;
        nilaiAwal: NilaiAwal | null;
        kandidatRapel: KandidatRapel[];
        rapelAwal: number[];
        bulan: number | null;
        onsimpan?: () => void;
    } = $props();

    let otomatisTerbuka = $state(false);

    // Tanggal lokal, bukan UTC, supaya pagi hari tidak mundur ke kemarin.
    const sekarang = new Date();
    const hariIni = [
        sekarang.getFullYear(),
        String(sekarang.getMonth() + 1).padStart(2, '0'),
        String(sekarang.getDate()).padStart(2, '0'),
    ].join('-');

    // Nilai awal formulir memang diambil sekali dari props.
    /* svelte-ignore state_referenced_locally */
    const form = useForm({
        nomor: kuitansi?.nomor ?? nilaiAwal?.nomor ?? '',
        untuk_pembayaran:
            kuitansi?.untuk_pembayaran ?? nilaiAwal?.untuk_pembayaran ?? '',
        penerima: kuitansi?.penerima ?? nilaiAwal?.penerima ?? '',
        tanggal_lunas:
            kuitansi?.tanggal_lunas ??
            // Kuitansi bulan lalu tidak boleh diam-diam bertanggal hari ini.
            (hariIni.startsWith(
                `${tahun}-${String(bulan).padStart(2, '0')}`,
            )
                ? hariIni
                : ''),
        jumlah: kuitansi?.jumlah ?? nilaiAwal?.jumlah ?? 0,
    });
    const hapus = useForm({});

    // Kolom yang galat tidak boleh tersembunyi di balik lipatan.
    const otomatisTerlihat = $derived(
        otomatisTerbuka ||
            form.errors.nomor !== undefined ||
            form.errors.untuk_pembayaran !== undefined,
    );
    const galat = $derived(form.errors as Record<string, string | undefined>);

    // Pilihan awal datang dari server, supaya muat ulang halaman tidak memisahkan
    // nilai (jumlah, teks) dari centang bulan.
    /* svelte-ignore state_referenced_locally */
    let rapelDipilih = $state<number[]>([...rapelAwal]);
    let memuat = $state(false);

    const acuan = $derived(kuitansi ?? nilaiAwal);
    const terbilang = $derived(
        acuan !== null && Number(form.jumlah) === acuan.jumlah
            ? acuan.terbilang
            : null,
    );
    const galatRapel = $derived(
        Object.entries(galat)
            .filter(([kunci]) => kunci.startsWith('kegiatan_rapel_ids'))
            .map(([, pesan]) => pesan),
    );

    function pilihRapel(id: number, aktif: boolean): void {
        rapelDipilih = aktif
            ? [...rapelDipilih, id]
            : rapelDipilih.filter((x) => x !== id);

        memuat = true;

        router.get(
            KegiatanController.show.url(kegiatanId),
            { rapel: rapelDipilih },
            {
                only: ['nilaiAwal', 'rapelDipilih'],
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

                    rapelDipilih = page.props.rapelDipilih as number[];
                },
                onFinish: () => {
                    memuat = false;
                },
            },
        );
    }

    function simpan(event: SubmitEvent): void {
        event.preventDefault();

        if (kuitansi !== null) {
            form.submit(KuitansiController.update(kuitansi.id), {
                preserveScroll: true,
                onSuccess: () => onsimpan?.(),
            });

            return;
        }

        form.transform((data) => ({
            ...data,
            kegiatan_rapel_ids: rapelDipilih,
        })).submit(KuitansiController.store(kegiatanId), {
            preserveScroll: true,
            onSuccess: () => onsimpan?.(),
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
            onSuccess: () => onsimpan?.(),
        });
    }
</script>

<form class="grid grid-cols-1 gap-3" onsubmit={simpan}>
    <div
        class="rounded-2xl bg-primary p-3.5 text-primary-foreground max-md:-order-1 md:hidden"
    >
        <div class="text-[13px] opacity-80">Jumlah</div>
        <div class="mt-0.5 text-[26px] leading-tight font-semibold tracking-tight tabular-nums">
            Rp {rupiah(Number(form.jumlah))}
        </div>
        <div class="mt-1.5 text-sm opacity-80">
            {terbilang ?? 'Terbilang dihitung ulang setelah disimpan'}
        </div>
    </div>

    {#if kuitansi !== null}
        <p class="text-sm text-muted-foreground max-md:order-3">
            Mencakup {kuitansi.bulanCakupan
                .map((b) => namaBulan[b - 1])
                .join(', ')} · {kuitansi.sumberDana}
        </p>
    {:else if nilaiAwal !== null}
        <p class="text-sm text-muted-foreground max-md:order-3">
            Sumber dana: {nilaiAwal.sumber_dana}
        </p>
    {/if}

    {#if kuitansi === null && kandidatRapel.length > 0}
        <fieldset class="grid gap-1 rounded-[10px] border p-3 text-sm max-md:order-2">
            <legend class="px-1 font-medium">
                Rapel: gabungkan bulan lain
            </legend>
            {#each kandidatRapel as kandidat (kandidat.id)}
                <label class="flex min-h-11 items-center gap-2.5">
                    <input
                        type="checkbox"
                        class="size-5 shrink-0 accent-primary"
                        checked={rapelDipilih.includes(kandidat.id)}
                        onchange={(e) =>
                            pilihRapel(kandidat.id, e.currentTarget.checked)}
                    />
                    {namaBulan[kandidat.bulan - 1]} · Rp {rupiah(
                        kandidat.jumlah,
                    )}
                </label>
            {/each}
            {#each galatRapel as pesan}
                <InputError message={pesan} />
            {/each}
        </fieldset>
    {/if}

    <InputError message={galat.kegiatan} />

    <!-- Di HP terlipat: nilainya sudah terisi otomatis dan jarang diubah. -->
    <button
        type="button"
        aria-expanded={otomatisTerlihat}
        class="flex min-h-14 items-center justify-between gap-2 rounded-2xl border px-3.5 py-1.5 text-left max-md:order-3 md:hidden"
        onclick={() => (otomatisTerbuka = !otomatisTerbuka)}
    >
        <span class="min-w-0">
            <span class="block font-semibold">Terisi otomatis</span>
            <span class="block truncate text-[13px] text-muted-foreground">
                Nomor {form.nomor}
            </span>
        </span>
        <ChevronDown
            class={cn('size-5 shrink-0', otomatisTerlihat && 'rotate-180')}
        />
    </button>
    <div
        class={[
            'gap-3 max-md:order-3 md:grid',
            otomatisTerlihat ? 'grid' : 'hidden',
        ]}
    >
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
    </div>

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
        inputmode="numeric"
        min="1"
        step="1"
        bind:value={form.jumlah}
    />
    <InputError message={form.errors.jumlah} />
    <p class="rounded-[10px] bg-muted px-3 py-2.5 text-sm max-md:hidden">
        Terbilang:
        {#if terbilang !== null}
            <span class="font-medium">{terbilang}</span>
        {:else}
            <span class="text-muted-foreground">
                dihitung ulang setelah disimpan
            </span>
        {/if}
    </p>

    <div
        class="flex flex-wrap gap-2 max-md:sticky max-md:bottom-0 max-md:order-4 max-md:-mx-4 max-md:border-t max-md:bg-card max-md:px-4 max-md:py-3"
    >
        <Button
            type="submit"
            class="max-md:h-14 max-md:w-full max-md:text-base max-md:font-semibold"
            disabled={form.processing || memuat}
        >
            {kuitansi === null ? 'Buat kuitansi' : 'Simpan kuitansi'}
        </Button>
        {#if kuitansi !== null}
            <Button
                type="button"
                variant="outline"
                class="max-md:w-full max-md:text-belum-foreground"
                disabled={hapus.processing}
                onclick={hapusKuitansi}
            >
                Hapus kuitansi
            </Button>
        {/if}
    </div>
</form>
