<script lang="ts" generics="T extends string | number">
    import TriangleAlert from '@lucide/svelte/icons/triangle-alert';
    import { cn } from '@/lib/utils';
    import type { NavIcon } from '@/types';

    let {
        label,
        nilai,
        pilihan,
        onpilih,
    }: {
        label: string;
        nilai: T;
        pilihan: {
            nilai: T;
            label: string;
            ikon?: NavIcon;
            lencana?: number;
            peringatan?: string | null;
        }[];
        onpilih: (nilai: T) => void;
    } = $props();
</script>

<div
    role="group"
    aria-label={label}
    class="grid max-w-full auto-cols-[minmax(max-content,1fr)] grid-flow-col gap-0.5 overflow-x-auto rounded-xl bg-accent p-1 md:flex"
>
    {#each pilihan as p (p.nilai)}
        <button
            type="button"
            aria-pressed={p.nilai === nilai}
            class={cn(
                'inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-md px-3 text-sm font-medium focus-visible:ring-2 md:px-4 focus-visible:ring-ring focus-visible:outline-none',
                p.nilai === nilai
                    ? 'bg-card shadow-xs'
                    : 'text-muted-foreground hover:text-foreground',
            )}
            onclick={() => onpilih(p.nilai)}
        >
            {#if p.ikon}
                <p.ikon class="size-4" />
            {/if}
            {p.label}
            {#if p.peringatan}
                <TriangleAlert
                    class="size-4 text-destructive"
                    aria-label={p.peringatan}
                />
            {/if}
            {#if p.lencana}
                <span
                    class="min-w-5 rounded-md bg-card px-1.5 text-center text-xs font-medium text-muted-foreground tabular-nums"
                >
                    {p.lencana}
                </span>
            {/if}
        </button>
    {/each}
</div>
