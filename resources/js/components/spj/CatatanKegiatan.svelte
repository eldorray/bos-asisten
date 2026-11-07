<script lang="ts">
    import { namaBulan } from '@/types';
    import type { BarisKegiatanData } from '@/types';

    let { kegiatan }: { kegiatan: BarisKegiatanData } = $props();
</script>

{#if kegiatan.acara}
    <p class="text-meta text-dirapel-foreground">
        Satu acara: {kegiatan.acara.nama}
    </p>
{/if}
{#if kegiatan.perluDitinjau}
    <p class="text-meta text-kurang-foreground">
        Jenis: {kegiatan.jenis}, perlu ditinjau
    </p>
{/if}
{#if kegiatan.ditundaDari}
    <p class="text-meta text-dirapel-foreground">
        Ditunda dari anggaran {namaBulan[kegiatan.ditundaDari - 1]}
    </p>
{/if}
{#if kegiatan.perluDiperiksa}
    <p class="text-meta font-medium text-belum-foreground">
        Perlu diperiksa: {kegiatan.perluDiperiksa}
    </p>
{:else if kegiatan.hilangDariAnggaran}
    <p class="text-meta font-medium text-belum-foreground">
        Tidak ada lagi di anggaran
    </p>
{/if}
{#if kegiatan.catatanStatus}
    <p class="text-meta text-muted-foreground">
        Catatan: {kegiatan.catatanStatus}
    </p>
{/if}
