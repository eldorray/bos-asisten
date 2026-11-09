<script lang="ts">
    import Download from '@lucide/svelte/icons/download';
    import PdfKegiatanController from '@/actions/App/Http/Controllers/PdfKegiatanController';
    import { Button } from '@/components/ui/button';

    let {
        kegiatanUuid,
        draf = false,
        class: className = '',
    }: {
        kegiatanUuid: string;
        draf?: boolean;
        class?: string;
    } = $props();

    function unduh(): void {
        const url = PdfKegiatanController.url(kegiatanUuid);

        // Aplikasi terpasang di iPhone membuka PDF di dalam jendelanya, tanpa tombol kembali.
        if (window.matchMedia('(display-mode: standalone)').matches) {
            window.open(url, '_blank');
        } else {
            window.location.href = url;
        }
    }
</script>

<Button
    type="button"
    size="sm"
    variant="outline"
    class={className}
    onclick={unduh}
>
    <Download class="size-4" />
    {draf ? 'PDF (draf)' : 'PDF'}
</Button>
