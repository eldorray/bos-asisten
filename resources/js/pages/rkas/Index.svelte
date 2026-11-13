<script module lang="ts">
    import KertasKerjaController from '@/actions/App/Http/Controllers/Rkas/KertasKerjaController';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Draf RKAS',
                href: KertasKerjaController.index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Plus from '@lucide/svelte/icons/plus';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import PilihCari from '@/components/PilihCari.svelte';
    import FormKertasKerja from '@/components/rkas/FormKertasKerja.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type Sekolah = { id: number; uuid: string; nama: string };
    type Baris = {
        id: number;
        uuid: string;
        label: string;
        sumber_dana: string;
        versi: string;
        status: string;
        status_label: string;
        pagu: number;
        dianggarkan: number;
        sisa: number;
        bisa_diubah: boolean;
    };

    let {
        tahun,
        sekolah,
        pilihanSekolah,
        pilihanSumberDana,
        daftar,
    }: {
        tahun: number;
        sekolah: Sekolah;
        pilihanSekolah: Sekolah[];
        pilihanSumberDana: { nilai: string; nama: string }[];
        daftar: Baris[];
    } = $props();

    let dialogBuka = $state(false);
    let akanDihapus = $state<Baris | null>(null);
    let menghapus = $state(false);

    const namaSumber = $derived(
        Object.fromEntries(pilihanSumberDana.map((s) => [s.nilai, s.nama])),
    );
    const errors = $derived(page.props.errors as Record<string, string>);

    function rupiah(n: number): string {
        return `Rp ${n.toLocaleString('id-ID')}`;
    }

    function bukaHalaman(
        ubah: Partial<{ tahun: number; sekolah: string }>,
    ): void {
        router.get(
            KertasKerjaController.index({
                query: { tahun, sekolah: sekolah.uuid, ...ubah },
            }).url,
        );
    }

    function hapus(): void {
        if (!akanDihapus) {
            return;
        }
        router.delete(KertasKerjaController.destroy(akanDihapus.uuid).url, {
            preserveScroll: true,
            onStart: () => (menghapus = true),
            // Ditolak server pun dialog ditutup; alasannya tampil di atas tabel.
            onFinish: () => {
                menghapus = false;
                akanDihapus = null;
            },
        });
    }
</script>

<AppHead title="Draf RKAS" />

<div class="flex flex-col gap-5 p-5 md:p-7">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <Heading
            title="Draf RKAS"
            description="Susun kertas kerja anggaran sekolah sebelum dimasukkan ke ARKAS."
        />

        <div class="flex flex-wrap items-center gap-2.5">
            {#if pilihanSekolah.length > 1}
                <Label for="sekolah" class="shrink-0">Sekolah</Label>
                <PilihCari
                    id="sekolah"
                    class="w-56"
                    opsi={pilihanSekolah.map((p) => ({ value: p.uuid, label: p.nama }))}
                    value={sekolah.uuid}
                    onchange={(v) => bukaHalaman({ sekolah: String(v) })}
                />
            {/if}
            <Label for="tahun" class="shrink-0">Tahun anggaran</Label>
            <Input
                id="tahun"
                class="w-24"
                type="number"
                min="2000"
                max="2100"
                value={tahun}
                onchange={(e) =>
                    bukaHalaman({
                        tahun: Number((e.target as HTMLInputElement).value),
                    })}
            />
            <Button onclick={() => (dialogBuka = true)}>
                <Plus class="size-4" /> Kertas kerja baru
            </Button>
        </div>
    </div>

    <InputError message={errors.hapus} />

    <Card>
        <CardContent>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-muted-foreground">
                            <th class="px-2 py-2 font-medium">Sumber dana</th>
                            <th class="px-2 py-2 font-medium">Versi</th>
                            <th class="px-2 py-2 font-medium">Status</th>
                            <th class="px-2 py-2 text-right font-medium">Pagu</th>
                            <th class="px-2 py-2 text-right font-medium">
                                Dianggarkan
                            </th>
                            <th class="px-2 py-2 text-right font-medium">Sisa</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each daftar as baris (baris.id)}
                            <tr class="border-b last:border-0">
                                <td class="px-2 py-2">
                                    <Link
                                        href={KertasKerjaController.show(baris.uuid)}
                                        class="font-medium underline-offset-4 hover:underline"
                                    >
                                        {namaSumber[baris.sumber_dana] ??
                                            baris.sumber_dana}
                                    </Link>
                                    <span class="block text-xs text-muted-foreground">
                                        {baris.label}
                                    </span>
                                </td>
                                <td class="px-2 py-2">{baris.versi}</td>
                                <td class="px-2 py-2">
                                    <Badge
                                        variant={baris.status === 'disahkan'
                                            ? 'default'
                                            : 'secondary'}
                                    >
                                        {baris.status_label}
                                    </Badge>
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {rupiah(baris.pagu)}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {rupiah(baris.dianggarkan)}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {rupiah(baris.sisa)}
                                </td>
                                <td class="px-2 py-2">
                                    <div class="flex justify-end">
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                        >
                                            {#snippet children(props)}
                                                <Link
                                                    {...props}
                                                    href={KertasKerjaController.show(
                                                        baris.uuid,
                                                    )}
                                                    aria-label={baris.bisa_diubah
                                                        ? 'Buka dan ubah'
                                                        : 'Buka'}
                                                    title={baris.bisa_diubah
                                                        ? 'Buka dan ubah'
                                                        : 'Buka'}
                                                >
                                                    <Pencil class="size-4" />
                                                </Link>
                                            {/snippet}
                                        </Button>
                                        {#if baris.bisa_diubah}
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="text-belum-foreground"
                                                aria-label="Hapus"
                                                title="Hapus"
                                                onclick={() =>
                                                    (akanDihapus = baris)}
                                            >
                                                <Trash2 class="size-4" />
                                            </Button>
                                        {/if}
                                    </div>
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td
                                    class="px-2 py-6 text-center text-muted-foreground"
                                    colspan="7"
                                >
                                    Belum ada kertas kerja untuk {sekolah.nama}
                                    tahun {tahun}.
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</div>

<Dialog bind:open={dialogBuka}>
    <DialogContent>
        <DialogTitle>Kertas kerja baru</DialogTitle>
        <DialogDescription class="mb-4">
            {sekolah.nama}, tahun anggaran {tahun}.
        </DialogDescription>
        {#key `${sekolah.id}-${tahun}-${dialogBuka}`}
            <FormKertasKerja
                sekolahId={sekolah.id}
                {tahun}
                {pilihanSumberDana}
                onSelesai={() => (dialogBuka = false)}
            />
        {/key}
    </DialogContent>
</Dialog>

<Dialog
    open={akanDihapus !== null}
    onOpenChange={(buka) => {
        if (!buka) {
            akanDihapus = null;
        }
    }}
>
    <DialogContent class="max-md:max-w-[calc(100%-2rem)]">
        {#if akanDihapus}
            <div class="grid gap-4">
                <DialogTitle>Hapus kertas kerja ini?</DialogTitle>
                <DialogDescription>
                    {namaSumber[akanDihapus.sumber_dana] ??
                        akanDihapus.sumber_dana}
                    {tahun}, versi {akanDihapus.versi}. Seluruh belanja di dalamnya
                    ({rupiah(akanDihapus.dianggarkan)}) ikut terhapus dan tidak bisa
                    dikembalikan.
                </DialogDescription>
                <DialogFooter>
                    <Button
                        variant="secondary"
                        onclick={() => (akanDihapus = null)}
                    >
                        Batal
                    </Button>
                    <Button
                        variant="destructive"
                        disabled={menghapus}
                        onclick={hapus}
                    >
                        Hapus
                    </Button>
                </DialogFooter>
            </div>
        {/if}
    </DialogContent>
</Dialog>
