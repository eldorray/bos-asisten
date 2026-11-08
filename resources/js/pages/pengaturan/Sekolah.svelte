<script module lang="ts">
    import SekolahController from '@/actions/App/Http/Controllers/SekolahController';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Pengaturan sekolah',
                href: SekolahController.index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import School from '@lucide/svelte/icons/school';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import HapusSekolah from '@/components/spj/HapusSekolah.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type SekolahData = {
        id: number;
        nama: string;
        sebutan: string;
        sistem: string;
        identitas: string;
        desa_kecamatan: string;
        kabupaten: string;
        provinsi: string;
        kode_bukti: string;
        nama_kepala: string;
        nama_bendahara: string;
        nomor_awal: Record<string, number>;
    };

    let {
        sekolahs,
        tahun: tahunAwal,
    }: {
        sekolahs: SekolahData[];
        tahun: number;
    } = $props();

    // Nilai awal formulir memang diambil sekali dari props.
    /* svelte-ignore state_referenced_locally */
    let tahun = $state(tahunAwal);

    function nomorUntuk(sekolah: SekolahData, tahunDipilih: number): string {
        return String(sekolah.nomor_awal[String(tahunDipilih)] ?? '');
    }

    /* svelte-ignore state_referenced_locally */
    // Per id, bukan per urutan: daftar bisa berkurang saat sekolah dihapus.
    const forms = new Map(
        sekolahs.map((sekolah) => [
            sekolah.id,
            useForm({
            nama: sekolah.nama,
            sebutan: sekolah.sebutan,
            desa_kecamatan: sekolah.desa_kecamatan,
            kabupaten: sekolah.kabupaten,
            provinsi: sekolah.provinsi,
            kode_bukti: sekolah.kode_bukti,
            nama_kepala: sekolah.nama_kepala,
            nama_bendahara: sekolah.nama_bendahara,
            nomor_awal: nomorUntuk(sekolah, tahunAwal),
            }),
        ]),
    );

    function gantiTahun(event: Event): void {
        const nilai = Number((event.target as HTMLInputElement).value);
        tahun = nilai;
        sekolahs.forEach((sekolah) => {
            const form = forms.get(sekolah.id);
            if (form) {
                form.nomor_awal = nomorUntuk(sekolah, nilai);
            }
        });
    }

    function simpan(event: SubmitEvent, sekolah: SekolahData): void {
        event.preventDefault();
        forms
            .get(sekolah.id)
            ?.transform((data) => ({ ...data, tahun }))
            .submit(SekolahController.update(sekolah.id), {
                preserveScroll: true,
            });
    }

    function urutkanNomor(sekolah: SekolahData): void {
        if (
            confirm(
                `Urutkan nomor bukti ${sekolah.nama} tahun ${tahun} per bulan? Nomor di kuitansi yang sudah dicetak atau ditandatangani bisa berubah, jadi cetak ulang bila perlu.`,
            )
        ) {
            router.post(
                SekolahController.urutkanNomor(sekolah.id).url,
                { tahun },
                { preserveScroll: true },
            );
        }
    }

    type Kunci =
        | 'nama'
        | 'sebutan'
        | 'desa_kecamatan'
        | 'kabupaten'
        | 'provinsi'
        | 'kode_bukti'
        | 'nama_kepala'
        | 'nama_bendahara';

    const kolom: { kunci: Kunci; label: string }[] = [
        { kunci: 'nama', label: 'Nama sekolah' },
        { kunci: 'sebutan', label: 'Sebutan (Sekolah / Madrasah)' },
        { kunci: 'desa_kecamatan', label: 'Desa / Kecamatan' },
        { kunci: 'kabupaten', label: 'Kabupaten / Kota' },
        { kunci: 'provinsi', label: 'Provinsi' },
        { kunci: 'kode_bukti', label: 'Kode nomor bukti' },
        { kunci: 'nama_kepala', label: 'Nama kepala' },
        { kunci: 'nama_bendahara', label: 'Nama bendahara' },
    ];
</script>

<AppHead title="Pengaturan sekolah" />

<div class="flex flex-col gap-5 p-5 md:p-7">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <Heading
            title="Pengaturan sekolah"
            description="Data sekolah dan penandatangan yang tercetak pada kuitansi."
        />

        <div class="flex items-center gap-2.5">
            <Label for="tahun" class="shrink-0">Tahun nomor awal</Label>
            <Input
                id="tahun"
                class="w-24"
                type="number"
                min="2000"
                max="2100"
                value={tahun}
                onchange={gantiTahun}
            />
        </div>
    </div>

    <div class="grid items-start gap-3.5 xl:grid-cols-2">
        {#each sekolahs as sekolah (sekolah.id)}
            {@const form = forms.get(sekolah.id)!}
            <Card>
                <CardHeader class="border-b">
                    <div class="flex items-center gap-3">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-[11px] bg-primary text-primary-foreground"
                        >
                            <School class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <CardTitle class="text-base">
                                {sekolah.nama}
                            </CardTitle>
                            <p class="mt-1 text-[13px] text-muted-foreground">
                                {sekolah.sistem.toUpperCase()} · {sekolah.identitas}
                            </p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <form
                        class="grid gap-3 sm:grid-cols-2"
                        onsubmit={(e) => simpan(e, sekolah)}
                    >
                        {#each kolom as { kunci, label } (kunci)}
                            <div class="grid content-start gap-1">
                                <Label for={`${kunci}-${sekolah.id}`}>
                                    {label}
                                </Label>
                                <Input
                                    id={`${kunci}-${sekolah.id}`}
                                    value={form[kunci]}
                                    oninput={(e) =>
                                        (form[kunci] = (
                                            e.target as HTMLInputElement
                                        ).value)}
                                />
                                <InputError message={form.errors[kunci]} />
                            </div>
                        {/each}
                        <div class="grid content-start gap-1">
                            <Label for={`nomor_awal-${sekolah.id}`}>
                                Nomor awal kuitansi tahun {tahun}
                            </Label>
                            <Input
                                id={`nomor_awal-${sekolah.id}`}
                                type="number"
                                min="1"
                                placeholder="1"
                                value={form.nomor_awal}
                                oninput={(e) =>
                                    (form.nomor_awal = (
                                        e.target as HTMLInputElement
                                    ).value)}
                            />
                            <InputError message={form.errors.nomor_awal} />
                            <InputError message={(form.errors as Record<string, string>).tahun} />
                        </div>
                        <div class="flex flex-wrap gap-2 sm:col-span-2">
                            <Button type="submit" disabled={form.processing}>
                                Simpan
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onclick={() => urutkanNomor(sekolah)}
                            >
                                Urutkan nomor per bulan
                            </Button>
                            <span class="ml-auto">
                                <HapusSekolah {sekolah} />
                            </span>
                        </div>
                    </form>
                </CardContent>
            </Card>
        {/each}
    </div>
</div>
