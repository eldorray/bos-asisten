<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Calendar from '@lucide/svelte/icons/calendar';
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

    let galat = $state<Record<number, string>>({});

    function pilihBulan(id: number, pilihan: HTMLSelectElement): void {
        if (pilihan.value === '') {
            return;
        }

        delete galat[id];

        router.patch(
            KegiatanController.update(id).url,
            { bulan: Number(pilihan.value) },
            {
                preserveScroll: true,
                onError: (errors) => {
                    galat[id] = errors.bulan ?? 'Bulan tidak bisa disimpan.';
                    pilihan.value = '';
                },
            },
        );
    }
</script>

{#each belumBerbulan as kelompok (kelompok.tahap)}
    {@const awal = (kelompok.tahap - 1) * 6}
    <section
        aria-label={`Tahap ${kelompok.tahap} belum punya bulan`}
        class="grid content-start gap-3 rounded-[14px] border bg-card p-4 text-sm"
    >
        <div class="flex items-start gap-3">
            <span
                class="grid size-9 shrink-0 place-items-center rounded-[10px] bg-kurang text-kurang-foreground"
            >
                <Calendar class="size-[18px]" />
            </span>
            <div>
                <h2 class="text-[15px] font-semibold">
                    {kelompok.kegiatan.length} kegiatan Tahap {kelompok.tahap}
                    {sekolah} belum punya bulan
                </h2>
                <p class="mt-0.5 text-muted-foreground">
                    Pilih bulannya agar tidak terlewat.
                </p>
            </div>
        </div>
        {#each kelompok.kegiatan as k (k.id)}
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="min-w-0">
                    <Link
                        href={KegiatanController.show(k.id)}
                        class="underline underline-offset-2"
                    >
                        {k.nama}
                    </Link>
                    ·
                    <span class="tabular-nums">Rp {rupiah(k.jumlah)}</span>
                    {#if k.perluDitinjau}
                        <span
                            class="text-xs font-semibold text-kurang-foreground"
                        >
                            Jenis: {k.jenis}
                        </span>
                    {/if}
                </span>
                <select
                    class="h-11 rounded-[10px] border border-input bg-card px-2.5 text-sm"
                    aria-label={`Bulan untuk ${k.nama}`}
                    aria-invalid={galat[k.id] ? true : undefined}
                    onchange={(e) => pilihBulan(k.id, e.currentTarget)}
                >
                    <option value="">Pilih bulan</option>
                    {#each namaBulan.slice(awal, awal + 6) as nama, i (nama)}
                        <option value={awal + i + 1}>{nama}</option>
                    {/each}
                </select>
                {#if galat[k.id]}
                    <p role="alert" class="w-full text-sm text-belum-foreground">
                        {galat[k.id]}
                    </p>
                {/if}
            </div>
        {/each}
    </section>
{/each}
