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
</script>

<div
    class="grid gap-2 border-b px-4 py-3 last:border-b-0 lg:grid-cols-[minmax(0,2fr)_110px_minmax(0,2fr)_160px] lg:items-center lg:gap-4"
>
    <div class="flex min-w-0 items-start gap-2">
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
                href={KegiatanController.show(kegiatan.uuid)}
                class="font-medium [overflow-wrap:anywhere] hover:underline"
            >
                {kegiatan.nama}
            </Link>
            <p class="text-meta text-muted-foreground">
                {kegiatan.koefisien} · {kegiatan.jenis}
            </p>
            <CatatanKegiatan {kegiatan} />
        </div>
    </div>

    <div class="text-sm tabular-nums lg:text-right">
        Rp {rupiah(kegiatan.jumlah)}
    </div>

    <div class="flex flex-wrap gap-x-2.5 gap-y-1 text-meta">
        {#if kegiatan.rapel}
            <span>
                Dirapel di kuitansi {kegiatan.rapel.nomor} ({namaBulan[
                    kegiatan.rapel.bulan - 1
                ]})
            </span>
        {:else if kegiatan.status === 'tidak_dilaksanakan' || kegiatan.status === 'di_luar_aplikasi'}
            <span class="text-muted-foreground">Tidak masuk PDF</span>
        {:else}
            {#each kegiatan.berkas as berkas (berkas.nama)}
                <span
                    class={[
                        'inline-flex items-center gap-1',
                        berkas.ada && 'text-muted-foreground',
                    ]}
                >
                    <SlotBerkas
                        berkas={[berkas]}
                        label={berkas.ada ? 'Ada' : 'Kurang'}
                    />
                    {berkas.nama}
                </span>
            {/each}
        {/if}
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <StatusPill status={kegiatan.status} kurang={kegiatan.kurang} />
        <AksiKegiatan {kegiatan} />
    </div>
</div>
