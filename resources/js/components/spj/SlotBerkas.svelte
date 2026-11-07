<script lang="ts">
    import type { BerkasStatus } from '@/types';

    let {
        berkas,
        label,
    }: {
        berkas: BerkasStatus[];
        label?: string;
    } = $props();

    const ada = $derived(berkas.filter((b) => b.ada).length);
</script>

<!-- Satu kotak untuk tiap lembar berkas; terisi saat berkasnya sudah ada. -->
<span
    role="img"
    aria-label={label ?? `${ada} dari ${berkas.length} berkas`}
    class="inline-flex shrink-0 items-center gap-0.5"
>
    {#each berkas as b (b.nama)}
        <span
            title={b.nama}
            class={[
                'h-3 w-2.5 rounded-xs border',
                b.ada
                    ? 'border-foreground bg-foreground'
                    : 'border-muted-foreground/50',
            ]}
        ></span>
    {/each}
</span>
