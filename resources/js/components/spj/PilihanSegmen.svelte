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
    class="flex max-w-full gap-0.5 overflow-x-auto rounded-xl bg-accent p-1"
>
    {#each pilihan as p (p.nilai)}
        <button
            type="button"
            aria-pressed={p.nilai === nilai}
            class={cn(
                'inline-flex h-11 shrink-0 items-center gap-2 rounded-[9px] px-3.5 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
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
                    class="min-w-5 rounded-md bg-belum px-1.5 text-center text-xs font-semibold text-belum-foreground tabular-nums"
                >
                    {p.lencana}
                </span>
            {/if}
        </button>
    {/each}
</div>
