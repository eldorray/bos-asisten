<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import KegiatanController from '@/actions/App/Http/Controllers/KegiatanController';
    import { namaBulan, rupiah } from '@/types';
    import type { BarisKegiatanData } from '@/types';

    let {
        sekolah,
        belumBerbulan,
    }: {
        sekolah: string;
        belumBerbulan: { tahap: number; kegiatan: BarisKegiatanData[] }[];
    } = $props();

    function pilihBulan(id: number, bulan: string): void {
        if (bulan === '') {
            return;
        }

        router.patch(
            KegiatanController.update(id).url,
            { bulan: Number(bulan) },
            { preserveScroll: true },
        );
    }
</script>

{#each belumBerbulan as kelompok (kelompok.tahap)}
    {@const awal = (kelompok.tahap - 1) * 6}
    <div
        class="mt-3 rounded-md border border-amber-300 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950/40"
    >
        <p class="font-semibold">
            {kelompok.kegiatan.length} kegiatan Tahap {kelompok.tahap}
            {sekolah} belum punya bulan
            <span class="font-normal">
                - pilih bulannya agar tidak terlewat
            </span>
        </p>
        {#each kelompok.kegiatan as k (k.id)}
            <div
                class="flex flex-wrap items-center justify-between gap-2 py-1.5 text-sm"
            >
                <span>
                    {k.nama} · Rp {rupiah(k.jumlah)}
                    {#if k.perluDitinjau}
                        <span class="text-xs font-semibold text-amber-700">
                            Jenis: {k.jenis}
                        </span>
                    {/if}
                </span>
                <select
                    class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                    aria-label={`Bulan untuk ${k.nama}`}
                    onchange={(e) => pilihBulan(k.id, e.currentTarget.value)}
                >
                    <option value="">Pilih bulan</option>
                    {#each namaBulan.slice(awal, awal + 6) as nama, i (nama)}
                        <option value={awal + i + 1}>{nama}</option>
                    {/each}
                </select>
            </div>
        {/each}
    </div>
{/each}
