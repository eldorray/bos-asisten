<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ChevronLeft from '@lucide/svelte/icons/chevron-left';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import { Button } from '@/components/ui/button';
    import { dashboard } from '@/routes';
    import { namaBulan } from '@/types';

    let {
        bulan,
        menunggakPerBulan,
    }: {
        bulan: string;
        menunggakPerBulan: Record<string, number>;
    } = $props();

    const tahun = $derived(Number(bulan.slice(0, 4)));
    const nomor = $derived(Number(bulan.slice(5, 7)));

    function pergi(t: number, n: number): void {
        const awal = new Date(t, n - 1, 1);
        const nilai = `${awal.getFullYear()}-${String(awal.getMonth() + 1).padStart(2, '0')}`;
        router.visit(dashboard({ query: { bulan: nilai } }));
    }
</script>

<div class="flex flex-wrap items-center gap-2">
    <Button
        variant="outline"
        size="icon"
        aria-label="Bulan sebelumnya"
        onclick={() => pergi(tahun, nomor - 1)}
    >
        <ChevronLeft class="size-4" />
    </Button>
    <select
        class="h-9 rounded-md border border-input bg-background px-2 text-base font-bold"
        aria-label="Pilih bulan"
        value={nomor}
        onchange={(e) => pergi(tahun, Number(e.currentTarget.value))}
    >
        {#each namaBulan as nama, i (nama)}
            {@const tunggak = menunggakPerBulan[i + 1] ?? 0}
            <option value={i + 1}>
                {nama}{tunggak > 0 ? ` (${tunggak} belum selesai)` : ''}
            </option>
        {/each}
    </select>
    <input
        type="number"
        class="h-9 w-24 rounded-md border border-input bg-background px-2 text-base font-bold"
        aria-label="Tahun"
        min="2000"
        max="2100"
        value={tahun}
        onchange={(e) => pergi(Number(e.currentTarget.value), nomor)}
    />
    <Button
        variant="outline"
        size="icon"
        aria-label="Bulan berikutnya"
        onclick={() => pergi(tahun, nomor + 1)}
    >
        <ChevronRight class="size-4" />
    </Button>
</div>
