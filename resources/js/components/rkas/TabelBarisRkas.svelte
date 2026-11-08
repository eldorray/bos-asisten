<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import BarisRkasController from '@/actions/App/Http/Controllers/Rkas/BarisRkasController';
    import { BULAN } from '@/components/rkas/FormBarisRkas.svelte';
    import type { BarisRkas } from '@/components/rkas/FormBarisRkas.svelte';
    import { Button } from '@/components/ui/button';

    let {
        baris,
        bisaDiubah,
        onUbah,
    }: {
        baris: BarisRkas[];
        bisaDiubah: boolean;
        onUbah: (baris: BarisRkas) => void;
    } = $props();

    let tampilan = $state<'bulan' | 'tahap'>('bulan');

    type Kelompok = {
        kunci: string;
        program: string;
        komponen: string;
        kegiatan: string;
        baris: BarisRkas[];
    };

    const kelompok = $derived.by(() => {
        const peta = new Map<string, Kelompok>();
        for (const b of baris) {
            const kunci = b.kegiatan.kode;
            let k = peta.get(kunci);
            if (!k) {
                k = {
                    kunci,
                    program: b.kegiatan.program
                        ? `${b.kegiatan.program.kode} ${b.kegiatan.program.uraian}`
                        : '',
                    komponen: b.kegiatan.induk
                        ? `${b.kegiatan.induk.kode} ${b.kegiatan.induk.uraian}`
                        : '',
                    kegiatan: `${b.kegiatan.kode} ${b.kegiatan.uraian}`,
                    baris: [],
                };
                peta.set(kunci, k);
            }
            k.baris.push(b);
        }

        return [...peta.values()].sort((a, b) => a.kunci.localeCompare(b.kunci));
    });

    const kolom = $derived(
        tampilan === 'bulan'
            ? BULAN.map((nama, i) => ({ nama, bulan: [i + 1] }))
            : [
                  { nama: 'Tahap 1', bulan: [1, 2, 3, 4, 5, 6] },
                  { nama: 'Tahap 2', bulan: [7, 8, 9, 10, 11, 12] },
              ],
    );

    function volumeKolom(b: BarisRkas, bulan: number[]): number {
        return bulan.reduce((n, m) => n + (b.volume_bulan[String(m)] ?? 0), 0);
    }

    function kode(b: BarisRkas): string {
        return `${b.kegiatan.kode}${String(b.urutan).padStart(3, '0')}`;
    }

    function rupiah(n: number): string {
        return n.toLocaleString('id-ID');
    }

    function hapus(b: BarisRkas): void {
        if (!confirm(`Hapus baris "${b.uraian}"?`)) {
            return;
        }
        router.delete(BarisRkasController.destroy(b.id), {
            preserveScroll: true,
        });
    }

    const total = $derived(baris.reduce((n, b) => n + b.jumlah, 0));
</script>

<div class="grid gap-3">
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-base font-semibold">Baris belanja</h2>
        <div class="flex gap-1" role="group" aria-label="Tampilan volume">
            <Button
                type="button"
                size="sm"
                variant={tampilan === 'bulan' ? 'default' : 'outline'}
                onclick={() => (tampilan = 'bulan')}
            >
                12 bulan
            </Button>
            <Button
                type="button"
                size="sm"
                variant={tampilan === 'tahap' ? 'default' : 'outline'}
                onclick={() => (tampilan = 'tahap')}
            >
                Per tahap
            </Button>
        </div>
    </div>

    {#if baris.length === 0}
        <p class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
            Belum ada baris belanja.
        </p>
    {:else}
        <div class="overflow-x-auto rounded-md border">
            <table class="w-full min-w-max text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-3 py-2">Kode</th>
                        <th class="px-3 py-2">Rekening</th>
                        <th class="px-3 py-2">Uraian</th>
                        <th class="px-3 py-2">Satuan</th>
                        <th class="px-3 py-2 text-right">Harga</th>
                        {#each kolom as k (k.nama)}
                            <th class="px-2 py-2 text-right">{k.nama}</th>
                        {/each}
                        <th class="px-3 py-2 text-right">Volume</th>
                        <th class="px-3 py-2 text-right">Jumlah</th>
                        {#if bisaDiubah}
                            <th class="px-3 py-2"><span class="sr-only">Aksi</span></th>
                        {/if}
                    </tr>
                </thead>
                {#each kelompok as k (k.kunci)}
                    <tbody>
                        <tr class="bg-muted/30">
                            <td
                                class="px-3 py-1.5 text-xs"
                                colspan={8 + kolom.length - 1 + (bisaDiubah ? 1 : 0)}
                            >
                                {#if k.program}<span class="text-muted-foreground">{k.program} ›</span>{/if}
                                {#if k.komponen}<span class="text-muted-foreground">{k.komponen} ›</span>{/if}
                                <span class="font-medium">{k.kegiatan}</span>
                            </td>
                        </tr>
                        {#each k.baris as b (b.id)}
                            <tr class="border-t align-top">
                                <td class="px-3 py-2 whitespace-nowrap tabular-nums">{kode(b)}</td>
                                <td class="px-3 py-2">{b.rekening.kode}</td>
                                <td class="px-3 py-2">
                                    {b.uraian}
                                    {#each b.masalah as pesan (pesan)}
                                        <p class="text-xs text-red-600 dark:text-red-500">{pesan}</p>
                                    {/each}
                                </td>
                                <td class="px-3 py-2">{b.satuan}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{rupiah(b.harga)}</td>
                                {#each kolom as c (c.nama)}
                                    <td class="px-2 py-2 text-right tabular-nums">
                                        {volumeKolom(b, c.bulan) || ''}
                                    </td>
                                {/each}
                                <td class="px-3 py-2 text-right tabular-nums">{rupiah(b.volume)}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{rupiah(b.jumlah)}</td>
                                {#if bisaDiubah}
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Ubah baris"
                                            onclick={() => onUbah(b)}
                                        >
                                            <Pencil class="size-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Hapus baris"
                                            onclick={() => hapus(b)}
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </td>
                                {/if}
                            </tr>
                        {/each}
                    </tbody>
                {/each}
                <tfoot>
                    <tr class="border-t font-medium">
                        <td class="px-3 py-2" colspan={5 + kolom.length + 1}>Total</td>
                        <td class="px-3 py-2 text-right tabular-nums">{rupiah(total)}</td>
                        {#if bisaDiubah}<td></td>{/if}
                    </tr>
                </tfoot>
            </table>
        </div>
    {/if}
</div>
