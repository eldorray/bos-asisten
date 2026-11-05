<script lang="ts">
    import Download from '@lucide/svelte/icons/download';
    import PdfBulananController from '@/actions/App/Http/Controllers/PdfBulananController';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import type { SekolahBulan } from '@/types';

    let { sekolah, bulan }: { sekolah: SekolahBulan; bulan: string } = $props();

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

    function unduh(): void {
        peringatanTerbuka = false;

        // Bukan kunjungan Inertia: hasilnya file, atau redirect biasa kalau bulan ini kosong.
        window.location.href = PdfBulananController.url({
            sekolah: sekolah.id,
            tahun: Number(bulan.slice(0, 4)),
            bulan: Number(bulan.slice(5, 7)),
        });
    }

    function tekanUnduh(): void {
        if (menunggak.length > 0) {
            peringatanTerbuka = true;
        } else {
            unduh();
        }
    }
</script>

<Button onclick={tekanUnduh}>
    <Download class="size-4" />
    Unduh PDF
</Button>

<Dialog bind:open={peringatanTerbuka}>
    <DialogContent>
        <div class="space-y-4">
            <div class="space-y-2">
                <DialogTitle>Berkas {sekolah.nama} belum lengkap</DialogTitle>
                <DialogDescription>
                    {menunggak.length} kegiatan masih kurang berkas. PDF tetap
                    bisa diunduh sebagai draf untuk diperiksa.
                </DialogDescription>
            </div>

            <ul class="max-h-64 space-y-2 overflow-y-auto text-sm">
                {#each menunggak as k (k.id)}
                    <li>
                        <span class="font-medium">{k.nama}</span>
                        <span class="block text-muted-foreground">
                            Kurang: {k.kurang.join(', ')}
                        </span>
                    </li>
                {/each}
            </ul>

            <DialogFooter>
                <Button
                    variant="outline"
                    onclick={() => (peringatanTerbuka = false)}
                >
                    Batal
                </Button>
                <Button onclick={unduh}>Unduh sebagai draf</Button>
            </DialogFooter>
        </div>
    </DialogContent>
</Dialog>
