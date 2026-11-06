<script lang="ts">
    let {
        title,
        description = '',
        variant = 'default',
    }: {
        title: string;
        description?: string;
        variant?: 'default' | 'small';
    } = $props();
</script>

<header class={variant === 'small' ? '' : 'space-y-1.5'}>
    {#if variant === 'small'}
        <h2 class="mb-0.5 text-base font-medium">{title}</h2>
    {:else}
        <h1 class="text-[28px] leading-tight font-semibold tracking-tight">
            {title}
        </h1>
    {/if}
    {#if description}
        <p
            class={variant === 'small'
                ? 'text-sm text-muted-foreground'
                : 'text-[15px] text-muted-foreground'}
        >
            {description}
        </p>
    {/if}
</header>
