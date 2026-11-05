<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Link2 from '@lucide/svelte/icons/link-2';
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

<section
    aria-label="Saran satu acara"
    class="grid content-start gap-3 rounded-[14px] border bg-card p-4 text-sm"
>
    <div class="flex items-start gap-3">
        <span
            class="grid size-9 shrink-0 place-items-center rounded-[10px] bg-dirapel text-dirapel-foreground"
        >
            <Link2 class="size-[18px]" />
        </span>
        <div>
            <h2 class="text-[15px] font-semibold">Mungkin satu acara</h2>
            <p class="mt-0.5 text-muted-foreground">
                Undangan, daftar hadir, notulen, dan foto cukup diunggah sekali.
            </p>
        </div>
    </div>
    <ul class="grid gap-1.5">
        {#each saran.baris as b (b.id)}
            <li class="flex justify-between gap-3">
                <span class="min-w-0">{b.nama}</span>
                <span class="shrink-0 tabular-nums">
                    Rp {rupiah(b.jumlah)}
                </span>
            </li>
        {/each}
    </ul>
    <div class="flex flex-wrap items-center gap-2">
        <Input
            class="min-w-48 flex-1"
            aria-label="Nama acara"
            bind:value={nama}
        />
        <Button disabled={memproses || nama.trim() === ''} onclick={gabung}>
            Gabung
        </Button>
    </div>
    <InputError message={galat.kegiatan_ids ?? galat.nama} />
</section>
