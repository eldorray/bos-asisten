<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import KegiatanController from '@/actions/App/Http/Controllers/KegiatanController';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import StatusPill from '@/components/spj/StatusPill.svelte';
    import { namaBulan, rupiah } from '@/types';
    import type { BarisKegiatanData } from '@/types';

    let {
        kegiatan,
        pilihan = null,
        dipilih = $bindable(false),
    }: {
        kegiatan: BarisKegiatanData;
        pilihan?: 'tandai' | null;
        dipilih?: boolean;
    } = $props();

    function hapus(): void {
        if (confirm(`Hapus "${kegiatan.nama}" dari aplikasi?`)) {
            router.delete(KegiatanController.destroy(kegiatan.id).url, {
                preserveScroll: true,
            });
        }
    }

    function batalkanTanda(): void {
        router.post(
            KegiatanController.tandai().url,
            { ids: [kegiatan.id], status_manual: null },
            { preserveScroll: true },
        );
    }
</script>

<div
    class="grid gap-2 border-b py-3 md:grid-cols-[auto_minmax(150px,2fr)_100px_minmax(160px,2fr)_auto] md:items-center md:gap-3"
>
    {#if pilihan === 'tandai'}
        <input
            type="checkbox"
            class="size-5"
            aria-label={`Pilih ${kegiatan.nama}`}
            bind:checked={dipilih}
        />
    {:else}
        <span class="hidden md:block"></span>
    {/if}

    <div>
        <Link
            href={KegiatanController.show(kegiatan.id)}
            class="font-medium hover:underline"
        >
            {kegiatan.nama}
        </Link>
        <small class="block text-xs text-muted-foreground">
            {kegiatan.koefisien}
        </small>
        {#if kegiatan.perluDitinjau}
            <Badge variant="outline" class="mt-1 border-amber-500 text-amber-700">
                Jenis: {kegiatan.jenis}, perlu ditinjau
            </Badge>
        {/if}
        {#if kegiatan.hilangDariAnggaran}
            <p class="mt-1 text-xs font-semibold text-red-700 dark:text-red-400">
                Tidak ada lagi di anggaran
            </p>
        {/if}
        {#if kegiatan.catatanStatus}
            <p class="mt-1 text-xs text-muted-foreground">
                Catatan: {kegiatan.catatanStatus}
            </p>
        {/if}
    </div>

    <div class="text-sm md:text-right">Rp {rupiah(kegiatan.jumlah)}</div>

    <div class="text-sm">
        {#if kegiatan.rapel}
            Dirapel di kuitansi {kegiatan.rapel.nomor} ({namaBulan[
                kegiatan.rapel.bulan - 1
            ]})
        {:else if kegiatan.status === 'tidak_dilaksanakan' || kegiatan.status === 'di_luar_aplikasi'}
            <span class="text-muted-foreground">Tidak masuk PDF</span>
        {:else}
            {#each kegiatan.berkas as berkas, i (berkas.nama)}
                {#if i > 0}
                    <span class="text-muted-foreground"> · </span>
                {/if}
                {#if berkas.ada}
                    <span>✓ {berkas.nama}</span>
                {:else}
                    <span class="font-semibold text-red-700 dark:text-red-400">
                        ✗ {berkas.nama}
                    </span>
                {/if}
            {/each}
        {/if}
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <StatusPill status={kegiatan.status} kurang={kegiatan.kurang} />
        {#if kegiatan.status === 'di_luar_aplikasi'}
            <Button variant="outline" size="sm" onclick={batalkanTanda}>
                Batalkan
            </Button>
        {/if}
        {#if kegiatan.hilangDariAnggaran}
            <Button variant="destructive" size="sm" onclick={hapus}>
                Hapus
            </Button>
        {/if}
    </div>
</div>
