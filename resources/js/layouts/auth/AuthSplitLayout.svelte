<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import KuitansiContoh from '@/components/spj/KuitansiContoh.svelte';
    import { home } from '@/routes';

    let {
        title = '',
        description = '',
        children,
    }: {
        title?: string;
        description?: string;
        children?: Snippet;
    } = $props();
</script>

<div class="grid min-h-svh bg-background lg:grid-cols-2">
    <main class="flex flex-col justify-center px-6 py-8 sm:px-12">
        <div class="mx-auto grid w-full max-w-sm gap-8">
            <Link href={home()} class="flex items-center gap-3">
                <AppLogo />
            </Link>

            <div class="grid gap-2">
                {#if title}
                    <h1 class="text-2xl leading-tight font-medium">{title}</h1>
                {/if}
                {#if description}
                    <p class="text-sm text-muted-foreground">{description}</p>
                {/if}
            </div>

            <div>{@render children?.()}</div>
        </div>
    </main>

    <aside
        aria-hidden="true"
        class="hidden items-center justify-center border-l bg-sidebar p-12 lg:flex"
    >
        <KuitansiContoh />
    </aside>
</div>
