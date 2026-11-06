<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import ArrowDown from '@lucide/svelte/icons/arrow-down';
    import ArrowUp from '@lucide/svelte/icons/arrow-up';
    import Plus from '@lucide/svelte/icons/plus';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import JenisKegiatanController from '@/actions/App/Http/Controllers/JenisKegiatanController';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type BerkasForm = {
        id: number | null;
        nama: string;
        tata_letak: 'penuh' | 'kisi';
    };

    export type JenisData = {
        id: number;
        nama: string;
        kata_kunci: string;
        cadangan: boolean;
        berkas: { id: number; nama: string; tata_letak: 'penuh' | 'kisi' }[];
    };

    let {
        jenis = null,
    }: {
        jenis?: JenisData | null;
    } = $props();

    // Nilai awal formulir memang diambil sekali dari props.
    /* svelte-ignore state_referenced_locally */
    const form = useForm({
        nama: jenis?.nama ?? '',
        kata_kunci: jenis?.kata_kunci ?? '',
        berkas: (jenis?.berkas ?? []).map((b) => ({ ...b })) as BerkasForm[],
    });
    const hapus = useForm({});

    const idUnik = $props.id();

    function ubahBerkas(i: number, perubahan: Partial<BerkasForm>): void {
        form.berkas = form.berkas.map((b, j) =>
            j === i ? { ...b, ...perubahan } : b,
        );
    }

    function tambahBerkas(): void {
        form.berkas = [
            ...form.berkas,
            { id: null, nama: '', tata_letak: 'penuh' },
        ];
    }

    function hapusBerkas(i: number): void {
        form.berkas = form.berkas.filter((_, j) => j !== i);
    }

    function geser(i: number, arah: -1 | 1): void {
        const tujuan = i + arah;
        if (tujuan < 0 || tujuan >= form.berkas.length) {
            return;
        }
        const salinan = [...form.berkas];
        [salinan[i], salinan[tujuan]] = [salinan[tujuan], salinan[i]];
        form.berkas = salinan;
    }

    function simpan(event: SubmitEvent): void {
        event.preventDefault();
        if (jenis === null) {
            form.submit(JenisKegiatanController.store(), {
                preserveScroll: true,
                onSuccess: () => form.reset(),
            });
        } else {
            form.submit(JenisKegiatanController.update(jenis.id), {
                preserveScroll: true,
                onSuccess: () => {
                    // Ambil id berkas baru dari data segar agar simpan berikutnya tidak menghapus dan membuat ulang.
                    form.berkas = jenis.berkas.map((b) => ({ ...b }));
                },
            });
        }
    }

    function hapusJenis(): void {
        if (jenis !== null && confirm(`Hapus jenis kegiatan "${jenis.nama}"?`)) {
            hapus.submit(JenisKegiatanController.destroy(jenis.id), {
                preserveScroll: true,
            });
        }
    }

    const galatBerkas = $derived(
        Object.entries(form.errors).filter(
            ([kunci]) => kunci === 'berkas' || kunci.startsWith('berkas.'),
        ),
    );
</script>

<form class="grid gap-3" onsubmit={simpan}>
    <div class="grid gap-1">
        <Label for={`nama-${idUnik}`}>Nama jenis kegiatan</Label>
        <Input id={`nama-${idUnik}`} bind:value={form.nama} />
        <InputError message={form.errors.nama} />
    </div>
    <div class="grid gap-1">
        <Label for={`kata-kunci-${idUnik}`}>
            Kata kunci (pisahkan dengan koma)
        </Label>
        <Input id={`kata-kunci-${idUnik}`} bind:value={form.kata_kunci} />
        <InputError message={form.errors.kata_kunci} />
    </div>

    <div class="grid gap-2">
        <h3 class="text-sm font-medium">Daftar berkas, berurutan</h3>
        <div
            class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-[10px] bg-muted px-3 py-2.5 text-sm"
        >
            <span class="flex-1 font-medium">Kuitansi</span>
            <span class="text-xs text-muted-foreground">
                otomatis, selalu pertama
            </span>
        </div>
        {#each form.berkas as berkas, i (i)}
            <div class="flex flex-wrap items-center gap-2">
                <Input
                    class="min-w-40 flex-1"
                    aria-label={`Nama berkas ${i + 1}`}
                    value={berkas.nama}
                    oninput={(e) =>
                        ubahBerkas(i, {
                            nama: (e.target as HTMLInputElement).value,
                        })}
                />
                <select
                    class="h-11 rounded-[10px] border border-input bg-card px-2 text-sm"
                    aria-label={`Tata letak berkas ${i + 1}`}
                    value={berkas.tata_letak}
                    onchange={(e) =>
                        ubahBerkas(i, {
                            tata_letak: (e.target as HTMLSelectElement)
                                .value as 'penuh' | 'kisi',
                        })}
                >
                    <option value="penuh">Penuh</option>
                    <option value="kisi">Kisi 2 kolom</option>
                </select>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={`Naikkan berkas ${i + 1}`}
                    disabled={i === 0}
                    onclick={() => geser(i, -1)}
                >
                    <ArrowUp class="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={`Turunkan berkas ${i + 1}`}
                    disabled={i === form.berkas.length - 1}
                    onclick={() => geser(i, 1)}
                >
                    <ArrowDown class="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={`Hapus berkas ${i + 1}`}
                    onclick={() => hapusBerkas(i)}
                >
                    <Trash2 class="size-4" />
                </Button>
            </div>
        {/each}
        {#each galatBerkas as [kunci, pesan] (kunci)}
            <InputError message={pesan} />
        {/each}
        <div>
            <Button type="button" variant="outline" onclick={tambahBerkas}>
                <Plus class="size-4" />
                Tambah berkas
            </Button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 border-t pt-3">
        <Button type="submit" disabled={form.processing}>
            {jenis === null ? 'Tambah jenis' : 'Simpan'}
        </Button>
        {#if jenis !== null && !jenis.cadangan}
            <Button
                type="button"
                variant="destructive"
                disabled={hapus.processing}
                onclick={hapusJenis}
            >
                Hapus jenis
            </Button>
        {/if}
        {#if jenis?.cadangan}
            <span class="text-xs text-muted-foreground">
                Jenis cadangan tidak bisa dihapus.
            </span>
        {/if}
    </div>
    <InputError message={(hapus.errors as Record<string, string>).hapus} />
</form>
