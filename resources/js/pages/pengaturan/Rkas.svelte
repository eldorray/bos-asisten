<script module lang="ts">
    import PengaturanRkasController from '@/actions/App/Http/Controllers/Rkas/PengaturanRkasController';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Pengaturan RKAS',
                href: PengaturanRkasController.edit(),
            },
        ],
    };
</script>

<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type Pengaturan = {
        id: number;
        swasta: boolean;
        persen_tahap_1: number;
        ambang_tahap_1: number;
        honor_maks_negeri: number;
        honor_maks_swasta: number;
        buku_min: number;
        sarpras_maks: number;
        kode_honor: string[];
        rekening_honor: string;
        kode_buku: string[];
        kode_sarpras: string[];
        rekening_sarpras_lain: string[];
        kuota_pergeseran: number | null;
        kuota_perubahan: number | null;
    };

    type Sekolah = { id: number; nama: string };

    let {
        pengaturan,
        sekolah,
        pilihanSekolah,
        tahun,
    }: {
        pengaturan: Pengaturan;
        sekolah: Sekolah;
        pilihanSekolah: Sekolah[];
        tahun: number;
    } = $props();

    /* svelte-ignore state_referenced_locally */
    const form = useForm({
        swasta: pengaturan.swasta,
        persen_tahap_1: String(pengaturan.persen_tahap_1),
        ambang_tahap_1: String(pengaturan.ambang_tahap_1),
        honor_maks_negeri: String(pengaturan.honor_maks_negeri),
        honor_maks_swasta: String(pengaturan.honor_maks_swasta),
        buku_min: String(pengaturan.buku_min),
        sarpras_maks: String(pengaturan.sarpras_maks),
        kode_honor: pengaturan.kode_honor.join(', '),
        rekening_honor: pengaturan.rekening_honor,
        kode_buku: pengaturan.kode_buku.join(', '),
        kode_sarpras: pengaturan.kode_sarpras.join(', '),
        rekening_sarpras_lain: pengaturan.rekening_sarpras_lain.join(', '),
        kuota_pergeseran: String(pengaturan.kuota_pergeseran ?? ''),
        kuota_perubahan: String(pengaturan.kuota_perubahan ?? ''),
    });

    type Kunci =
        | 'persen_tahap_1'
        | 'ambang_tahap_1'
        | 'honor_maks_negeri'
        | 'honor_maks_swasta'
        | 'buku_min'
        | 'sarpras_maks'
        | 'kode_honor'
        | 'rekening_honor'
        | 'kode_buku'
        | 'kode_sarpras'
        | 'rekening_sarpras_lain'
        | 'kuota_pergeseran'
        | 'kuota_perubahan';

    const batas: { kunci: Kunci; label: string }[] = [
        { kunci: 'persen_tahap_1', label: 'Persen penyaluran tahap 1 (%)' },
        { kunci: 'ambang_tahap_1', label: 'Ambang belanja tahap 1 (%)' },
        { kunci: 'honor_maks_negeri', label: 'Batas honor sekolah negeri (%)' },
        { kunci: 'honor_maks_swasta', label: 'Batas honor sekolah swasta (%)' },
        { kunci: 'buku_min', label: 'Batas minimal buku (%)' },
        { kunci: 'sarpras_maks', label: 'Batas maksimal sarpras (%)' },
    ];

    const daftar: { kunci: Kunci; label: string; petunjuk: string }[] = [
        {
            kunci: 'kode_honor',
            label: 'Kode kegiatan honor',
            petunjuk: 'Pisahkan dengan koma, contoh 07.12.01.',
        },
        {
            kunci: 'rekening_honor',
            label: 'Kode rekening honor',
            petunjuk: 'Contoh 5.1.02.02.01.0013',
        },
        {
            kunci: 'kode_buku',
            label: 'Kode kegiatan buku',
            petunjuk: 'Pisahkan dengan koma, contoh 03.02.02.',
        },
        {
            kunci: 'kode_sarpras',
            label: 'Kode kegiatan sarpras',
            petunjuk: 'Pisahkan dengan koma, contoh 05.08.01.',
        },
        {
            kunci: 'rekening_sarpras_lain',
            label: 'Rekening sarpras lain',
            petunjuk: 'Pisahkan dengan koma, contoh 5.2.02.05.02.0001',
        },
    ];

    const kuota: { kunci: Kunci; label: string }[] = [
        { kunci: 'kuota_pergeseran', label: 'Kuota pergeseran (kosong = tanpa batas)' },
        { kunci: 'kuota_perubahan', label: 'Kuota perubahan (kosong = tanpa batas)' },
    ];

    function bukaHalaman(sekolahId: number, tahunDipilih: number): void {
        router.get(
            PengaturanRkasController.edit({
                query: { sekolah_id: sekolahId, tahun: tahunDipilih },
            }).url,
        );
    }

    function simpan(event: SubmitEvent): void {
        event.preventDefault();
        form.submit(PengaturanRkasController.update(pengaturan.id), {
            preserveScroll: true,
        });
    }
</script>

<AppHead title="Pengaturan RKAS" />

<div class="flex flex-col gap-5 p-5 md:p-7">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <Heading
            title="Pengaturan RKAS"
            description="Batas juknis dan kode kegiatan untuk draf RKAS per tahun anggaran."
        />

        <div class="flex flex-wrap items-center gap-2.5">
            {#if pilihanSekolah.length > 1}
                <Label for="sekolah" class="shrink-0">Sekolah</Label>
                <select
                    id="sekolah"
                    class="h-9 rounded-md border bg-background px-2 text-sm"
                    value={sekolah.id}
                    onchange={(e) =>
                        bukaHalaman(
                            Number((e.target as HTMLSelectElement).value),
                            tahun,
                        )}
                >
                    {#each pilihanSekolah as pilihan (pilihan.id)}
                        <option value={pilihan.id}>{pilihan.nama}</option>
                    {/each}
                </select>
            {/if}
            <Label for="tahun" class="shrink-0">Tahun anggaran</Label>
            <Input
                id="tahun"
                class="w-24"
                type="number"
                min="2000"
                max="2100"
                value={tahun}
                onchange={(e) =>
                    bukaHalaman(
                        sekolah.id,
                        Number((e.target as HTMLInputElement).value),
                    )}
            />
        </div>
    </div>

    <Card>
        <CardHeader class="border-b">
            <CardTitle class="text-base">
                {sekolah.nama} · {tahun}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <form class="grid gap-4" onsubmit={simpan}>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input
                        type="checkbox"
                        class="size-4"
                        checked={form.swasta}
                        onchange={(e) =>
                            (form.swasta = (e.target as HTMLInputElement)
                                .checked)}
                    />
                    Sekolah swasta
                </label>
                <InputError message={form.errors.swasta} />

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {#each batas as { kunci, label } (kunci)}
                        <div class="grid content-start gap-1">
                            <Label for={kunci}>{label}</Label>
                            <Input
                                id={kunci}
                                type="number"
                                value={form[kunci]}
                                oninput={(e) =>
                                    (form[kunci] = (
                                        e.target as HTMLInputElement
                                    ).value)}
                            />
                            <InputError message={form.errors[kunci]} />
                        </div>
                    {/each}
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    {#each daftar as { kunci, label, petunjuk } (kunci)}
                        <div class="grid content-start gap-1">
                            <Label for={kunci}>{label}</Label>
                            <Input
                                id={kunci}
                                value={form[kunci]}
                                oninput={(e) =>
                                    (form[kunci] = (
                                        e.target as HTMLInputElement
                                    ).value)}
                            />
                            <p class="text-xs text-muted-foreground">
                                {petunjuk}
                            </p>
                            <InputError message={form.errors[kunci]} />
                            {#each Object.entries(form.errors).filter( ([k]) => k.startsWith(`${kunci}.`), ) as [k, pesan] (k)}
                                <InputError message={pesan} />
                            {/each}
                        </div>
                    {/each}
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    {#each kuota as { kunci, label } (kunci)}
                        <div class="grid content-start gap-1">
                            <Label for={kunci}>{label}</Label>
                            <Input
                                id={kunci}
                                type="number"
                                min="0"
                                value={form[kunci]}
                                oninput={(e) =>
                                    (form[kunci] = (
                                        e.target as HTMLInputElement
                                    ).value)}
                            />
                            <InputError message={form.errors[kunci]} />
                        </div>
                    {/each}
                </div>

                <div>
                    <Button type="submit" disabled={form.processing}>
                        Simpan
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</div>
