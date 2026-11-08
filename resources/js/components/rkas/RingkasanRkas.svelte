<script module lang="ts">
    export type Ringkasan = {
        pagu: number;
        dianggarkan: number;
        sisa: number;
        per_bulan: Record<string, number>;
        tahap: {
            tahap: number;
            alokasi: number;
            dianggarkan: number;
            selisih: number;
        }[];
        jenis_belanja: { operasional: number; alat_mesin: number; aset: number };
        rincian_belanja: Record<string, number>;
        juknis: {
            nama: string;
            persen: number;
            batas: number;
            pesan: string | null;
        }[];
        peringatan: string[];
        jumlah_baris_bermasalah: number;
    };
</script>

<script lang="ts">
    import TriangleAlert from '@lucide/svelte/icons/triangle-alert';
    import { Card, CardContent } from '@/components/ui/card';

    let { ringkasan }: { ringkasan: Ringkasan } = $props();

    function rupiah(n: number): string {
        return `Rp ${n.toLocaleString('id-ID')}`;
    }

    const jenis = $derived([
        {
            nama: 'Operasional (5.1)',
            nilai: ringkasan.jenis_belanja.operasional,
        },
        {
            nama: 'Alat dan mesin (5.2.02)',
            nilai: ringkasan.jenis_belanja.alat_mesin,
        },
        { nama: 'Aset lainnya (5.2)', nilai: ringkasan.jenis_belanja.aset },
    ]);

    const totalJenis = $derived(jenis.reduce((n, j) => n + j.nilai, 0));

    function lebar(nilai: number): number {
        return totalJenis === 0 ? 0 : (nilai / totalJenis) * 100;
    }
</script>

<div class="grid gap-3">
    <div class="grid gap-3 md:grid-cols-3">
        <Card>
            <CardContent class="grid gap-1">
                <span class="text-sm text-muted-foreground">Pagu</span>
                <span class="text-lg font-semibold tabular-nums">
                    {rupiah(ringkasan.pagu)}
                </span>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="grid gap-1">
                <span class="text-sm text-muted-foreground">Dianggarkan</span>
                <span class="text-lg font-semibold tabular-nums">
                    {rupiah(ringkasan.dianggarkan)}
                </span>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="grid gap-1">
                <span class="text-sm text-muted-foreground">Sisa</span>
                <span
                    class="text-lg font-semibold tabular-nums {ringkasan.sisa < 0
                        ? 'text-red-600 dark:text-red-500'
                        : ''}"
                >
                    {rupiah(ringkasan.sisa)}
                </span>
            </CardContent>
        </Card>
    </div>

    {#if ringkasan.tahap.length > 0}
        <div class="grid gap-3 md:grid-cols-2">
            {#each ringkasan.tahap as t (t.tahap)}
                <Card>
                    <CardContent class="grid gap-1 text-sm">
                        <span class="font-medium">Tahap {t.tahap}</span>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Alokasi</span>
                            <span class="tabular-nums">{rupiah(t.alokasi)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Dianggarkan</span>
                            <span class="tabular-nums">{rupiah(t.dianggarkan)}</span>
                        </div>
                        <div
                            class="flex justify-between font-medium {t.selisih < 0
                                ? 'text-red-600 dark:text-red-500'
                                : ''}"
                        >
                            <span>{t.selisih < 0 ? 'Lebih' : 'Selisih'}</span>
                            <span class="tabular-nums">
                                {rupiah(Math.abs(t.selisih))}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            {/each}
        </div>
    {/if}

    <Card>
        <CardContent class="grid gap-3">
            <span class="text-sm font-medium">Jenis belanja</span>
            <div class="flex h-3 overflow-hidden rounded-full bg-muted">
                <div class="bg-primary" style="width: {lebar(jenis[0].nilai)}%"></div>
                <div class="bg-primary/60" style="width: {lebar(jenis[1].nilai)}%"></div>
                <div class="bg-primary/30" style="width: {lebar(jenis[2].nilai)}%"></div>
            </div>
            <ul class="grid gap-1 text-sm">
                {#each jenis as j (j.nama)}
                    <li class="flex justify-between">
                        <span class="text-muted-foreground">{j.nama}</span>
                        <span class="tabular-nums">{rupiah(j.nilai)}</span>
                    </li>
                {/each}
            </ul>
        </CardContent>
    </Card>

    {#if ringkasan.juknis.length > 0}
        <Card>
            <CardContent class="grid gap-2">
                <span class="text-sm font-medium">Juknis</span>
                <ul class="grid gap-1 text-sm">
                    {#each ringkasan.juknis as j (j.nama)}
                        <li
                            class="flex items-start justify-between gap-3 {j.pesan
                                ? 'text-red-600 dark:text-red-500'
                                : ''}"
                        >
                            <span>
                                {j.nama}
                                {#if j.pesan}
                                    <span class="block text-xs">{j.pesan}</span>
                                {/if}
                            </span>
                            <span class="tabular-nums">
                                {j.persen.toLocaleString('id-ID')}%
                            </span>
                        </li>
                    {/each}
                </ul>
            </CardContent>
        </Card>
    {/if}

    {#if ringkasan.jumlah_baris_bermasalah > 0}
        <p
            class="flex items-center gap-2 rounded-md border border-red-600/40 p-3 text-sm text-red-600 dark:text-red-500"
        >
            <TriangleAlert class="size-4 shrink-0" />
            Masih ada {ringkasan.jumlah_baris_bermasalah} baris dengan referensi bermasalah.
        </p>
    {/if}
</div>
