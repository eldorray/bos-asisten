<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AppContent from '@/components/AppContent.svelte';
    import AppShell from '@/components/AppShell.svelte';
    import AppSidebar from '@/components/AppSidebar.svelte';
    import AppSidebarHeader from '@/components/AppSidebarHeader.svelte';
    import AjakanPasang from '@/components/spj/AjakanPasang.svelte';
    import BannerAntrean from '@/components/spj/BannerAntrean.svelte';
    import TabBawah from '@/components/TabBawah.svelte';
    import { Toaster } from '@/components/ui/sonner';
    import type { BreadcrumbItem } from '@/types';

    let {
        breadcrumbs = [],
        children,
    }: {
        breadcrumbs?: BreadcrumbItem[];
        children?: Snippet;
    } = $props();

    // Rincian punya tombol aksi sendiri di bawah layar.
    const adaTab = $derived(page.component !== 'kegiatan/Show');
</script>

<AppShell variant="sidebar">
    <AppSidebar />
    <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
        <AppSidebarHeader {breadcrumbs} />
        <div
            class={[
                'flex-1 bg-background md:mr-2 md:mb-2 md:rounded-2xl md:border',
                adaTab &&
                    'max-md:pb-[calc(5.5rem+env(safe-area-inset-bottom))]',
            ]}
        >
            <BannerAntrean />
            {@render children?.()}
        </div>
    </AppContent>
    {#if adaTab}
        <TabBawah />
    {/if}
    <AjakanPasang />
    <Toaster />
</AppShell>
