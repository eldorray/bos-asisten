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
    import { tick } from 'svelte';
    import Calendar from '@lucide/svelte/icons/calendar';
    import { Link, router } from '@inertiajs/svelte';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import CircleAlert from '@lucide/svelte/icons/circle-alert';
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
    import BarisKegiatanHp from '@/components/spj/BarisKegiatanHp.svelte';
    import LembarBawah from '@/components/spj/LembarBawah.svelte';
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
        pdfBelumPerBulan,
    }: {
        bulan: string;
        ringkasan: { selesai: number; kurang: number; belum: number };
        sekolah: SekolahBulan[];
        menunggakPerBulan: Record<string, number>;
        pdfBelumPerBulan: Record<string, boolean>;
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

    // Tanpa PDF, papan kosong akan terbaca seolah bulan ini tidak ada kegiatan.
    const tanpaData = $derived(
        aktif?.peringatan !== null && aktif?.kegiatan.length === 0,
    );

    const perluDiperiksa = (s: SekolahBulan): BarisKegiatanData[] =>
        s.kegiatan.filter((k) => k.perluDiperiksa);

    let periksaId = $state<number | null>(null);

    const jumlahBelumBerbulan = (s: SekolahBulan): number =>
        s.belumBerbulan.reduce((n, k) => n + k.kegiatan.length, 0);

    async function lihatBelumBerbulan(id: number): Promise<void> {
        sekolahId = id;
        await tick();
        document
            .getElementById('belum-berbulan')
            ?.scrollIntoView({ behavior: 'smooth' });
    }

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

    const pilihanSekolah = $derived(
        sekolah.map((s) => ({
            nilai: s.id,
            label: s.nama,
            peringatan: s.peringatan,
            lencana:
                hitung(s.kegiatan, 'kurang', 'belum').length +
                jumlahBelumBerbulan(s),
        })),
    );

    // Tampilan HP: satu daftar, yang paling sedikit kekurangannya di atas.
    type SaringanHp = 'tugas' | 'beres' | 'batal';
    const saringHp: { kunci: SaringanHp; label: string }[] = [
        { kunci: 'tugas', label: 'Perlu dikerjakan' },
        { kunci: 'beres', label: 'Selesai' },
        { kunci: 'batal', label: 'Batal' },
    ];
    let saringanHp = $state<SaringanHp>('tugas');
    let keputusanTerbuka = $state(false);

    const kelompokHp = $derived.by(() => {
        const semua = aktif?.kegiatan ?? [];

        return {
            tugas: [
                ...hitung(semua, 'kurang').sort((a, b) => a.kurang - b.kurang),
                ...hitung(semua, 'belum'),
            ],
            beres: hitung(semua, 'selesai', 'dirapel', 'di_luar_aplikasi'),
            batal: hitung(semua, 'tidak_dilaksanakan'),
        };
    });

    const persenSelesai = $derived(
        aktif && aktif.kegiatan.length > 0
            ? Math.round((kelompokHp.beres.length / aktif.kegiatan.length) * 100)
            : 0,
    );

    const jumlahKeputusan = $derived(
        aktif ? aktif.saranAcara.length + jumlahBelumBerbulan(aktif) : 0,
    );

    $effect(() => {
        if (jumlahKeputusan === 0) {
            keputusanTerbuka = false;
        }
    });

    // Rincian kegiatan yang tertunda ikut tersimpan, supaya bisa dibuka saat sinyal hilang.
    const layarHp =
        typeof window !== 'undefined' &&
        window.matchMedia('(max-width: 767px)').matches;

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

<div
    class={[
        'flex flex-col gap-3.5 p-4 md:gap-5 md:p-7',
        menandai && 'max-md:pb-24',
    ]}
>
    <div class="flex flex-wrap items-center justify-between gap-2 md:items-end md:gap-4">
        <div class="space-y-1.5">
            <h1
                class="text-[26px] leading-tight font-semibold tracking-tight md:text-[28px]"
            >
                <span class="md:hidden">Bulan ini</span>
                <span class="max-md:hidden">Bulan ini ada apa saja?</span>
            </h1>
            <p class="text-[15px] text-muted-foreground max-md:hidden">
                {judul} · semua sekolah: {ringkasan.selesai} selesai,
                {ringkasan.kurang} kurang berkas, {ringkasan.belum} belum mulai
            </p>
        </div>
        <PemilihBulan {bulan} {menunggakPerBulan} {pdfBelumPerBulan} />
    </div>

    {#if aktif}
        <div class="md:hidden">
            <PilihanSegmen
                label="Sekolah"
                nilai={aktif.id}
                pilihan={pilihanSekolah}
                onpilih={(id) => (sekolahId = id)}
            />
        </div>

        {#each sekolah.filter((s) => s.peringatan) as s (s.id)}
            <div
                role="alert"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-belum py-2 pr-2 pl-3 text-sm text-belum-foreground"
            >
                <div class="flex min-w-60 flex-1 items-center gap-3">
                    <TriangleAlert class="size-5 shrink-0" />
                    <p>{s.nama}: {s.peringatan}.</p>
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
        {/each}

        {#each sekolah.filter((s) => perluDiperiksa(s).length > 0) as s (s.id)}
            <div
                role="alert"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-kurang py-2 pr-2 pl-3 text-sm text-kurang-foreground"
            >
                <div class="flex min-w-60 flex-1 items-center gap-3">
                    <CircleAlert class="size-5 shrink-0" />
                    <p>
                        {s.nama}: {perluDiperiksa(s).length} kegiatan perlu
                        diperiksa.
                    </p>
                </div>
                <Button
                    variant="outline"
                    class="text-foreground"
                    onclick={() => (periksaId = s.id)}
                >
                    Lihat
                </Button>
            </div>
            <LembarBawah
                judul={`Perlu diperiksa: ${s.nama}`}
                terbuka={periksaId === s.id}
                ontutup={() => {
                    if (periksaId === s.id) {
                        periksaId = null;
                    }
                }}
            >
                <ul class="max-h-[60dvh] divide-y overflow-y-auto rounded-2xl border">
                    {#each perluDiperiksa(s) as k (k.id)}
                        <li>
                            <Link
                                href={KegiatanController.show(k.id)}
                                class="flex min-h-14 items-center gap-2 py-2 pr-2.5 pl-3.5 text-sm"
                            >
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold">
                                        {k.nama}
                                    </span>
                                    <span
                                        class="block text-[13px] text-belum-foreground"
                                    >
                                        {k.perluDiperiksa}
                                    </span>
                                </span>
                                <ChevronRight
                                    class="size-5 shrink-0 text-muted-foreground"
                                />
                            </Link>
                        </li>
                    {/each}
                </ul>
            </LembarBawah>
        {/each}

        {#each sekolah.filter((s) => jumlahBelumBerbulan(s) > 0) as s (s.id)}
            <div
                role="alert"
                class="hidden flex-wrap items-center justify-between gap-3 rounded-xl bg-kurang py-2 pr-2 pl-3 text-sm text-kurang-foreground md:flex"
            >
                <div class="flex min-w-60 flex-1 items-center gap-3">
                    <Calendar class="size-5 shrink-0" />
                    <p>
                        {s.nama}: {jumlahBelumBerbulan(s)} kegiatan belum punya
                        bulan.
                    </p>
                </div>
                <Button
                    variant="outline"
                    class="text-foreground"
                    onclick={() => lihatBelumBerbulan(s.id)}
                >
                    Pilih bulannya
                </Button>
            </div>
        {/each}

        {#if !tanpaData}
            <section
                aria-label="Kemajuan bulan ini"
                class="grid gap-3 rounded-[18px] bg-primary p-4 text-primary-foreground md:hidden"
            >
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <div
                            class="text-[28px] leading-none font-semibold tracking-tight tabular-nums"
                        >
                            {kelompokHp.beres.length} dari {aktif.kegiatan.length}
                        </div>
                        <div class="mt-1 text-sm opacity-80">kegiatan selesai</div>
                    </div>
                    <UnduhPdf
                        sekolah={aktif}
                        {bulan}
                        label="PDF"
                        class="bg-primary-foreground font-semibold text-primary hover:bg-primary-foreground/90"
                    />
                </div>
                <div
                    role="progressbar"
                    aria-label="Kemajuan"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={persenSelesai}
                    class="h-2 overflow-hidden rounded-full bg-primary-foreground/25"
                >
                    <div
                        class="h-full rounded-full bg-primary-foreground"
                        style:width={`${persenSelesai}%`}
                    ></div>
                </div>
                <div class="flex justify-between gap-2 text-[13px]">
                    <span class="opacity-80">Anggaran bulan ini</span>
                    <span class="font-semibold tabular-nums">
                        Rp {rupiah(aktif.total)}
                    </span>
                </div>
            </section>

            <section
                aria-label="Ringkasan sekolah"
                class="hidden grid-cols-2 gap-3 md:grid lg:grid-cols-4 lg:gap-3.5"
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
        {/if}

        {#if jumlahKeputusan > 0}
            <button
                type="button"
                class="flex min-h-13 items-center gap-2.5 rounded-[14px] bg-kurang px-3 py-2 text-left text-[15px] font-semibold text-kurang-foreground md:hidden"
                onclick={() => (keputusanTerbuka = true)}
            >
                <CircleAlert class="size-5 shrink-0" />
                <span class="flex-1">{jumlahKeputusan} hal perlu keputusan Anda</span>
                <ChevronRight class="size-[18px] shrink-0" />
            </button>
        {/if}

        <div
            class="hidden flex-wrap items-center justify-between gap-3 border-b pb-4 md:flex"
        >
            <div class="flex max-w-full flex-wrap items-center gap-3">
                <PilihanSegmen
                    label="Sekolah"
                    nilai={aktif.id}
                    pilihan={pilihanSekolah}
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

        {#if tanpaData}
            <p class="py-7 text-center text-sm text-muted-foreground">
                Daftar kegiatan {aktif.nama} muncul setelah PDF-nya diunggah.
            </p>
        {:else}
            <div class="grid grid-cols-1 gap-3 md:hidden">
                <div
                    role="group"
                    aria-label="Saring kegiatan"
                    class="flex gap-1.5 overflow-x-auto"
                >
                    {#each saringHp as s (s.kunci)}
                        <button
                            type="button"
                            aria-pressed={s.kunci === saringanHp}
                            class={[
                                'h-11 shrink-0 rounded-full border px-3 text-sm font-semibold whitespace-nowrap',
                                s.kunci === saringanHp
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-input bg-card',
                            ]}
                            onclick={() => (saringanHp = s.kunci)}
                        >
                            {s.label}
                            {kelompokHp[s.kunci].length}
                        </button>
                    {/each}
                </div>

                <ul class="grid grid-cols-1 gap-2.5">
                    {#each kelompokHp[saringanHp] as item (item.id)}
                        <BarisKegiatanHp
                            kegiatan={item}
                            {menandai}
                            bind:dipilih={terpilih[item.id]}
                            simpanUntukOffline={layarHp && saringanHp === 'tugas'}
                        />
                    {:else}
                        <li
                            class="rounded-xl border border-dashed border-input px-3 py-5 text-center text-sm text-muted-foreground"
                        >
                            Tidak ada kegiatan.
                        </li>
                    {/each}
                </ul>

                {#if !menandai && aktif.kegiatan.length > 0}
                    <Button variant="outline" onclick={() => mulaiTandai(aktif)}>
                        Tandai di luar aplikasi
                    </Button>
                {/if}
            </div>

            {#if menandai}
                <div
                    class="fixed inset-x-0 bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-20 flex gap-2 border-t bg-card p-3 md:hidden"
                >
                    <Button variant="outline" onclick={batalTandai}>Batal</Button>
                    <Button
                        class="flex-1"
                        disabled={jumlahTerpilih === 0}
                        onclick={terapkanTanda}
                    >
                        Tandai {jumlahTerpilih} kegiatan
                    </Button>
                </div>
            {/if}
        {/if}

        {#if !tanpaData && tampilan === 'papan'}
            <div
                class="hidden items-start gap-3.5 md:grid md:grid-cols-2 xl:grid-cols-4"
            >
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
        {:else if !tanpaData}
            <div class="hidden overflow-hidden rounded-[14px] border bg-card md:block">
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

        {#if aktif.ditunda.length > 0}
            <section
                aria-label="Kegiatan yang ditunda"
                class="grid gap-2 rounded-[14px] border bg-card p-4 text-sm"
            >
                <h2 class="text-[15px] font-semibold">
                    {aktif.ditunda.length} kegiatan bulan ini ditunda
                </h2>
                <ul class="grid gap-1">
                    {#each aktif.ditunda as k (k.id)}
                        <li>
                            <Link
                                href={KegiatanController.show(k.id)}
                                class="flex min-h-11 items-center justify-between gap-3"
                            >
                                <span class="min-w-0 [overflow-wrap:anywhere]">
                                    {k.nama}
                                </span>
                                <span class="shrink-0 text-dirapel-foreground">
                                    ke {namaBulan[(k.bulanPelaksanaan ?? 1) - 1]}
                                </span>
                            </Link>
                        </li>
                    {/each}
                </ul>
            </section>
        {/if}

        {#if jumlahKeputusan > 0}
            <LembarBawah
                judul="Perlu keputusan Anda"
                sebarisDiDesktop
                terbuka={keputusanTerbuka}
                ontutup={() => (keputusanTerbuka = false)}
            >
                <div
                    id="belum-berbulan"
                    class="grid scroll-mt-4 items-start gap-3.5 lg:grid-cols-2"
                >
                    {#each aktif.saranAcara as saran (saran.ids.join('-'))}
                        <SaranAcara {saran} />
                    {/each}
                    <KotakBelumBerbulan
                        sekolah={aktif.nama}
                        belumBerbulan={aktif.belumBerbulan}
                    />
                </div>
            </LembarBawah>
        {/if}
    {:else}
        <p class="py-7 text-center text-sm text-muted-foreground">
            Belum ada sekolah.
        </p>
    {/if}
</div>
