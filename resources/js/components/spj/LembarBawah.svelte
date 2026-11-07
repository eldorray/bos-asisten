<script lang="ts">
    import X from '@lucide/svelte/icons/x';
    import type { Snippet } from 'svelte';
    import { Button } from '@/components/ui/button';
    import { cn } from '@/lib/utils';

    // <dialog> bawaan: fokus terkunci, Esc, dan tombol kembali Android menutupnya tanpa kode tambahan.
    let {
        terbuka,
        ontutup,
        judul,
        sebarisDiDesktop = false,
        penuh = false,
        children,
    }: {
        terbuka: boolean;
        ontutup: () => void;
        judul: string;
        // Di desktop isinya tampil biasa di halaman; lembar hanya berlaku di HP.
        sebarisDiDesktop?: boolean;
        penuh?: boolean;
        children: Snippet;
    } = $props();

    let dialog: HTMLDialogElement;

    $effect(() => {
        if (terbuka && !dialog.open) {
            dialog.showModal();
        } else if (!terbuka && dialog.open) {
            dialog.close();
        }
    });
</script>

<dialog
    bind:this={dialog}
    aria-label={judul}
    onclose={ontutup}
    onclick={(e) => e.target === dialog && ontutup()}
    class={cn(
        'max-md:animate-in max-md:fade-in max-md:slide-in-from-bottom-4 max-md:duration-300 max-md:ease-tenang fixed inset-x-0 top-auto bottom-0 m-0 max-h-[90dvh] w-full max-w-none overflow-y-auto rounded-t-3xl bg-card p-0 text-foreground backdrop:bg-black/45',
        penuh && 'top-0 h-dvh max-h-dvh rounded-none',
        sebarisDiDesktop
            ? 'md:static md:block md:h-auto md:max-h-none md:overflow-visible md:rounded-none md:bg-transparent'
            : 'md:top-1/2 md:right-auto md:bottom-auto md:left-1/2 md:h-auto md:max-h-[85vh] md:max-w-lg md:-translate-x-1/2 md:-translate-y-1/2 md:rounded-2xl',
    )}
>
    <div
        class={cn(
            'grid grid-cols-1 gap-4 px-4 pb-[calc(1.5rem+env(safe-area-inset-bottom))]',
            sebarisDiDesktop ? 'md:gap-3 md:p-0' : 'md:p-5',
        )}
    >
        <div
            class={cn(
                'sticky top-0 z-10 -mx-4 flex items-center justify-between gap-2 bg-card py-2 pr-2 pl-4',
                sebarisDiDesktop
                    ? 'md:hidden'
                    : 'md:static md:mx-0 md:bg-transparent md:p-0',
            )}
        >
            <h2 class="text-lg font-medium">{judul}</h2>
            <Button
                variant="ghost"
                size="icon"
                aria-label="Tutup"
                onclick={ontutup}
            >
                <X class="size-5" />
            </Button>
        </div>
        {@render children()}
    </div>
</dialog>
