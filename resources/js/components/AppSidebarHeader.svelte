<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ChevronLeft from '@lucide/svelte/icons/chevron-left';
    import Upload from '@lucide/svelte/icons/upload';
    import ImporController from '@/actions/App/Http/Controllers/ImporController';
    import Breadcrumbs from '@/components/Breadcrumbs.svelte';
    import { Button } from '@/components/ui/button';
    import { SidebarTrigger } from '@/components/ui/sidebar';
    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { dashboard, lainnya } from '@/routes';
    import type { BreadcrumbItem } from '@/types';

    let {
        breadcrumbs = [],
    }: {
        breadcrumbs?: BreadcrumbItem[];
    } = $props();

    const url = currentUrlState();

    // Di HP, halaman tab tidak butuh kepala; halaman lain hanya butuh jalan kembali.
    const halamanTab = $derived(
        ['Dashboard', 'impor/Index', 'Lainnya'].includes(page.component),
    );

    const tujuanKembali = $derived.by(() => {
        if (page.component !== 'kegiatan/Show') {
            return lainnya();
        }

        const { tahun, bulan: bulanAnggaran, bulanPelaksanaan } = page.props
            .kegiatan as {
            tahun: number;
            bulan: number | null;
            bulanPelaksanaan: number | null;
        };
        const bulan = bulanPelaksanaan ?? bulanAnggaran;

        return bulan === null
            ? dashboard()
            : dashboard({
                  query: {
                      bulan: `${tahun}-${String(bulan).padStart(2, '0')}`,
                  },
              });
    });
</script>

{#if !halamanTab}
    <header
        class="sticky top-0 z-10 flex items-center gap-1 border-b bg-background px-2 py-1.5 md:hidden"
    >
        <Link
            href={tujuanKembali}
            aria-label="Kembali"
            class="grid size-11 place-items-center"
        >
            <ChevronLeft class="size-[22px]" />
        </Link>
        <span class="truncate font-semibold">{breadcrumbs.at(-1)?.title}</span>
    </header>
{/if}

<header
    class="hidden min-h-17 shrink-0 items-center justify-between gap-2 px-3 py-3 md:flex"
>
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
