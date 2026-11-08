<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import RevisiController from '@/actions/App/Http/Controllers/Rkas/RevisiController';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
        DialogTrigger,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    let {
        kertasKerjaId,
        jenis,
        paguAwal,
    }: {
        kertasKerjaId: number;
        jenis: 'pergeseran' | 'perubahan';
        paguAwal: number;
    } = $props();

    let terbuka = $state(false);

    // Komponen ini dibuat ulang lewat {#key} oleh pemakainya, jadi nilai awal memang diambil sekali.
    /* svelte-ignore state_referenced_locally */
    const form = useForm<{
        jenis: string;
        tanggal_revisi: string;
        pagu_baru: number | string;
        kertas_kerja?: string;
    }>({
        jenis,
        tanggal_revisi: new Date().toISOString().slice(0, 10),
        pagu_baru: jenis === 'perubahan' ? paguAwal : '',
    });

    const judul = $derived(jenis === 'perubahan' ? 'Buat Perubahan' : 'Buat Pergeseran');

    function kirim(): void {
        form.post(RevisiController.store.url(kertasKerjaId), {
            onSuccess: () => (terbuka = false),
        });
    }
</script>

<Dialog bind:open={terbuka}>
    <DialogTrigger asChild>
        {#snippet children(props)}
            <Button variant="secondary" onclick={props.onClick}>{judul}</Button>
        {/snippet}
    </DialogTrigger>
    <DialogContent>
        <DialogTitle>{judul}</DialogTitle>
        <DialogDescription>
            Versi baru berstatus draf dibuat sebagai salinan versi ini. Versi
            ini tetap tersimpan dan tidak berubah.
        </DialogDescription>
        <form
            class="grid gap-4"
            onsubmit={(e) => {
                e.preventDefault();
                kirim();
            }}
        >
            <div class="grid gap-2">
                <Label for={`tanggal-revisi-${jenis}`}>Tanggal revisi</Label>
                <Input
                    id={`tanggal-revisi-${jenis}`}
                    type="date"
                    bind:value={form.tanggal_revisi}
                />
                <InputError message={form.errors.tanggal_revisi} />
            </div>
            {#if jenis === 'perubahan'}
                <div class="grid gap-2">
                    <Label for="pagu-baru">Pagu baru (Rp)</Label>
                    <Input
                        id="pagu-baru"
                        type="number"
                        min="0"
                        inputmode="numeric"
                        bind:value={form.pagu_baru}
                    />
                    <InputError message={form.errors.pagu_baru} />
                </div>
            {/if}
            <InputError message={form.errors.kertas_kerja} />
            <DialogFooter>
                <DialogClose asChild>
                    {#snippet children(props)}
                        <Button
                            type="button"
                            variant="secondary"
                            onclick={props.onClick}
                        >
                            Batal
                        </Button>
                    {/snippet}
                </DialogClose>
                <Button type="submit" disabled={form.processing}>
                    {judul}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>
