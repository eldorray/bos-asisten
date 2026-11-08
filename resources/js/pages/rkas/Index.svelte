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
    import Plus from '@lucide/svelte/icons/plus';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import FormKertasKerja from '@/components/rkas/FormKertasKerja.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type Sekolah = { id: number; nama: string };
    type Baris = {
        id: number;
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

    const namaSumber = $derived(
        Object.fromEntries(pilihanSumberDana.map((s) => [s.nilai, s.nama])),
    );
    const errors = $derived(page.props.errors as Record<string, string>);

    function rupiah(n: number): string {
        return `Rp ${n.toLocaleString('id-ID')}`;
    }

    function bukaHalaman(
        ubah: Partial<{ tahun: number; sekolah_id: number }>,
    ): void {
        router.get(
            KertasKerjaController.index({
                query: { tahun, sekolah_id: sekolah.id, ...ubah },
            }).url,
        );
    }

    function hapus(id: number): void {
        if (!confirm('Hapus kertas kerja ini beserta seluruh barisnya?')) {
            return;
        }
        router.delete(KertasKerjaController.destroy(id).url, {
            preserveScroll: true,
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
                <select
                    id="sekolah"
                    class="h-9 rounded-md border bg-background px-2 text-sm"
                    value={sekolah.id}
                    onchange={(e) =>
                        bukaHalaman({
                            sekolah_id: Number(
                                (e.target as HTMLSelectElement).value,
                            ),
                        })}
                >
                    {#each pilihanSekolah as pilihan (pilihan.id)}
                        <option value={pilihan.id}>{pilihan.nama}</option>
                    {/each}
                </select>
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
                            <th class="w-10 px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each daftar as baris (baris.id)}
                            <tr class="border-b last:border-0">
                                <td class="px-2 py-2">
                                    <Link
                                        href={KertasKerjaController.show(baris.id)}
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
                                <td class="px-2 py-2 text-right">
                                    {#if baris.bisa_diubah}
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Hapus"
                                            onclick={() => hapus(baris.id)}
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    {/if}
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
