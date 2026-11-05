<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Alert, AlertDescription } from '@/components/ui/alert';
    import { Button } from '@/components/ui/button';
    import { Link } from '@inertiajs/svelte';
    import ImporController from '@/actions/App/Http/Controllers/ImporController';
    import KegiatanController from '@/actions/App/Http/Controllers/KegiatanController';
    import BarisKegiatan from '@/components/spj/BarisKegiatan.svelte';
    import KotakBelumBerbulan from '@/components/spj/KotakBelumBerbulan.svelte';
    import SaranAcara from '@/components/spj/SaranAcara.svelte';
    import PemilihBulan from '@/components/spj/PemilihBulan.svelte';
    import UnduhPdf from '@/components/spj/UnduhPdf.svelte';
    import { namaBulan, rupiah } from '@/types';
    import type { SekolahBulan } from '@/types';

    let {
        bulan,
        ringkasan,
        sekolah,
        menunggakPerBulan,
    }: {
        bulan: string;
        ringkasan: { selesai: number; kurang: number; belum: number };
        sekolah: SekolahBulan[];
        menunggakPerBulan: Record<string, number>;
    } = $props();

    let sedangMenandai = $state<number | null>(null);
    let terpilih = $state<Record<number, boolean>>({});

    const judul = $derived(
        `${namaBulan[Number(bulan.slice(5, 7)) - 1]} ${bulan.slice(0, 4)}`,
    );

    const jumlahTerpilih = $derived(
        Object.values(terpilih).filter(Boolean).length,
    );

    function mulaiTandai(s: SekolahBulan): void {
        sedangMenandai = s.id;
        terpilih = Object.fromEntries(
            s.kegiatan
                .filter((k) => k.status === 'kurang' || k.status === 'belum')
                .map((k) => [k.id, true]),
        );
    }

    function batalTandai(): void {
        sedangMenandai = null;
        terpilih = {};
    }

    function terapkanTanda(): void {
        const ids = Object.entries(terpilih)
            .filter(([, pilih]) => pilih)
            .map(([id]) => Number(id));

        router.post(
            KegiatanController.tandai().url,
            { ids, status_manual: 'di_luar_aplikasi' },
            { preserveScroll: true, onSuccess: batalTandai },
        );
    }
</script>

<AppHead title="Bulan ini ada apa saja?" />

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                Bulan ini ada apa saja?
            </h1>
            <p class="text-sm text-muted-foreground">{judul}</p>
        </div>
        <PemilihBulan {bulan} {menunggakPerBulan} />
    </div>

    <div class="flex flex-wrap gap-2 text-sm font-semibold">
        <span
            class="rounded-full bg-green-100 px-3 py-1 text-green-800 dark:bg-green-950 dark:text-green-300"
        >
            {ringkasan.selesai} selesai
        </span>
        <span
            class="rounded-full bg-amber-100 px-3 py-1 text-amber-900 dark:bg-amber-950 dark:text-amber-300"
        >
            {ringkasan.kurang} kurang berkas
        </span>
        <span
            class="rounded-full bg-red-100 px-3 py-1 text-red-800 dark:bg-red-950 dark:text-red-300"
        >
            {ringkasan.belum} belum mulai
        </span>
    </div>

    {#each sekolah as s (s.id)}
        <section aria-label={s.nama}>
            <div
                class="flex flex-wrap items-center justify-between gap-2 border-b-2 border-foreground py-2"
            >
                <h2 class="font-bold">
                    {s.nama}
                    <small class="font-medium text-muted-foreground">
                        · {s.sistem.toUpperCase()}
                    </small>
                </h2>
                <div class="flex flex-wrap items-center gap-2">
                    <small class="text-muted-foreground">
                        Rp {rupiah(s.total)}
                    </small>
                    {#if sedangMenandai === s.id}
                        <Button
                            size="sm"
                            disabled={jumlahTerpilih === 0}
                            onclick={terapkanTanda}
                        >
                            Tandai {jumlahTerpilih} kegiatan
                        </Button>
                        <Button size="sm" variant="outline" onclick={batalTandai}>
                            Batal
                        </Button>
                    {:else}
                        <UnduhPdf sekolah={s} {bulan} />
                        {#if s.kegiatan.length > 0}
                            <Button
                                size="sm"
                                variant="outline"
                                onclick={() => mulaiTandai(s)}
                            >
                                Tandai sudah di luar aplikasi
                            </Button>
                        {/if}
                    {/if}
                </div>
            </div>

            {#if s.peringatan}
                <Alert variant="destructive" class="mt-3">
                    <AlertDescription>
                        {s.peringatan}.
                        <Link
                            href={ImporController.index()}
                            class="font-semibold underline"
                        >
                            Unggah sekarang
                        </Link>
                    </AlertDescription>
                </Alert>
            {/if}

            {#if sedangMenandai === s.id}
                <p class="py-2 text-sm text-muted-foreground">
                    Hapus centang pada kegiatan yang tidak ingin ditandai.
                </p>
            {/if}

            {#each s.kegiatan as k (k.id)}
                <BarisKegiatan
                    kegiatan={k}
                    pilihan={sedangMenandai === s.id ? 'tandai' : null}
                    bind:dipilih={terpilih[k.id]}
                />
            {:else}
                {#if !s.peringatan}
                    <p class="py-3 text-sm text-muted-foreground">
                        Tidak ada kegiatan di bulan ini.
                    </p>
                {/if}
            {/each}

            {#each s.saranAcara as saran (saran.ids.join('-'))}
                <SaranAcara {saran} />
            {/each}

            <KotakBelumBerbulan
                sekolah={s.nama}
                belumBerbulan={s.belumBerbulan}
            />
        </section>
    {/each}
</div>
