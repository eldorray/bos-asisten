<script lang="ts">
    import type { BarisKegiatanData } from '@/types';

    let { kegiatan }: { kegiatan: BarisKegiatanData } = $props();
</script>

{#if kegiatan.acara}
    <p class="text-[13px] text-dirapel-foreground">
        Satu acara: {kegiatan.acara.nama}
    </p>
{/if}
{#if kegiatan.perluDitinjau}
    <p class="text-[13px] text-kurang-foreground">
        Jenis: {kegiatan.jenis}, perlu ditinjau
    </p>
{/if}
{#if kegiatan.hilangDariAnggaran}
    <p class="text-[13px] font-semibold text-belum-foreground">
        Tidak ada lagi di anggaran
    </p>
{/if}
{#if kegiatan.catatanStatus}
    <p class="text-[13px] text-muted-foreground">
        Catatan: {kegiatan.catatanStatus}
    </p>
{/if}
