<script lang="ts">
    import { rupiah } from '@/types';

    export type Rincian = {
        baru: { nama: string; jumlah: number }[];
        berubah: {
            nama: string;
            koefisienLama: string;
            koefisienBaru: string;
            jumlahLama: number;
            jumlahBaru: number;
            adaKuitansi: boolean;
        }[];
        hilang: { nama: string; jumlah: number; disimpan: boolean }[];
    };

    let { rincian }: { rincian: Rincian } = $props();
</script>

{#snippet judul(teks: string, jumlah: number)}
    <summary class="flex min-h-11 cursor-pointer items-center font-medium">
        {teks} ({jumlah})
    </summary>
{/snippet}

<div class="grid gap-1 border-t pt-1.5">
    {#if rincian.hilang.length > 0}
        <details open>
            {@render judul('Hilang dari anggaran', rincian.hilang.length)}
            <ul class="grid gap-2 pb-2">
                {#each rincian.hilang as b, i (i)}
                    <li>
                        <div class="flex justify-between gap-3">
                            <span class="min-w-0 [overflow-wrap:anywhere]">{b.nama}</span>
                            <span class="shrink-0 tabular-nums">
                                Rp {rupiah(b.jumlah)}
                            </span>
                        </div>
                        <p
                            class={`text-[13px] ${b.disimpan ? 'font-medium text-belum-foreground' : 'text-muted-foreground'}`}
                        >
                            {b.disimpan
                                ? 'Sudah ada isinya: tetap disimpan dan perlu diperiksa.'
                                : 'Belum ada berkas: akan dihapus.'}
                        </p>
                    </li>
                {/each}
            </ul>
        </details>
    {/if}

    {#if rincian.berubah.length > 0}
        <details open>
            {@render judul('Berubah', rincian.berubah.length)}
            <ul class="grid gap-2 pb-2">
                {#each rincian.berubah as b, i (i)}
                    <li>
                        <div class="[overflow-wrap:anywhere]">{b.nama}</div>
                        <p class="text-[13px] text-muted-foreground tabular-nums">
                            {#if b.koefisienLama !== b.koefisienBaru}
                                {b.koefisienLama} → {b.koefisienBaru} ·
                            {/if}
                            Rp {rupiah(b.jumlahLama)} → Rp {rupiah(b.jumlahBaru)}
                        </p>
                        {#if b.adaKuitansi && b.jumlahLama !== b.jumlahBaru}
                            <p class="text-[13px] font-medium text-kurang-foreground">
                                Kuitansi tidak ikut berubah dan perlu diperiksa.
                            </p>
                        {/if}
                    </li>
                {/each}
            </ul>
        </details>
    {/if}

    {#if rincian.baru.length > 0}
        <details>
            {@render judul('Baru', rincian.baru.length)}
            <ul class="grid gap-1.5 pb-2">
                {#each rincian.baru as b, i (i)}
                    <li class="flex justify-between gap-3">
                        <span class="min-w-0 [overflow-wrap:anywhere]">{b.nama}</span>
                        <span class="shrink-0 tabular-nums">
                            Rp {rupiah(b.jumlah)}
                        </span>
                    </li>
                {/each}
            </ul>
        </details>
    {/if}
</div>
