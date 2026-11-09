<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
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
        simpanUntukOffline = false,
    }: {
        kegiatan: BarisKegiatanData;
        menandai?: boolean;
        dipilih?: boolean;
        simpanUntukOffline?: boolean;
    } = $props();

    const keterangan = $derived.by(() => {
        if (kegiatan.rapel) {
            return `Kuitansi ${kegiatan.rapel.nomor} (${namaBulan[kegiatan.rapel.bulan - 1]})`;
        }

        switch (kegiatan.status) {
            case 'kurang':
                return kegiatan.berkas
                    .filter((b) => !b.ada)
                    .map((b) => b.nama)
                    .join(', ');
            case 'belum':
                return 'Belum ada berkas';
            case 'selesai':
                return 'Lengkap';
            default:
                return 'Tidak masuk PDF';
        }
    });
</script>

<li class="rounded-2xl border bg-card">
    <div class="flex items-center gap-2 py-4 pr-3 pl-4">
        {#if menandai}
            <input
                type="checkbox"
                class="size-[22px] shrink-0 accent-primary"
                aria-label={`Pilih ${kegiatan.nama}`}
                bind:checked={dipilih}
            />
        {/if}
        <Link
            href={KegiatanController.show(kegiatan.uuid)}
            prefetch={simpanUntukOffline ? 'mount' : false}
            cacheFor="5m"
            class="flex min-w-0 flex-1 items-center gap-2 transition-opacity duration-150 ease-tenang active:opacity-60"
        >
            <span class="grid min-w-0 flex-1 gap-1">
                <span class="font-medium [overflow-wrap:anywhere]">
                    {kegiatan.nama}
                </span>
                <span class="text-meta text-muted-foreground tabular-nums">
                    {kegiatan.koefisien} · Rp {rupiah(kegiatan.jumlah)}
                </span>
                <span class="mt-0.5 flex flex-wrap items-center gap-2">
                    {#if kegiatan.status === 'belum' || kegiatan.status === 'kurang' || kegiatan.status === 'selesai'}
                        <SlotBerkas berkas={kegiatan.berkas} />
                    {:else}
                        <StatusPill
                            status={kegiatan.status}
                            kurang={kegiatan.kurang}
                        />
                    {/if}
                    <span
                        class={`text-meta ${kegiatan.status === 'kurang' ? 'text-kurang-foreground' : 'text-muted-foreground'}`}
                    >
                        {keterangan}
                    </span>
                </span>
                <CatatanKegiatan {kegiatan} />
            </span>
            <ChevronRight class="size-5 shrink-0 text-muted-foreground" />
        </Link>
    </div>
    {#if kegiatan.status === 'di_luar_aplikasi' || kegiatan.hilangDariAnggaran || kegiatan.bisaUnduhPdf}
        <div class="flex flex-wrap gap-2 px-4 pb-4">
            <AksiKegiatan {kegiatan} />
        </div>
    {/if}
</li>
