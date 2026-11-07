<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import KegiatanController from '@/actions/App/Http/Controllers/KegiatanController';
    import AksiKegiatan from '@/components/spj/AksiKegiatan.svelte';
    import CatatanKegiatan from '@/components/spj/CatatanKegiatan.svelte';
    import SlotBerkas from '@/components/spj/SlotBerkas.svelte';
    import StatusPill from '@/components/spj/StatusPill.svelte';
    import { namaBulan, rupiah } from '@/types';
    import type { BarisKegiatanData } from '@/types';

    let {
        kegiatan,
        menandai = false,
        dipilih = $bindable(),
    }: {
        kegiatan: BarisKegiatanData;
        menandai?: boolean;
        dipilih?: boolean;
    } = $props();

    const berkasKurang = $derived(
        kegiatan.berkas.filter((b) => !b.ada).map((b) => b.nama),
    );

    const keterangan = $derived.by(() => {
        if (kegiatan.rapel) {
            return `Dirapel di kuitansi ${kegiatan.rapel.nomor} (${namaBulan[kegiatan.rapel.bulan - 1]})`;
        }

        if (
            kegiatan.status === 'tidak_dilaksanakan' ||
            kegiatan.status === 'di_luar_aplikasi'
        ) {
            return 'Tidak masuk PDF';
        }

        return `${kegiatan.berkas.length - berkasKurang.length}/${kegiatan.berkas.length} berkas`;
    });
</script>

<article class="flex flex-col gap-2 rounded-xl border bg-card p-4">
    <div class="flex items-center justify-between gap-2">
        <span class="min-w-0 truncate text-meta text-muted-foreground">
            {kegiatan.jenis}
        </span>
        <!-- Kolom papan sudah menyebut status biasa; hanya yang khusus diberi label. -->
        {#if kegiatan.status === 'dirapel' || kegiatan.status === 'di_luar_aplikasi'}
            <StatusPill status={kegiatan.status} kurang={kegiatan.kurang} />
        {/if}
    </div>

    <div class="flex items-start gap-2">
        {#if menandai}
            <input
                type="checkbox"
                class="mt-0.5 size-5 shrink-0 accent-primary"
                aria-label={`Pilih ${kegiatan.nama}`}
                bind:checked={dipilih}
            />
        {/if}
        <div class="min-w-0">
            <Link
                href={KegiatanController.show(kegiatan.id)}
                class="block text-nama font-medium [overflow-wrap:anywhere] hover:underline"
            >
                {kegiatan.nama}
            </Link>
            <p class="mt-0.5 text-meta text-muted-foreground">
                {kegiatan.koefisien}
            </p>
        </div>
    </div>

    {#if kegiatan.status === 'kurang'}
        <p class="text-meta text-kurang-foreground">
            <span class="font-medium">Kurang:</span>
            {berkasKurang.join(', ')}
        </p>
    {/if}

    <CatatanKegiatan {kegiatan} />

    <div
        class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t pt-2 text-meta"
    >
        <span class="inline-flex items-center gap-2 text-muted-foreground">
            {#if !kegiatan.rapel && kegiatan.status !== 'tidak_dilaksanakan' && kegiatan.status !== 'di_luar_aplikasi'}
                <SlotBerkas berkas={kegiatan.berkas} />
            {/if}
            {keterangan}
        </span>
        <span class="font-medium tabular-nums">
            Rp {rupiah(kegiatan.jumlah)}
        </span>
    </div>

    {#if kegiatan.status === 'di_luar_aplikasi' || kegiatan.hilangDariAnggaran}
        <div class="flex flex-wrap gap-2">
            <AksiKegiatan {kegiatan} />
        </div>
    {/if}
</article>
