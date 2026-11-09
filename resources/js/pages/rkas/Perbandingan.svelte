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
    import { Link, router } from '@inertiajs/svelte';
    import PerbandinganController from '@/actions/App/Http/Controllers/Rkas/PerbandinganController';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Card, CardContent } from '@/components/ui/card';
    import { namaBulan } from '@/types';

    type Perbandingan = {
        sudahDiimpor: boolean;
        totalDraf: number;
        totalImpor: number;
        hanyaDiDraf: { kode_rekening: string; uraian: string; jumlah: number }[];
        hanyaDiImpor: {
            kode: string;
            uraian: string;
            jumlah: number;
            kode_program: string | null;
        }[];
        berbeda: {
            kode_rekening: string;
            uraian: string;
            draf: number;
            impor: number;
        }[];
    };

    let {
        kertasKerja,
        pembanding,
        bulan,
        perbandingan,
    }: {
        kertasKerja: {
            id: number;
            uuid: string;
            tahun: number;
            versi: string;
            sekolah: string;
        };
        pembanding: { id: number; versi: string; status_label: string } | null;
        bulan: number;
        perbandingan: Perbandingan | null;
    } = $props();

    function rupiah(n: number): string {
        return `Rp ${n.toLocaleString('id-ID')}`;
    }

    function pilihBulan(nilai: number): void {
        router.get(
            PerbandinganController.url(kertasKerja.uuid, {
                query: { bulan: nilai },
            }),
        );
    }

    const sama = $derived(
        perbandingan !== null &&
            perbandingan.sudahDiimpor &&
            perbandingan.hanyaDiDraf.length === 0 &&
            perbandingan.hanyaDiImpor.length === 0 &&
            perbandingan.berbeda.length === 0,
    );
</script>

<AppHead title={`Perbandingan impor ${kertasKerja.tahun}`} />

<div class="flex flex-col gap-5 p-5 md:p-7">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <Heading
            title="Perbandingan dengan impor ARKAS"
            description={`${kertasKerja.sekolah} · ${kertasKerja.tahun}${pembanding ? ` · pembanding: ${pembanding.versi} (${pembanding.status_label})` : ''}`}
        />
        <div class="flex flex-wrap items-center gap-3">
            <select
                class="h-11 rounded-lg border border-input bg-card px-3 text-sm"
                aria-label="Pilih bulan"
                value={bulan}
                onchange={(e) => pilihBulan(Number(e.currentTarget.value))}
            >
                {#each namaBulan as nama, i (nama)}
                    <option value={i + 1}>{nama}</option>
                {/each}
            </select>
            <Link
                href={KertasKerjaController.show(kertasKerja.uuid)}
                class="text-sm underline-offset-4 hover:underline"
            >
                Kembali ke kertas kerja
            </Link>
        </div>
    </div>

    {#if perbandingan === null}
        <Card>
            <CardContent class="p-5 text-sm">
                Perbandingan hanya tersedia untuk sumber dana BOSP Reguler,
                karena impor ARKAS hanya menerima BOSP Reguler.
            </CardContent>
        </Card>
    {:else if !perbandingan.sudahDiimpor}
        <Card>
            <CardContent class="p-5 text-sm">
                Belum diimpor. Anggaran {namaBulan[bulan - 1]}
                {kertasKerja.tahun} belum diimpor dari PDF ARKAS.
            </CardContent>
        </Card>
    {:else}
        <div class="grid gap-3 sm:grid-cols-2">
            <Card>
                <CardContent class="p-5">
                    <p class="text-sm text-muted-foreground">Total draf</p>
                    <p class="text-lg font-semibold tabular-nums">
                        {rupiah(perbandingan.totalDraf)}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-5">
                    <p class="text-sm text-muted-foreground">Total impor</p>
                    <p class="text-lg font-semibold tabular-nums">
                        {rupiah(perbandingan.totalImpor)}
                    </p>
                </CardContent>
            </Card>
        </div>

        {#if sama}
            <Card>
                <CardContent class="p-5 text-sm">
                    Semua baris cocok dengan hasil impor.
                </CardContent>
            </Card>
        {/if}

        {#if perbandingan.berbeda.length > 0}
            <section class="flex flex-col gap-2">
                <h2 class="font-semibold">
                    Nominal berbeda ({perbandingan.berbeda.length})
                </h2>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="p-2.5">Rekening</th>
                                <th class="p-2.5">Uraian</th>
                                <th class="p-2.5 text-right">Draf</th>
                                <th class="p-2.5 text-right">Impor</th>
                                <th class="p-2.5 text-right">Selisih</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each perbandingan.berbeda as b (b.kode_rekening + b.uraian)}
                                <tr class="border-t">
                                    <td class="p-2.5">{b.kode_rekening}</td>
                                    <td class="p-2.5">{b.uraian}</td>
                                    <td class="p-2.5 text-right tabular-nums">{rupiah(b.draf)}</td>
                                    <td class="p-2.5 text-right tabular-nums">{rupiah(b.impor)}</td>
                                    <td class="p-2.5 text-right tabular-nums">{rupiah(b.draf - b.impor)}</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </section>
        {/if}

        {#if perbandingan.hanyaDiDraf.length > 0}
            <section class="flex flex-col gap-2">
                <h2 class="font-semibold">
                    Hanya di draf ({perbandingan.hanyaDiDraf.length})
                </h2>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="p-2.5">Rekening</th>
                                <th class="p-2.5">Uraian</th>
                                <th class="p-2.5 text-right">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each perbandingan.hanyaDiDraf as b (b.kode_rekening + b.uraian)}
                                <tr class="border-t">
                                    <td class="p-2.5">{b.kode_rekening}</td>
                                    <td class="p-2.5">{b.uraian}</td>
                                    <td class="p-2.5 text-right tabular-nums">{rupiah(b.jumlah)}</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </section>
        {/if}

        {#if perbandingan.hanyaDiImpor.length > 0}
            <section class="flex flex-col gap-2">
                <h2 class="font-semibold">
                    Hanya di impor ({perbandingan.hanyaDiImpor.length})
                </h2>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="p-2.5">Rekening</th>
                                <th class="p-2.5">Kode program PDF</th>
                                <th class="p-2.5">Uraian</th>
                                <th class="p-2.5 text-right">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each perbandingan.hanyaDiImpor as b (b.kode + b.uraian)}
                                <tr class="border-t">
                                    <td class="p-2.5">{b.kode}</td>
                                    <td class="p-2.5">{b.kode_program ?? '-'}</td>
                                    <td class="p-2.5">{b.uraian}</td>
                                    <td class="p-2.5 text-right tabular-nums">{rupiah(b.jumlah)}</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </section>
        {/if}
    {/if}
</div>
