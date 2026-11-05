<script module lang="ts">
    import ImporController from '@/actions/App/Http/Controllers/ImporController';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Impor Anggaran',
                href: ImporController.index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import Upload from '@lucide/svelte/icons/upload';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Alert, AlertDescription } from '@/components/ui/alert';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type Ringkasan = {
        sekolah: string;
        bulan: number | null;
        tahun: number;
        jumlah_kegiatan: number;
        total: number;
        baru: number;
        berubah: number;
        hilang: number;
    };

    type Item = {
        token?: string;
        nama_file: string;
        ringkasan?: Ringkasan;
        ditolak?: string;
    };

    let {
        pratinjau = [],
        hasil = [],
    }: {
        pratinjau?: Item[];
        hasil?: Item[];
    } = $props();

    const namaBulan = [
        'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember',
    ];

    const unggah = useForm(ImporController.pratinjau(), {
        files: [] as File[],
    });
    const simpan = useForm(ImporController.simpan(), {
        tokens: [] as string[],
    });

    const bisaDisimpan = $derived(
        pratinjau.filter((item) => item.token !== undefined),
    );

    const rupiah = (angka: number): string =>
        'Rp ' + angka.toLocaleString('id-ID');

    const periode = (r: Ringkasan): string =>
        r.bulan === null
            ? `RKAM tahun ${r.tahun}`
            : `${namaBulan[r.bulan - 1]} ${r.tahun}`;

    function pilihFile(event: Event): void {
        unggah.files = Array.from((event.target as HTMLInputElement).files ?? []);
    }

    function kirimUnggah(event: SubmitEvent): void {
        event.preventDefault();
        unggah.submit({ forceFormData: true });
    }

    function kirimSimpan(): void {
        simpan.tokens = bisaDisimpan.map((item) => item.token as string);
        simpan.submit();
    }
</script>

<AppHead title="Impor Anggaran" />

<div class="flex flex-col gap-5 p-5 md:p-7">
    <Heading
        title="Impor Anggaran"
        description="Unggah PDF kertas kerja ARKAS atau RKAM ERKAM. Periksa pratinjau dulu sebelum menyimpan."
    />

    <form
        class="grid gap-3.5 rounded-[14px] border bg-card p-4"
        onsubmit={kirimUnggah}
    >
        <Label
            for="files"
            class="grid min-w-0 cursor-pointer justify-items-center gap-2.5 rounded-xl border border-dashed border-input bg-background px-5 py-8 text-center"
        >
            <span
                class="grid size-11 place-items-center rounded-xl bg-primary text-primary-foreground"
            >
                <Upload class="size-5" />
            </span>
            <span class="text-[15px] font-semibold">Pilih file PDF</span>
            <span class="font-normal text-muted-foreground">
                Maksimal 10 MB per file. Bisa beberapa file sekaligus.
            </span>
            <Input
                id="files"
                class="h-auto w-auto max-w-full cursor-pointer border-0 bg-transparent py-0 text-[13px] text-foreground"
                type="file"
                accept="application/pdf"
                multiple
                onchange={pilihFile}
            />
        </Label>
        <InputError message={unggah.errors.files} />
        {#each Object.entries(unggah.errors).filter(([kunci]) => kunci.startsWith('files.')) as [kunci, pesan] (kunci)}
            <InputError message={pesan} />
        {/each}
        <div>
            <Button
                type="submit"
                disabled={unggah.processing || unggah.files.length === 0}
            >
                {unggah.processing ? 'Membaca...' : 'Lihat pratinjau'}
            </Button>
        </div>
    </form>

    {#if hasil.length > 0}
        <section class="grid gap-3" aria-label="Hasil simpan">
            <h2 class="text-lg font-semibold tracking-tight">Hasil simpan</h2>
            {#each hasil as item, i (i)}
                <Alert
                    variant={item.ditolak ? 'destructive' : 'default'}
                    class={item.ditolak
                        ? ''
                        : 'border-transparent bg-selesai text-selesai-foreground'}
                >
                    <AlertDescription>
                        <strong>{item.nama_file}</strong>:
                        {#if item.ringkasan}
                            tersimpan untuk {item.ringkasan.sekolah}, {periode(
                                item.ringkasan,
                            )}. Baru {item.ringkasan.baru}, berubah {item
                                .ringkasan.berubah}, hilang {item.ringkasan
                                .hilang}.
                        {:else}
                            {item.ditolak}
                        {/if}
                    </AlertDescription>
                </Alert>
            {/each}
        </section>
    {/if}

    {#if pratinjau.length > 0}
        <section class="grid gap-3" aria-label="Pratinjau">
            <h2 class="text-lg font-semibold tracking-tight">Pratinjau</h2>
            <div class="grid items-start gap-3.5 md:grid-cols-2 xl:grid-cols-3">
                {#each pratinjau as item, i (i)}
                    <Card>
                        <CardHeader>
                            <CardTitle class="break-all text-base">
                                {item.nama_file}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="grid gap-2 text-sm">
                            {#if item.ringkasan}
                                <p>
                                    <strong>{item.ringkasan.sekolah}</strong>,
                                    {periode(item.ringkasan)}
                                </p>
                                <p class="text-muted-foreground tabular-nums">
                                    {item.ringkasan.jumlah_kegiatan} kegiatan,
                                    total {rupiah(item.ringkasan.total)}
                                </p>
                                <div class="flex flex-wrap gap-1.5 border-t pt-2.5">
                                    <Badge
                                        class="rounded-md border-transparent bg-selesai text-selesai-foreground"
                                    >
                                        Baru {item.ringkasan.baru}
                                    </Badge>
                                    <Badge
                                        class="rounded-md border-transparent bg-kurang text-kurang-foreground"
                                    >
                                        Berubah {item.ringkasan.berubah}
                                    </Badge>
                                    <Badge
                                        class={`rounded-md border-transparent ${item.ringkasan.hilang > 0 ? 'bg-belum text-belum-foreground' : 'bg-batal text-batal-foreground'}`}
                                    >
                                        Hilang {item.ringkasan.hilang}
                                    </Badge>
                                </div>
                            {:else}
                                <Alert variant="destructive">
                                    <AlertDescription>
                                        {item.ditolak}
                                    </AlertDescription>
                                </Alert>
                            {/if}
                        </CardContent>
                    </Card>
                {/each}
            </div>

            {#if bisaDisimpan.length > 0}
                <div>
                    <InputError message={simpan.errors.tokens} />
                    <Button
                        onclick={kirimSimpan}
                        disabled={simpan.processing}
                    >
                        {simpan.processing
                            ? 'Menyimpan...'
                            : `Simpan ${bisaDisimpan.length} file`}
                    </Button>
                </div>
            {/if}
        </section>
    {/if}
</div>
