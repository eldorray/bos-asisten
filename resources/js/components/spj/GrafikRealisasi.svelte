<script lang="ts">
    import { namaBulan, rupiah } from '@/types';

    let {
        tahun,
        sekolah,
        bulanDipilih,
        anggaran,
        realisasi,
    }: {
        tahun: number;
        sekolah: string;
        bulanDipilih: number;
        anggaran: Record<string, number>;
        realisasi: Record<string, number>;
    } = $props();

    const persen = (nilai: number, dasar: number): number | null =>
        dasar > 0 ? Math.round((nilai / dasar) * 100) : null;

    const kolom = $derived(
        namaBulan.map((nama, i) => {
            const n = i + 1;
            const a = anggaran[n] ?? 0;
            const r = realisasi[n] ?? 0;

            return { n, nama, a, r, persen: persen(r, a) };
        }),
    );

    const totalAnggaran = $derived(kolom.reduce((t, k) => t + k.a, 0));
    const totalRealisasi = $derived(kolom.reduce((t, k) => t + k.r, 0));
    const persenTahun = $derived(persen(totalRealisasi, totalAnggaran));
</script>

<section
    aria-label={`Realisasi anggaran ${sekolah} tahun ${tahun}`}
    class="grid gap-4 rounded-2xl border bg-card p-4 md:p-6"
>
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
        <div class="min-w-0">
            <h2 class="text-sm text-muted-foreground">
                Realisasi anggaran {tahun}
                <span class="max-md:hidden">· {sekolah}</span>
            </h2>
            <p class="mt-1 text-[28px] leading-none font-medium tabular-nums md:text-4xl">
                {persenTahun === null ? '–' : `${persenTahun}%`}
            </p>
        </div>
        <p class="text-sm text-muted-foreground tabular-nums">
            Rp {rupiah(totalRealisasi)} dari Rp {rupiah(totalAnggaran)}
        </p>
    </div>

    <ol class="grid grid-cols-12 gap-1 md:gap-2">
        {#each kolom as k (k.n)}
            <li
                class="grid min-w-0 gap-1"
                aria-label={k.persen === null
                    ? `${k.nama}: tidak ada anggaran`
                    : `${k.nama}: ${k.persen}%, Rp ${rupiah(k.r)} dari Rp ${rupiah(k.a)}`}
                title={k.persen === null
                    ? `${k.nama}: tidak ada anggaran`
                    : `${k.nama}: Rp ${rupiah(k.r)} dari Rp ${rupiah(k.a)}`}
            >
                <span
                    class="text-center text-[10px] leading-none tabular-nums md:text-xs {k.persen === null
                        ? 'text-muted-foreground'
                        : 'font-medium'}"
                >
                    {k.persen === null ? '–' : `${k.persen}%`}
                </span>
                <span
                    class="relative flex h-28 items-end overflow-hidden rounded-sm bg-accent md:h-36"
                >
                    <span
                        class="w-full rounded-sm bg-primary transition-[height] duration-300 ease-tenang"
                        style:height={`${Math.min(k.persen ?? 0, 100)}%`}
                    ></span>
                </span>
                <span
                    class={[
                        'text-center text-[10px] leading-none md:text-xs',
                        k.n === bulanDipilih
                            ? 'font-semibold text-foreground'
                            : 'text-muted-foreground',
                    ]}
                >
                    {k.nama.slice(0, 3)}
                </span>
            </li>
        {/each}
    </ol>

    <p class="text-meta text-muted-foreground">
        Realisasi dihitung dari jumlah kuitansi, dibagi per bulan menurut porsi anggaran kegiatannya. Kegiatan yang ditandai selesai di luar aplikasi dihitung penuh.
        Batang penuh berarti 100% atau lebih.
    </p>
</section>
