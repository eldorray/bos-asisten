<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import Plus from '@lucide/svelte/icons/plus';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import KertasKerjaController from '@/actions/App/Http/Controllers/Rkas/KertasKerjaController';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    export type PemberiDana = { nama: string; jumlah: number | string };
    export type SumberDana = 'bosp_reguler' | 'lainnya';

    let {
        sekolahId,
        tahun,
        pilihanSumberDana,
        kertasKerjaUuid = null,
        sumberDana = 'bosp_reguler',
        pagu = '',
        pemberiDana = [],
        onSelesai,
    }: {
        sekolahId: number;
        tahun: number;
        pilihanSumberDana: { nilai: string; nama: string }[];
        kertasKerjaUuid?: string | null;
        sumberDana?: SumberDana;
        pagu?: number | string;
        pemberiDana?: PemberiDana[];
        onSelesai?: () => void;
    } = $props();

    // Komponen ini dibuat ulang lewat {#key} oleh pemakainya, jadi nilai awal memang diambil sekali.
    /* svelte-ignore state_referenced_locally */
    const form = useForm<{
        sekolah_id: number;
        tahun: number;
        sumber_dana: SumberDana;
        pagu: number | string;
        pemberi_dana: PemberiDana[];
    }>({
        sekolah_id: sekolahId,
        tahun,
        sumber_dana: sumberDana,
        pagu,
        pemberi_dana: pemberiDana.length > 0 ? pemberiDana.map((p) => ({ ...p })) : [{ nama: '', jumlah: '' }],
    });

    const ubah = $derived(kertasKerjaUuid !== null);
    const lainnya = $derived(form.sumber_dana === 'lainnya');
    const total = $derived(
        form.pemberi_dana.reduce((jumlah, p) => jumlah + (Number(p.jumlah) || 0), 0),
    );

    function tambahPemberi(): void {
        form.pemberi_dana = [...form.pemberi_dana, { nama: '', jumlah: '' }];
    }

    function hapusPemberi(indeks: number): void {
        form.pemberi_dana = form.pemberi_dana.filter((_, i) => i !== indeks);
    }

    function simpan(event: SubmitEvent): void {
        event.preventDefault();
        form
            .transform((data) => {
                const { pemberi_dana, pagu: nilaiPagu, ...dasar } = data;

                return lainnya
                    ? {
                          ...dasar,
                          pemberi_dana: pemberi_dana.map((p) => ({
                              nama: p.nama,
                              jumlah: Number(p.jumlah),
                          })),
                      }
                    : { ...dasar, pagu: Number(nilaiPagu) };
            })
            .submit(
                kertasKerjaUuid === null
                    ? KertasKerjaController.store()
                    : KertasKerjaController.update(kertasKerjaUuid),
                { preserveScroll: true, onSuccess: () => onSelesai?.() },
            );
    }

    const galatPemberi = $derived(
        Object.entries(form.errors)
            .filter(([kunci]) => kunci.startsWith('pemberi_dana'))
            .map(([, pesan]) => pesan),
    );
</script>

<form class="grid gap-4" onsubmit={simpan}>
    <InputError message={form.errors.sekolah_id} />
    <InputError message={form.errors.tahun} />

    {#if !ubah}
        <div class="grid gap-1.5">
            <Label for="sumber_dana">Sumber dana</Label>
            <select
                id="sumber_dana"
                class="h-9 rounded-md border bg-background px-2 text-sm"
                bind:value={form.sumber_dana}
            >
                {#each pilihanSumberDana as pilihan (pilihan.nilai)}
                    <option value={pilihan.nilai}>{pilihan.nama}</option>
                {/each}
            </select>
            <InputError message={form.errors.sumber_dana} />
        </div>
    {/if}

    {#if lainnya}
        <div class="grid gap-2">
            <Label>Pemberi dana</Label>
            {#each form.pemberi_dana as _, i (i)}
                <div class="flex items-center gap-2">
                    <Input
                        aria-label="Nama pemberi dana"
                        placeholder="Nama pemberi dana"
                        bind:value={form.pemberi_dana[i].nama}
                    />
                    <Input
                        aria-label="Jumlah"
                        class="w-40"
                        type="number"
                        min="1"
                        placeholder="Jumlah"
                        bind:value={form.pemberi_dana[i].jumlah}
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Hapus pemberi dana"
                        disabled={form.pemberi_dana.length === 1}
                        onclick={() => hapusPemberi(i)}
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            {/each}
            <InputError message={form.errors.pagu} />
            {#each galatPemberi as pesan (pesan)}
                <InputError message={pesan} />
            {/each}
            <div class="flex items-center justify-between text-sm">
                <Button type="button" variant="outline" size="sm" onclick={tambahPemberi}>
                    <Plus class="size-4" /> Tambah pemberi dana
                </Button>
                <span class="text-muted-foreground">
                    Pagu: <span class="font-medium text-foreground tabular-nums">Rp {total.toLocaleString('id-ID')}</span>
                </span>
            </div>
        </div>
    {:else}
        <div class="grid gap-1.5">
            <Label for="pagu">Pagu (Rp)</Label>
            <Input id="pagu" type="number" min="0" bind:value={form.pagu} />
            <InputError message={form.errors.pagu} />
        </div>
    {/if}

    <div class="flex justify-end">
        <Button type="submit" disabled={form.processing}>
            {ubah ? 'Simpan pagu' : 'Buat kertas kerja'}
        </Button>
    </div>
</form>
