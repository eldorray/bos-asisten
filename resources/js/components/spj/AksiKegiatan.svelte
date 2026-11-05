<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import KegiatanController from '@/actions/App/Http/Controllers/KegiatanController';
    import { Button } from '@/components/ui/button';
    import type { BarisKegiatanData } from '@/types';

    let { kegiatan }: { kegiatan: BarisKegiatanData } = $props();

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

{#if kegiatan.status === 'di_luar_aplikasi'}
    <Button variant="outline" size="sm" onclick={batalkanTanda}>Batalkan</Button>
{/if}
{#if kegiatan.hilangDariAnggaran}
    <Button variant="destructive" size="sm" onclick={hapus}>Hapus</Button>
{/if}
