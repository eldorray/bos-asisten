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
    import { useForm } from '@inertiajs/svelte';
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
    const forms = sekolahs.map((sekolah) =>
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
    );

    function gantiTahun(event: Event): void {
        const nilai = Number((event.target as HTMLInputElement).value);
        tahun = nilai;
        sekolahs.forEach((sekolah, i) => {
            forms[i].nomor_awal = nomorUntuk(sekolah, nilai);
        });
    }

    function simpan(event: SubmitEvent, i: number): void {
        event.preventDefault();
        forms[i]
            .transform((data) => ({ ...data, tahun }))
            .submit(SekolahController.update(sekolahs[i].id), {
                preserveScroll: true,
            });
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

<div class="flex flex-col gap-6 p-4">
    <Heading
        title="Pengaturan sekolah"
        description="Data sekolah dan penandatangan yang tercetak pada kuitansi."
    />

    <div class="grid max-w-xs gap-2">
        <Label for="tahun">Tahun nomor awal</Label>
        <Input
            id="tahun"
            type="number"
            min="2000"
            max="2100"
            value={tahun}
            onchange={gantiTahun}
        />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {#each sekolahs as sekolah, i (sekolah.id)}
            {@const form = forms[i]}
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {sekolah.nama}
                        <span class="text-sm font-normal text-muted-foreground">
                            · {sekolah.sistem.toUpperCase()} · {sekolah.identitas}
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        class="grid gap-3"
                        onsubmit={(e) => simpan(e, i)}
                    >
                        {#each kolom as { kunci, label } (kunci)}
                            <div class="grid gap-1">
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
                        <div class="grid gap-1">
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
                        <div>
                            <Button type="submit" disabled={form.processing}>
                                Simpan
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        {/each}
    </div>
</div>
