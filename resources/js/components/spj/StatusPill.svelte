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
            kelas: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        },
        kurang: {
            label: 'Kurang',
            kelas: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300',
        },
        belum: {
            label: 'Belum',
            kelas: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
        },
        dirapel: {
            label: 'Dirapel',
            kelas: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
        },
        tidak_dilaksanakan: {
            label: 'Tidak dilaksanakan',
            kelas: 'bg-muted text-muted-foreground',
        },
        di_luar_aplikasi: {
            label: 'Di luar aplikasi',
            kelas: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        },
    };

    const label = $derived(
        status === 'kurang' ? `Kurang ${kurang}` : tampil[status].label,
    );
</script>

<span
    class={cn(
        'inline-block rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap',
        tampil[status].kelas,
    )}
>
    {label}
</span>
