<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Upload from '@lucide/svelte/icons/upload';
    import ImporController from '@/actions/App/Http/Controllers/ImporController';
    import Breadcrumbs from '@/components/Breadcrumbs.svelte';
    import { Button } from '@/components/ui/button';
    import { SidebarTrigger } from '@/components/ui/sidebar';
    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { dashboard } from '@/routes';
    import type { BreadcrumbItem } from '@/types';

    let {
        breadcrumbs = [],
    }: {
        breadcrumbs?: BreadcrumbItem[];
    } = $props();

    const url = currentUrlState();
</script>

<header class="flex min-h-17 shrink-0 items-center justify-between gap-2 px-3 py-3">
    <div class="flex min-w-0 items-center gap-2">
        <SidebarTrigger class="-ml-1" />
        {#if breadcrumbs && breadcrumbs.length > 0}
            <Breadcrumbs {breadcrumbs} />
        {/if}
    </div>
    {#if url.isCurrentUrl(dashboard(), url.currentUrl)}
        <Button asChild>
            {#snippet children(props)}
                <Link href={ImporController.index()} class={props.class}>
                    <Upload class="size-4" />
                    Impor anggaran
                </Link>
            {/snippet}
        </Button>
    {/if}
</header>
