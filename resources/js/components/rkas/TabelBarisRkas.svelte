<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import CalendarDays from '@lucide/svelte/icons/calendar-days';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Plus from '@lucide/svelte/icons/plus';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import { SvelteSet } from 'svelte/reactivity';
    import BarisRkasController from '@/actions/App/Http/Controllers/Rkas/BarisRkasController';
    import type { BarisRkas } from '@/components/rkas/FormBarisRkas.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { BULAN, kelompokkanPerKegiatan, ringkasBulan } from '@/lib/rkas';

    let {
        baris,
        bisaDiubah,
        onUbah,
        onTambah,
    }: {
        baris: BarisRkas[];
        bisaDiubah: boolean;
        onUbah: (baris: BarisRkas) => void;
        /** Kegiatan yang sudah terpilih di form, atau null untuk memilih sendiri. */
        onTambah: (refKegiatanId: number | null) => void;
    } = $props();

    let cari = $state('');
    let terbuka = $state<number | null>(null);
    // Kode kegiatan yang dilipat. Bawaan terbuka; saat mencari semua hasil tampil.
    const dilipat = new SvelteSet<string>();

    const kata = $derived(cari.trim().toLowerCase());

    // Subtotal tetap dari semua baris kegiatan; pencarian hanya menyaring yang tampil.
    const kelompok = $derived(
        kelompokkanPerKegiatan(baris)
            .map((k) => ({
                ...k,
                baris: k.baris.filter((b) =>
                    `${b.uraian} ${b.rekening.kode} ${b.rekening.nama} ${k.kode} ${k.nama}`
                        .toLowerCase()
                        .includes(kata),
                ),
            }))
            .filter((k) => k.baris.length > 0),
    );

    const semuaDilipat = $derived(
        kelompok.length > 0 && kelompok.every((k) => dilipat.has(k.kode)),
    );

    function lipatSemua(): void {
        if (semuaDilipat) {
            dilipat.clear();

            return;
        }
        for (const k of kelompok) {
            dilipat.add(k.kode);
        }
    }

    function lipat(kodeKegiatan: string): void {
        if (!dilipat.delete(kodeKegiatan)) {
            dilipat.add(kodeKegiatan);
        }
    }

    const total = $derived(baris.reduce((n, b) => n + b.jumlah, 0));
    const jumlahKegiatan = $derived(
        new Set(baris.map((b) => b.kegiatan.kode)).size,
    );

    // Kolom inti saja, supaya tabel tidak perlu digeser ke samping. Sebaran bulan dibuka per baris.
    const KOLOM =
        'md:grid-cols-[minmax(0,1fr)_7.5rem_8rem_8.5rem_auto] md:gap-x-3';

    function kode(b: BarisRkas): string {
        return `${b.kegiatan.kode}${String(b.urutan).padStart(3, '0')}`;
    }

    function rupiah(n: number): string {
        return n.toLocaleString('id-ID');
    }

    function volumeTahap(b: BarisRkas, tahap: 1 | 2): number {
        const awal = tahap === 1 ? 1 : 7;

        return Array.from(
            { length: 6 },
            (_, i) => b.volume_bulan[String(awal + i)] ?? 0,
        ).reduce((n, v) => n + v, 0);
    }

    function hapus(b: BarisRkas): void {
        if (!confirm(`Hapus belanja "${b.uraian}"?`)) {
            return;
        }
        router.delete(BarisRkasController.destroy(b.uuid), {
            preserveScroll: true,
        });
    }
</script>

<div class="grid grid-cols-1 gap-3.5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold tracking-tight">Belanja</h2>
            <p class="text-sm text-muted-foreground">
                {baris.length} belanja di {jumlahKegiatan} kegiatan
            </p>
        </div>
        <div class="flex max-w-full flex-wrap gap-2">
            {#if baris.length > 0}
                <Input
                    type="search"
                    class="w-56 max-w-full"
                    aria-label="Cari belanja"
                    placeholder="Cari belanja"
                    bind:value={cari}
                />
            {/if}
            {#if kelompok.length > 1 && kata === ''}
                <Button type="button" variant="outline" onclick={lipatSemua}>
                    {semuaDilipat ? 'Buka semua' : 'Tutup semua'}
                </Button>
            {/if}
            {#if bisaDiubah}
                <Button type="button" onclick={() => onTambah(null)}>
                    <Plus class="size-4" />
                    Tambah belanja
                </Button>
            {/if}
        </div>
    </div>

    {#if baris.length === 0}
        <p
            class="rounded-[14px] border border-dashed border-input p-7 text-center text-sm text-muted-foreground"
        >
            Belum ada belanja.
            {#if bisaDiubah}
                Mulai dengan tombol Tambah belanja.
            {/if}
        </p>
    {:else if kelompok.length === 0}
        <p
            class="rounded-[14px] border border-dashed border-input p-7 text-center text-sm text-muted-foreground"
        >
            Tidak ada belanja yang cocok.
        </p>
    {/if}

    {#each kelompok as k (k.kode)}
        {@const buka = kata !== '' || !dilipat.has(k.kode)}
        <section aria-label={k.nama} class="rounded-[14px] border bg-card">
            <div
                class={[
                    'flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3 pr-3 pl-2',
                    buka && 'border-b',
                ]}
            >
                <button
                    type="button"
                    class="flex min-w-0 flex-[1_1_18rem] items-start gap-1.5 rounded-lg p-2 text-left hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-expanded={buka}
                    aria-controls={`belanja-${k.kode}`}
                    disabled={kata !== ''}
                    onclick={() => lipat(k.kode)}
                >
                    <ChevronDown
                        class={[
                            'mt-0.5 size-5 shrink-0 text-muted-foreground transition-transform duration-150',
                            !buka && '-rotate-90',
                        ]}
                    />
                    <span class="min-w-0">
                        <span
                            class="block font-semibold [overflow-wrap:anywhere]"
                        >
                            {k.kode}
                            {k.nama}
                        </span>
                        <span class="block text-meta text-muted-foreground">
                            {k.baris.length} belanja{k.jalur ? ` · ${k.jalur}` : ''}
                        </span>
                    </span>
                </button>
                <span class="font-semibold tabular-nums">
                    Rp {rupiah(k.subtotal)}
                </span>
                {#if bisaDiubah}
                    <Button
                        type="button"
                        variant="secondary"
                        aria-label={`Tambah belanja di ${k.nama}`}
                        onclick={() => onTambah(k.refKegiatanId)}
                    >
                        <Plus class="size-4" />
                        Tambah di sini
                    </Button>
                {/if}
            </div>

            <div id={`belanja-${k.kode}`} hidden={!buka}>
                <div
                    class={`hidden px-4 py-2 text-meta font-medium text-muted-foreground md:grid ${KOLOM}`}
                >
                    <span>Uraian</span>
                    <span class="text-right">Volume</span>
                    <span class="text-right">Harga satuan</span>
                    <span class="text-right">Jumlah</span>
                    <span class="w-33"></span>
                </div>

                {#each k.baris as b (b.id)}
                    <div class="border-t first:border-t-0 md:first:border-t">
                        <div
                            class={`grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1 py-2.5 pr-2 pl-4 ${KOLOM}`}
                        >
                            <div class="min-w-0">
                                <div class="font-medium [overflow-wrap:anywhere]">
                                    {b.uraian}
                                </div>
                                <div
                                    class="text-meta text-muted-foreground tabular-nums md:hidden"
                                >
                                    {rupiah(b.volume)}
                                    {b.satuan} × Rp {rupiah(b.harga)}
                                </div>
                                <div class="text-meta text-muted-foreground">
                                    <span title={b.rekening.nama}>
                                        {b.rekening.kode}
                                    </span>
                                    · {ringkasBulan(b.volume_bulan)}
                                </div>
                                {#each b.masalah as pesan (pesan)}
                                    <p class="text-meta font-medium text-belum-foreground">
                                        {pesan}
                                    </p>
                                {/each}
                                {#each b.peringatan as pesan (pesan)}
                                    <p class="text-meta text-kurang-foreground">
                                        {pesan}
                                    </p>
                                {/each}
                            </div>
                            <span class="hidden text-right tabular-nums md:block">
                                {rupiah(b.volume)}
                                {b.satuan}
                            </span>
                            <span class="hidden text-right tabular-nums md:block">
                                {rupiah(b.harga)}
                            </span>
                            <span class="text-right font-semibold tabular-nums">
                                {rupiah(b.jumlah)}
                            </span>
                            <span
                                class="col-span-2 flex justify-end md:col-span-1 md:w-33"
                            >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Sebaran bulan ${b.uraian}`}
                                    aria-expanded={terbuka === b.id}
                                    onclick={() =>
                                        (terbuka = terbuka === b.id ? null : b.id)}
                                >
                                    <CalendarDays class="size-[18px]" />
                                </Button>
                                {#if bisaDiubah}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Ubah ${b.uraian}`}
                                        onclick={() => onUbah(b)}
                                    >
                                        <Pencil class="size-[18px]" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="text-belum-foreground"
                                        aria-label={`Hapus ${b.uraian}`}
                                        onclick={() => hapus(b)}
                                    >
                                        <Trash2 class="size-[18px]" />
                                    </Button>
                                {/if}
                            </span>
                        </div>

                        {#if terbuka === b.id}
                            <div class="grid gap-2 px-4 pb-3">
                                <div class="grid grid-cols-6 gap-1.5 md:grid-cols-12">
                                    {#each BULAN as nama, i (nama)}
                                        {@const v = b.volume_bulan[String(i + 1)] ?? 0}
                                        <div
                                            class={[
                                                'rounded-lg px-1 py-1.5 text-center',
                                                v > 0
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'bg-muted text-muted-foreground',
                                            ]}
                                        >
                                            <div class="text-xs">{nama}</div>
                                            <div class="font-semibold tabular-nums">
                                                {v > 0 ? rupiah(v) : '–'}
                                            </div>
                                        </div>
                                    {/each}
                                </div>
                                <p class="text-meta text-muted-foreground tabular-nums">
                                    Kode {kode(b)} · Tahap 1: {rupiah(volumeTahap(b, 1))}
                                    {b.satuan} · Tahap 2: {rupiah(volumeTahap(b, 2))}
                                    {b.satuan}
                                </p>
                            </div>
                        {/if}
                    </div>
                {/each}
            </div>
        </section>
    {/each}

    {#if baris.length > 0}
        <div
            class="flex justify-between gap-3 rounded-[14px] bg-primary px-4 py-3.5 font-semibold text-primary-foreground"
        >
            <span>Total belanja</span>
            <span class="tabular-nums">Rp {rupiah(total)}</span>
        </div>
    {/if}
</div>
