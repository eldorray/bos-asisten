<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import Plus from '@lucide/svelte/icons/plus';
    import ReferensiRkasController from '@/actions/App/Http/Controllers/Rkas/ReferensiRkasController';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    export type JenisReferensi =
        | 'kegiatan'
        | 'rekening'
        | 'barang'
        | 'satuan'
        | 'ptk';

    let {
        jenis,
        tahun,
        sekolahId,
        pilihanJenisPtk,
    }: {
        jenis: JenisReferensi;
        tahun: number;
        sekolahId: number;
        pilihanJenisPtk: { nilai: number; nama: string }[];
    } = $props();

    // Komponen ini dibuat ulang (lewat {#key}) setiap jenis atau tahun berganti,
    // jadi nilai awalnya memang diambil sekali.
    /* svelte-ignore state_referenced_locally */
    const form = useForm({
        tahun,
        sekolah_id: sekolahId,
        kode: '',
        uraian: '',
        nama: '',
        kode_rekening: '',
        satuan: '',
        harga: '',
        batas_bawah: '',
        batas_atas: '',
        nuptk: '',
        jenis: String(pilihanJenisPtk[0]?.nilai ?? 1),
    });

    function simpan(event: SubmitEvent): void {
        event.preventDefault();
        form.transform((data) => ({ ...data, jenis: Number(data.jenis) })).submit(
            ReferensiRkasController.store({ jenis }),
            {
                preserveScroll: true,
                onSuccess: () => form.reset(),
            },
        );
    }

    const medan = $derived.by(() => {
        switch (jenis) {
            case 'kegiatan':
                return [
                    { kunci: 'kode', label: 'Kode', petunjuk: 'Contoh 07.12.05.' },
                    { kunci: 'uraian', label: 'Uraian' },
                ] as const;
            case 'rekening':
                return [
                    { kunci: 'kode', label: 'Kode', petunjuk: 'Contoh 5.1.02.02.01.0013' },
                    { kunci: 'nama', label: 'Nama rekening' },
                ] as const;
            case 'barang':
                return [
                    { kunci: 'nama', label: 'Nama barang/jasa' },
                    { kunci: 'kode_rekening', label: 'Kode rekening (opsional)' },
                    { kunci: 'satuan', label: 'Satuan' },
                    { kunci: 'harga', label: 'Harga', angka: true },
                    { kunci: 'batas_bawah', label: 'Batas bawah', angka: true },
                    { kunci: 'batas_atas', label: 'Batas atas (0 = tanpa batas)', angka: true },
                ] as const;
            case 'satuan':
                return [{ kunci: 'nama', label: 'Nama satuan' }] as const;
            default:
                return [
                    { kunci: 'nama', label: 'Nama PTK' },
                    { kunci: 'nuptk', label: 'NUPTK (opsional)', petunjuk: '16 digit' },
                ] as const;
        }
    });
</script>

<form class="flex flex-wrap items-start gap-3" onsubmit={simpan}>
    {#each medan as m (m.kunci)}
        <div class="grid min-w-40 flex-1 content-start gap-1">
            <Label for={`ref-${m.kunci}`}>{m.label}</Label>
            <Input
                id={`ref-${m.kunci}`}
                type={'angka' in m ? 'number' : 'text'}
                min={'angka' in m ? '0' : undefined}
                value={form[m.kunci]}
                oninput={(e) =>
                    (form[m.kunci] = (e.target as HTMLInputElement).value)}
            />
            {#if 'petunjuk' in m}
                <p class="text-xs text-muted-foreground">{m.petunjuk}</p>
            {/if}
            <InputError message={form.errors[m.kunci]} />
            {#if jenis === 'kegiatan' && m.kunci === 'kode'}
                <InputError
                    message={(form.errors as Record<string, string>).kode_induk}
                />
            {/if}
        </div>
    {/each}

    {#if jenis === 'ptk'}
        <div class="grid min-w-40 content-start gap-1">
            <Label for="ref-jenis">Jenis PTK</Label>
            <select
                id="ref-jenis"
                class="h-9 rounded-md border bg-background px-2 text-sm"
                value={form.jenis}
                onchange={(e) =>
                    (form.jenis = (e.target as HTMLSelectElement).value)}
            >
                {#each pilihanJenisPtk as pilihan (pilihan.nilai)}
                    <option value={String(pilihan.nilai)}>{pilihan.nama}</option>
                {/each}
            </select>
            <InputError message={form.errors.jenis} />
        </div>
    {/if}

    <div class="pt-6">
        <Button type="submit" disabled={form.processing}>
            <Plus class="size-4" />
            Tambah
        </Button>
    </div>
</form>
