<script lang="ts">
    import type { Snippet } from 'svelte';
    import { cn } from '@/lib/utils';

    let {
        class: className = '',
        children,
    }: {
        class?: string;
        children?: Snippet;
    } = $props();
</script>

<div data-slot="card" class={cn('bg-card text-card-foreground flex flex-col gap-4 rounded-[14px] border py-4', className)}>
    {@render children?.()}
</div>
