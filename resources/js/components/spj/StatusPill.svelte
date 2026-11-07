<script lang="ts">
    import { cn } from '@/lib/utils';
    import type { StatusKode } from '@/types';

    let {
        status,
        kurang = 0,
    }: {
        status: StatusKode;
        kurang?: number;
    } = $props();

    const tampil: Record<StatusKode, { label: string; kelas: string }> = {
        selesai: {
            label: 'Selesai',
            kelas: 'bg-selesai text-selesai-foreground',
        },
        kurang: {
            label: 'Kurang',
            kelas: 'bg-kurang text-kurang-foreground',
        },
        belum: {
            label: 'Belum',
            kelas: 'bg-batal text-batal-foreground',
        },
        dirapel: {
            label: 'Dirapel',
            kelas: 'bg-dirapel text-dirapel-foreground',
        },
        tidak_dilaksanakan: {
            label: 'Tidak dilaksanakan',
            kelas: 'bg-batal text-batal-foreground',
        },
        di_luar_aplikasi: {
            label: 'Di luar aplikasi',
            kelas: 'bg-selesai text-selesai-foreground',
        },
    };

    const label = $derived(
        status === 'kurang' ? `Kurang ${kurang}` : tampil[status].label,
    );
</script>

<span
    class={cn(
        'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap',
        tampil[status].kelas,
    )}
>
    {label}
</span>
