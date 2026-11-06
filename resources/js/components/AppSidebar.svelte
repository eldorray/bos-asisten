<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ListChecks from '@lucide/svelte/icons/list-checks';
    import Download from '@lucide/svelte/icons/download';
    import LayoutGrid from '@lucide/svelte/icons/layout-grid';
    import School from '@lucide/svelte/icons/school';
    import Upload from '@lucide/svelte/icons/upload';
    import type { Snippet } from 'svelte';
    import ImporController from '@/actions/App/Http/Controllers/ImporController';
    import JenisKegiatanController from '@/actions/App/Http/Controllers/JenisKegiatanController';
    import SekolahController from '@/actions/App/Http/Controllers/SekolahController';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';
    import { unduh as cadanganUnduh } from '@/routes/cadangan';
    import type { NavItem } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const navLaporan: NavItem[] = $derived([
        {
            title: 'Bulanan',
            href: dashboard(),
            icon: LayoutGrid,
            badge: {
                jumlah: page.props.menunggakBulanIni,
                label: `${page.props.menunggakBulanIni} kegiatan bulan ini belum selesai`,
            },
        },
        {
            title: 'Impor Anggaran',
            href: ImporController.index(),
            icon: Upload,
        },
    ]);

    const navPengaturan: NavItem[] = [
        {
            title: 'Sekolah',
            href: SekolahController.index(),
            icon: School,
        },
        {
            title: 'Jenis Kegiatan',
            href: JenisKegiatanController.index(),
            icon: ListChecks,
        },
        {
            title: 'Unduh Cadangan',
            href: cadanganUnduh(),
            icon: Download,
            unduh: true,
        },
    ];
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton
                    size="lg"
                    asChild
                    class="hover:bg-transparent active:bg-transparent"
                >
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={toUrl(dashboard())}
                            class={props.class}
                        >
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain label="Laporan" items={navLaporan} />
        <NavMain label="Pengaturan" items={navPengaturan} />
    </SidebarContent>

    <SidebarFooter>
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
