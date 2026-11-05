<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import {
        SidebarGroup,
        SidebarGroupLabel,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { toUrl } from '@/lib/utils';
    import type { NavItem } from '@/types';

    let {
        label,
        items = [],
    }: {
        label: string;
        items: NavItem[];
    } = $props();

    const url = currentUrlState();
</script>

<SidebarGroup class="px-2 py-0">
    <SidebarGroupLabel>{label}</SidebarGroupLabel>
    <SidebarMenu>
        {#each items as item (toUrl(item.href))}
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    isActive={url.isCurrentUrl(item.href, url.currentUrl)}
                    tooltip={item.title}
                >
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={toUrl(item.href)}
                            class={props.class}
                        >
                            {#if item.icon}
                                <item.icon class="size-4 shrink-0" />
                            {/if}
                            <span class="flex-1">{item.title}</span>
                            {#if item.badge && item.badge.jumlah > 0}
                                <span
                                    aria-label={item.badge.label}
                                    class="min-w-5 rounded-md bg-destructive px-1.5 text-center text-xs font-semibold text-destructive-foreground tabular-nums group-data-[collapsible=icon]:hidden"
                                >
                                    {item.badge.jumlah}
                                </span>
                            {/if}
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        {/each}
    </SidebarMenu>
</SidebarGroup>
