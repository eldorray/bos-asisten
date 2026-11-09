<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import SekolahController from '@/actions/App/Http/Controllers/SekolahController';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    let { sekolah }: { sekolah: { uuid: string; nama: string } } = $props();

    let terbuka = $state(false);
    const form = useForm({ konfirmasi: '' });

    function hapus(event: SubmitEvent): void {
        event.preventDefault();
        form.submit(SekolahController.destroy(sekolah.uuid), {
            preserveScroll: true,
            onSuccess: () => (terbuka = false),
        });
    }
</script>

<Button type="button" variant="destructive" onclick={() => (terbuka = true)}>
    Hapus sekolah
</Button>

<Dialog
    bind:open={terbuka}
    onOpenChange={(buka) => {
        if (!buka) {
            form.reset();
            form.clearErrors();
        }
    }}
>
    <DialogContent>
        <form class="grid gap-4" onsubmit={hapus}>
            <DialogTitle>Hapus {sekolah.nama}?</DialogTitle>
            <DialogDescription>
                Semua data sekolah ini ikut terhapus permanen: anggaran, kegiatan,
                kuitansi, foto, dan draf RKAS. Tidak bisa dikembalikan.
            </DialogDescription>
            <div class="grid gap-1.5">
                <Label for={`hapus-${sekolah.uuid}`}>
                    Ketik <strong>{sekolah.nama}</strong> untuk melanjutkan
                </Label>
                <Input
                    id={`hapus-${sekolah.uuid}`}
                    autocomplete="off"
                    bind:value={form.konfirmasi}
                />
                <InputError message={form.errors.konfirmasi} />
            </div>
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
                <Button
                    type="submit"
                    variant="destructive"
                    disabled={form.processing ||
                        form.konfirmasi !== sekolah.nama}
                >
                    Hapus permanen
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>
