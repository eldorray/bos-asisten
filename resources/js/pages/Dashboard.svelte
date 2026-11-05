<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Bulanan',
                href: dashboard(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import CircleCheck from '@lucide/svelte/icons/circle-check';
    import CircleDashed from '@lucide/svelte/icons/circle-dashed';
    import CircleX from '@lucide/svelte/icons/circle-x';
    import List from '@lucide/svelte/icons/list';
    import SquareKanban from '@lucide/svelte/icons/square-kanban';
    import TriangleAlert from '@lucide/svelte/icons/triangle-alert';
    import Wallet from '@lucide/svelte/icons/wallet';
    import ImporController from '@/actions/App/Http/Controllers/ImporController';
    import KegiatanController from '@/actions/App/Http/Controllers/KegiatanController';
    import AppHead from '@/components/AppHead.svelte';
    import BarisKegiatan from '@/components/spj/BarisKegiatan.svelte';
    import KartuKegiatan from '@/components/spj/KartuKegiatan.svelte';
    import KotakBelumBerbulan from '@/components/spj/KotakBelumBerbulan.svelte';
    import PemilihBulan from '@/components/spj/PemilihBulan.svelte';
    import PilihanSegmen from '@/components/spj/PilihanSegmen.svelte';
    import SaranAcara from '@/components/spj/SaranAcara.svelte';
    import UnduhPdf from '@/components/spj/UnduhPdf.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { namaBulan, rupiah } from '@/types';
    import type { BarisKegiatanData, SekolahBulan, StatusKode } from '@/types';

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

    const simpanan = (kunci: string): string | null =>
        typeof localStorage === 'undefined' ? null : localStorage.getItem(kunci);

    // Pilihan tab bertahan saat kembali dari rincian atau pindah bulan.
    let sekolahId = $state(Number(simpanan('spj.sekolah')));
    let tampilan = $state<'papan' | 'daftar'>(
        simpanan('spj.tampilan') === 'daftar' ? 'daftar' : 'papan',
    );
    let cari = $state('');
    let sedangMenandai = $state<number | null>(null);
    let terpilih = $state<Record<number, boolean>>({});

    $effect(() => {
        localStorage.setItem('spj.sekolah', String(sekolahId));
        localStorage.setItem('spj.tampilan', tampilan);
    });

    const judul = $derived(
        `${namaBulan[Number(bulan.slice(5, 7)) - 1]} ${bulan.slice(0, 4)}`,
    );

    const aktif = $derived(
        sekolah.find((s) => s.id === sekolahId) ?? sekolah[0],
    );

    const menandai = $derived(sedangMenandai === aktif?.id);

    const jumlahTerpilih = $derived(
        Object.values(terpilih).filter(Boolean).length,
    );

    const baris = $derived(
        (aktif?.kegiatan ?? []).filter((k) =>
            k.nama.toLowerCase().includes(cari.trim().toLowerCase()),
        ),
    );

    const hitung = (daftar: BarisKegiatanData[], ...status: StatusKode[]) =>
        daftar.filter((k) => status.includes(k.status));

    const kolom = $derived([
        {
            label: 'Belum mulai',
            ikon: CircleDashed,
            kelas: 'bg-belum text-belum-foreground',
            baris: hitung(baris, 'belum'),
        },
        {
            label: 'Kurang berkas',
            ikon: TriangleAlert,
            kelas: 'bg-kurang text-kurang-foreground',
            baris: hitung(baris, 'kurang'),
        },
        {
            label: 'Selesai',
            ikon: CircleCheck,
            kelas: 'bg-selesai text-selesai-foreground',
            baris: hitung(baris, 'selesai', 'dirapel', 'di_luar_aplikasi'),
        },
        {
            label: 'Tidak dilaksanakan',
            ikon: CircleX,
            kelas: 'bg-batal text-batal-foreground',
            baris: hitung(baris, 'tidak_dilaksanakan'),
        },
    ]);

    const statistik = $derived.by(() => {
        const semua = aktif?.kegiatan ?? [];

        return [
            {
                ikon: CircleCheck,
                nilai: hitung(semua, 'selesai', 'dirapel', 'di_luar_aplikasi')
                    .length,
                label: 'Selesai',
                catatan: `dari ${semua.length} kegiatan`,
            },
            {
                ikon: TriangleAlert,
                nilai: hitung(semua, 'kurang').length,
                label: 'Kurang berkas',
                catatan: 'perlu dilengkapi',
            },
            {
                ikon: CircleDashed,
                nilai: hitung(semua, 'belum').length,
                label: 'Belum mulai',
                catatan: 'belum ada berkas',
            },
            {
                ikon: Wallet,
                nilai: `Rp ${rupiah(aktif?.total ?? 0)}`,
                label: 'Total anggaran bulan ini',
                catatan: aktif?.sistem.toUpperCase() ?? '',
            },
        ];
    });

    function mulaiTandai(s: SekolahBulan): void {
        // Baris yang tersaring tetap ikut tercentang, jadi saringan dibuka dulu.
        cari = '';
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

<div class="flex flex-col gap-5 p-5 md:p-7">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1.5">
            <h1 class="text-[28px] leading-tight font-semibold tracking-tight">
                Bulan ini ada apa saja?
            </h1>
            <p class="text-[15px] text-muted-foreground">
                {judul} · semua sekolah: {ringkasan.selesai} selesai,
                {ringkasan.kurang} kurang berkas, {ringkasan.belum} belum mulai
            </p>
        </div>
        <PemilihBulan {bulan} {menunggakPerBulan} />
    </div>

    {#if aktif}
        {#if aktif.peringatan}
            <div
                role="alert"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-belum py-2 pr-2 pl-3 text-sm text-belum-foreground"
            >
                <div class="flex min-w-60 flex-1 items-center gap-3">
                    <TriangleAlert class="size-5 shrink-0" />
                    <p>{aktif.nama}: {aktif.peringatan}.</p>
                </div>
                <Button variant="outline" asChild>
                    {#snippet children(props)}
                        <Link
                            href={ImporController.index()}
                            class={`${props.class} text-foreground`}
                        >
                            Unggah sekarang
                        </Link>
                    {/snippet}
                </Button>
            </div>
        {/if}

        <section
            aria-label="Ringkasan sekolah"
            class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-3.5"
        >
            {#each statistik as s (s.label)}
                <div
                    class="grid content-start gap-3.5 rounded-[14px] border bg-card p-3.5 sm:p-4"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span
                            class="grid size-9 shrink-0 place-items-center rounded-[10px] bg-primary text-primary-foreground"
                        >
                            <s.ikon class="size-[18px]" />
                        </span>
                        <span
                            class="hidden text-right text-[13px] text-muted-foreground sm:block"
                        >
                            {s.catatan}
                        </span>
                    </div>
                    <div>
                        <div
                            class="text-lg font-semibold tracking-tight [overflow-wrap:anywhere] tabular-nums sm:text-[22px]"
                        >
                            {s.nilai}
                        </div>
                        <div class="text-sm text-muted-foreground">
                            {s.label}
                        </div>
                    </div>
                </div>
            {/each}
        </section>

        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b pb-4"
        >
            <div class="flex max-w-full flex-wrap items-center gap-3">
                <PilihanSegmen
                    label="Sekolah"
                    nilai={aktif.id}
                    pilihan={sekolah.map((s) => ({
                        nilai: s.id,
                        label: s.nama,
                        peringatan: s.peringatan,
                        lencana: hitung(s.kegiatan, 'kurang', 'belum').length,
                    }))}
                    onpilih={(id) => (sekolahId = id)}
                />
                <PilihanSegmen
                    label="Tampilan"
                    nilai={tampilan}
                    pilihan={[
                        {
                            nilai: 'papan' as const,
                            label: 'Papan',
                            ikon: SquareKanban,
                        },
                        { nilai: 'daftar' as const, label: 'Daftar', ikon: List },
                    ]}
                    onpilih={(t) => (tampilan = t)}
                />
                <Input
                    type="search"
                    class="w-40"
                    aria-label="Cari kegiatan"
                    placeholder="Cari kegiatan"
                    disabled={menandai}
                    bind:value={cari}
                />
            </div>

            {#if menandai}
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm text-muted-foreground">
                        Hapus centang yang tidak ingin ditandai.
                    </span>
                    <Button variant="outline" onclick={batalTandai}>Batal</Button>
                    <Button
                        disabled={jumlahTerpilih === 0}
                        onclick={terapkanTanda}
                    >
                        Tandai {jumlahTerpilih} kegiatan
                    </Button>
                </div>
            {:else}
                <div class="flex flex-wrap items-center gap-2">
                    {#if aktif.kegiatan.length > 0}
                        <Button
                            variant="outline"
                            onclick={() => mulaiTandai(aktif)}
                        >
                            Tandai di luar aplikasi
                        </Button>
                    {/if}
                    <UnduhPdf sekolah={aktif} {bulan} />
                </div>
            {/if}
        </div>

        {#if tampilan === 'papan'}
            <div class="grid items-start gap-3.5 md:grid-cols-2 xl:grid-cols-4">
                {#each kolom as k (k.label)}
                    <section
                        aria-label={k.label}
                        class="grid grid-cols-1 gap-3"
                    >
                        <div>
                            <span
                                class={`inline-flex items-center gap-2 rounded-full py-1.5 pr-2 pl-3 text-sm font-medium ${k.kelas}`}
                            >
                                <k.ikon class="size-4" />
                                {k.label}
                                <span
                                    class="min-w-[22px] rounded-full bg-card px-1.5 text-center text-xs font-semibold text-foreground tabular-nums"
                                >
                                    {k.baris.length}
                                </span>
                            </span>
                        </div>
                        {#each k.baris as item (item.id)}
                            <KartuKegiatan
                                kegiatan={item}
                                {menandai}
                                bind:dipilih={terpilih[item.id]}
                            />
                        {:else}
                            <p
                                class="rounded-xl border border-dashed border-input px-3 py-5 text-center text-sm text-muted-foreground"
                            >
                                Tidak ada kegiatan.
                            </p>
                        {/each}
                    </section>
                {/each}
            </div>
        {:else}
            <div class="overflow-hidden rounded-[14px] border bg-card">
                <div
                    class="hidden gap-4 border-b px-4 py-3 text-[13px] font-medium text-muted-foreground lg:grid lg:grid-cols-[minmax(0,2fr)_110px_minmax(0,2fr)_160px]"
                >
                    <span>Kegiatan</span>
                    <span class="text-right">Jumlah</span>
                    <span>Berkas</span>
                    <span>Status</span>
                </div>
                {#each baris as item (item.id)}
                    <BarisKegiatan
                        kegiatan={item}
                        {menandai}
                        bind:dipilih={terpilih[item.id]}
                    />
                {:else}
                    <p class="px-4 py-7 text-center text-sm text-muted-foreground">
                        Tidak ada kegiatan.
                    </p>
                {/each}
            </div>
        {/if}

        {#if aktif.saranAcara.length > 0 || aktif.belumBerbulan.length > 0}
            <div class="grid items-start gap-3.5 lg:grid-cols-2">
                {#each aktif.saranAcara as saran (saran.ids.join('-'))}
                    <SaranAcara {saran} />
                {/each}
                <KotakBelumBerbulan
                    sekolah={aktif.nama}
                    belumBerbulan={aktif.belumBerbulan}
                />
            </div>
        {/if}
    {:else}
        <p class="py-7 text-center text-sm text-muted-foreground">
            Belum ada sekolah.
        </p>
    {/if}
</div>
