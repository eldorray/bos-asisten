<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import CalendarCheck from '@lucide/svelte/icons/calendar-check';
    import Menu from '@lucide/svelte/icons/menu';
    import Upload from '@lucide/svelte/icons/upload';
    import ImporController from '@/actions/App/Http/Controllers/ImporController';
    import { cn } from '@/lib/utils';
    import { dashboard, lainnya } from '@/routes';

    const tab = $derived([
        {
            judul: 'Bulan Ini',
            href: dashboard(),
            ikon: CalendarCheck,
            aktif: page.component === 'Dashboard',
            lencana: page.props.menunggakBulanIni,
        },
        {
            judul: 'Impor',
            href: ImporController.index(),
            ikon: Upload,
            aktif: page.component === 'impor/Index',
            lencana: 0,
        },
        {
            judul: 'Lainnya',
            href: lainnya(),
            ikon: Menu,
            aktif: !['Dashboard', 'impor/Index'].includes(page.component),
            lencana: 0,
        },
    ]);
</script>

<nav
    aria-label="Menu utama"
    class="fixed inset-x-0 bottom-0 z-20 grid grid-cols-3 gap-1 border-t bg-card px-3 pt-2 pb-[calc(0.5rem+env(safe-area-inset-bottom))] md:hidden"
>
    {#each tab as t (t.judul)}
        <Link
            href={t.href}
            aria-current={t.aktif ? 'page' : undefined}
            class={cn(
                'relative flex h-14 flex-col items-center justify-center gap-0.5 rounded-xl text-xs font-medium',
                t.aktif
                    ? 'bg-muted font-semibold text-foreground'
                    : 'text-muted-foreground',
            )}
        >
            <t.ikon class="size-[22px]" />
            {t.judul}
            {#if t.lencana > 0}
                <span
                    aria-label={`${t.lencana} kegiatan bulan ini belum selesai`}
                    class="absolute top-0.5 left-[calc(50%+8px)] min-w-[18px] rounded-full bg-primary px-1 text-center text-[11px] leading-[18px] font-semibold text-primary-foreground tabular-nums"
                >
                    {t.lencana}
                </span>
            {/if}
        </Link>
    {/each}
</nav>
