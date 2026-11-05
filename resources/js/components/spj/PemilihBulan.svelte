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
        pdfBelumPerBulan,
    }: {
        bulan: string;
        menunggakPerBulan: Record<string, number>;
        pdfBelumPerBulan: Record<string, boolean>;
    } = $props();

    function label(nama: string, nomorBulan: number): string {
        const catatan = [
            pdfBelumPerBulan[nomorBulan] ? 'PDF belum diunggah' : null,
            menunggakPerBulan[nomorBulan]
                ? `${menunggakPerBulan[nomorBulan]} belum selesai`
                : null,
        ].filter(Boolean);

        return catatan.length > 0 ? `${nama} (${catatan.join(', ')})` : nama;
    }

    const tahun = $derived(Number(bulan.slice(0, 4)));
    const nomor = $derived(Number(bulan.slice(5, 7)));

    function pergi(t: number, n: number): void {
        const awal = new Date(t, n - 1, 1);
        const nilai = `${awal.getFullYear()}-${String(awal.getMonth() + 1).padStart(2, '0')}`;
        router.visit(dashboard({ query: { bulan: nilai } }));
    }
</script>

<div class="flex max-w-full flex-wrap items-center gap-2">
    <Button
        variant="outline"
        size="icon"
        aria-label="Bulan sebelumnya"
        onclick={() => pergi(tahun, nomor - 1)}
    >
        <ChevronLeft class="size-4" />
    </Button>
    <select
        class="h-11 max-w-full rounded-[10px] border border-input bg-card px-2.5 text-sm font-semibold"
        aria-label="Pilih bulan"
        value={nomor}
        onchange={(e) => pergi(tahun, Number(e.currentTarget.value))}
    >
        {#each namaBulan as nama, i (nama)}
            <option value={i + 1}>{label(nama, i + 1)}</option>
        {/each}
    </select>
    <input
        type="number"
        class="h-11 w-24 rounded-[10px] border border-input bg-card px-2.5 text-sm font-semibold"
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
