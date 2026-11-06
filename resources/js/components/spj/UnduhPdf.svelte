<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import Download from '@lucide/svelte/icons/download';
    import KegiatanController from '@/actions/App/Http/Controllers/KegiatanController';
    import PdfBulananController from '@/actions/App/Http/Controllers/PdfBulananController';
    import LembarBawah from '@/components/spj/LembarBawah.svelte';
    import { Button } from '@/components/ui/button';
    import { cn } from '@/lib/utils';
    import type { SekolahBulan } from '@/types';

    let {
        sekolah,
        bulan,
        label = 'Unduh PDF',
        class: className = '',
    }: {
        sekolah: SekolahBulan;
        bulan: string;
        label?: string;
        class?: string;
    } = $props();

    let peringatanTerbuka = $state(false);

    const menunggak = $derived(
        sekolah.kegiatan
            .filter((k) => k.status === 'kurang' || k.status === 'belum')
            .map((k) => ({
                id: k.id,
                nama: k.nama,
                kurang: k.berkas.filter((b) => !b.ada).map((b) => b.nama),
            })),
    );

    const diperiksa = $derived(sekolah.kegiatan.filter((k) => k.perluDiperiksa));

    function unduh(): void {
        peringatanTerbuka = false;

        // Bukan kunjungan Inertia: hasilnya file, atau redirect biasa kalau bulan ini kosong.
        const url = PdfBulananController.url({
            sekolah: sekolah.id,
            tahun: Number(bulan.slice(0, 4)),
            bulan: Number(bulan.slice(5, 7)),
        });

        // Aplikasi terpasang di iPhone membuka PDF di dalam jendelanya, tanpa tombol kembali.
        if (window.matchMedia('(display-mode: standalone)').matches) {
            window.open(url, '_blank');
        } else {
            window.location.href = url;
        }
    }

    function tekanUnduh(): void {
        if (menunggak.length > 0 || diperiksa.length > 0) {
            peringatanTerbuka = true;
        } else {
            unduh();
        }
    }
</script>

<Button class={cn(className)} onclick={tekanUnduh}>
    <Download class="size-4" />
    {label}
</Button>

<LembarBawah
    judul={`Periksa dulu SPJ ${sekolah.nama}`}
    terbuka={peringatanTerbuka}
    ontutup={() => (peringatanTerbuka = false)}
>
    <p class="rounded-xl bg-kurang p-3.5 text-[15px] text-kurang-foreground">
        {#if menunggak.length > 0}
            {menunggak.length} kegiatan masih kurang berkas.
        {/if}
        {#if diperiksa.length > 0}
            {diperiksa.length} kegiatan perlu diperiksa dan tetap ikut tercetak.
        {/if}
        PDF tetap bisa diunduh sebagai draf untuk diperiksa.
    </p>

    <ul class="max-h-[45dvh] divide-y overflow-y-auto rounded-2xl border">
        {#each diperiksa as k (k.id)}
            <li>
                <Link
                    href={KegiatanController.show(k.id)}
                    class="flex min-h-14 items-center gap-2 py-2 pr-2.5 pl-3.5 text-sm"
                >
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold">{k.nama}</span>
                        <span class="block text-[13px] text-belum-foreground">
                            {k.perluDiperiksa}
                        </span>
                    </span>
                    <ChevronRight class="size-5 shrink-0 text-muted-foreground" />
                </Link>
            </li>
        {/each}
        {#each menunggak as k (k.id)}
            <li>
                <Link
                    href={KegiatanController.show(k.id)}
                    class="flex min-h-14 items-center gap-2 py-2 pr-2.5 pl-3.5 text-sm"
                >
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold">{k.nama}</span>
                        <span class="block text-[13px] text-belum-foreground">
                            Kurang: {k.kurang.join(', ')}
                        </span>
                    </span>
                    <ChevronRight class="size-5 shrink-0 text-muted-foreground" />
                </Link>
            </li>
        {/each}
    </ul>

    <div class="grid gap-2 md:flex md:justify-end">
        <Button class="max-md:order-first max-md:h-14" onclick={unduh}>
            <Download class="size-5 md:size-4" />
            Unduh sebagai draf
        </Button>
        <Button
            variant="outline"
            class="md:order-first"
            onclick={() => (peringatanTerbuka = false)}
        >
            Lengkapi dulu
        </Button>
    </div>
</LembarBawah>
