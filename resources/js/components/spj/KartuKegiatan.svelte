<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Tag from '@lucide/svelte/icons/tag';
    import KegiatanController from '@/actions/App/Http/Controllers/KegiatanController';
    import AksiKegiatan from '@/components/spj/AksiKegiatan.svelte';
    import CatatanKegiatan from '@/components/spj/CatatanKegiatan.svelte';
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

<article class="grid grid-cols-1 gap-2.5 rounded-[14px] border bg-card p-3.5">
    <div class="flex items-center justify-between gap-2">
        <span
            class="inline-flex min-w-0 items-center gap-1.5 text-[13px] text-muted-foreground"
        >
            <Tag class="size-3.5 shrink-0" />
            <span class="truncate">{kegiatan.jenis}</span>
        </span>
        <StatusPill status={kegiatan.status} kurang={kegiatan.kurang} />
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
                class="block text-[15px] font-semibold tracking-tight [overflow-wrap:anywhere] hover:underline"
            >
                {kegiatan.nama}
            </Link>
            <p class="mt-0.5 text-[13px] text-muted-foreground">
                {kegiatan.koefisien}
            </p>
        </div>
    </div>

    {#if kegiatan.status === 'kurang'}
        <p class="text-[13px] text-belum-foreground">
            <span class="font-semibold">Kurang:</span>
            {berkasKurang.join(', ')}
        </p>
    {/if}

    <CatatanKegiatan {kegiatan} />

    <div
        class="flex flex-wrap items-center justify-between gap-1.5 border-t pt-2.5 text-[13px]"
    >
        <span class="text-muted-foreground">{keterangan}</span>
        <span class="font-semibold tabular-nums">
            Rp {rupiah(kegiatan.jumlah)}
        </span>
    </div>

    {#if kegiatan.status === 'di_luar_aplikasi' || kegiatan.hilangDariAnggaran}
        <div class="flex flex-wrap gap-2">
            <AksiKegiatan {kegiatan} />
        </div>
    {/if}
</article>
