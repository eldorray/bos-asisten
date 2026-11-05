<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AcaraController from '@/actions/App/Http/Controllers/AcaraController';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { rupiah } from '@/types';
    import type { SaranAcaraData } from '@/types';

    let { saran }: { saran: SaranAcaraData } = $props();

    // Nama awal hanya usulan dari baris pertama; pemilik bebas mengubahnya.
    /* svelte-ignore state_referenced_locally */
    let nama = $state(saran.nama);
    let galat = $state<Record<string, string>>({});
    let memproses = $state(false);

    function gabung(): void {
        router.post(
            AcaraController.store().url,
            { kegiatan_ids: saran.ids, nama },
            {
                preserveScroll: true,
                onStart: () => {
                    memproses = true;
                    galat = {};
                },
                onError: (errors) => (galat = errors),
                onFinish: () => (memproses = false),
            },
        );
    }
</script>

<div
    class="mt-3 grid gap-2 rounded-md border border-sky-300 bg-sky-50 p-3 text-sm dark:border-sky-800 dark:bg-sky-950/40"
>
    <p class="font-semibold">
        Mungkin satu acara
        <span class="font-normal">
            - undangan, daftar hadir, notulen, dan foto cukup diunggah sekali
        </span>
    </p>
    <ul class="grid gap-0.5">
        {#each saran.baris as b (b.id)}
            <li>{b.nama} · Rp {rupiah(b.jumlah)}</li>
        {/each}
    </ul>
    <div class="flex flex-wrap items-center gap-2">
        <Input
            class="max-w-xs"
            aria-label="Nama acara"
            bind:value={nama}
        />
        <Button size="sm" disabled={memproses || nama.trim() === ''} onclick={gabung}>
            Gabung
        </Button>
    </div>
    <InputError message={galat.kegiatan_ids ?? galat.nama} />
</div>
