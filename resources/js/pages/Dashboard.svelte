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
    import List from '@lucide/svelte/icons/list';
    import SquareKanban from '@lucide/svelte/icons/square-kanban';
    import TriangleAlert from '@lucide/svelte/icons/triangle-alert';
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
    import StripBulan from '@/components/spj/StripBulan.svelte';
    import UnduhPdf from '@/components/spj/UnduhPdf.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { transisi } from '@/lib/transisi';
    import { namaBulan, rupiah } from '@/types';
    import type { BarisKegiatanData, SekolahBulan, StatusKode } from '@/types';

    let {
        bulan,
        ringkasan,
        sekolah,
        menunggakPerBulan,
        pdfBelumPerBulan,
        anggaranPerBulan,
    }: {
        bulan: string;
        ringkasan: { selesai: number; kurang: number; belum: number };
        sekolah: SekolahBulan[];
        menunggakPerBulan: Record<string, number>;
        pdfBelumPerBulan: Record<string, boolean>;
        anggaranPerBulan: Record<string, Record<string, number>>;
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
            titik: 'bg-muted-foreground',
            baris: hitung(baris, 'belum'),
        },
        {
            label: 'Kurang berkas',
            titik: 'bg-kurang-foreground',
            baris: hitung(baris, 'kurang'),
        },
        {
            label: 'Selesai',
            titik: 'bg-selesai-foreground',
            baris: hitung(baris, 'selesai', 'dirapel', 'di_luar_aplikasi'),
        },
        {
            label: 'Tidak dilaksanakan',
            titik: 'bg-input',
            baris: hitung(baris, 'tidak_dilaksanakan'),
        },
    ]);

    // Kolom kosong diringkas jadi satu baris, supaya papan tidak penuh ruang mati.
    const kolomIsi = $derived(kolom.filter((k) => k.baris.length > 0));
    const kolomKosong = $derived(kolom.filter((k) => k.baris.length === 0));
    const lebarPapan: Record<number, string> = {
        1: 'xl:grid-cols-1',
        2: 'xl:grid-cols-2',
        3: 'xl:grid-cols-3',
        4: 'xl:grid-cols-4',
    };

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

<AppHead title={`SPJ ${judul}`} />

<div
    class={[
        'flex flex-col gap-4 p-4 md:gap-6 md:p-8',
        menandai && 'max-md:pb-24',
    ]}
>
    <div class="flex flex-wrap items-center justify-between gap-2 md:gap-4">
        <div class="space-y-1">
            <h1 class="text-[26px] leading-tight font-medium md:text-[28px]">
                <span class="md:hidden">Bulanan</span>
                <span class="max-md:hidden">{judul}</span>
            </h1>
            <p class="text-sm text-muted-foreground max-md:hidden">
                Semua sekolah: {ringkasan.selesai} selesai,
                {ringkasan.kurang} kurang berkas, {ringkasan.belum} belum mulai
            </p>
        </div>
        <PemilihBulan {bulan} {menunggakPerBulan} {pdfBelumPerBulan} />
    </div>

    <StripBulan
        {bulan}
        {menunggakPerBulan}
        {pdfBelumPerBulan}
        {anggaranPerBulan}
        sekolah={sekolah.map((s) => ({ id: s.id, nama: s.nama }))}
    />

    {#if aktif}
        <div class="md:hidden">
            <PilihanSegmen
                label="Sekolah"
                nilai={aktif.id}
                pilihan={pilihanSekolah}
                onpilih={(id) => transisi(() => (sekolahId = id))}
            />
        </div>

        {#each sekolah.filter((s) => s.peringatan) as s (s.id)}
            <div
                role="alert"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-belum py-2 pr-2 pl-3 text-sm text-belum-foreground"
            >
                <div class="flex min-w-60 flex-1 items-center gap-3">
                    <TriangleAlert class="size-4 shrink-0" />
                    <p>{s.nama}: {s.peringatan}</p>
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
                    <CircleAlert class="size-4 shrink-0" />
                    <p>
                        {s.nama}: {perluDiperiksa(s).length} kegiatan perlu
                        diperiksa
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
                                class="flex min-h-14 items-center gap-2 py-2 pr-3 pl-4 text-sm"
                            >
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium">
                                        {k.nama}
                                    </span>
                                    <span
                                        class="block text-meta text-belum-foreground"
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
                    <Calendar class="size-4 shrink-0" />
                    <p>
                        {s.nama}: {jumlahBelumBerbulan(s)} kegiatan belum punya
                        bulan
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
                class="grid gap-4 rounded-2xl bg-primary p-4 text-primary-foreground md:p-6"
            >
                <div class="flex items-end justify-between gap-4">
                    <div class="min-w-0">
                        <div class="text-[28px] leading-none font-medium tabular-nums md:text-4xl">
                            {kelompokHp.beres.length} dari {aktif.kegiatan.length}
                        </div>
                        <div class="mt-1 text-sm opacity-80">
                            kegiatan <span class="max-md:hidden">{aktif.nama}</span> selesai
                        </div>
                    </div>
                    <dl class="hidden gap-8 text-sm md:flex">
                        <div>
                            <dt class="opacity-80">Kurang berkas</dt>
                            <dd class="mt-1 text-lg font-medium tabular-nums">
                                {hitung(aktif.kegiatan, 'kurang').length}
                            </dd>
                        </div>
                        <div>
                            <dt class="opacity-80">Belum mulai</dt>
                            <dd class="mt-1 text-lg font-medium tabular-nums">
                                {hitung(aktif.kegiatan, 'belum').length}
                            </dd>
                        </div>
                        <div>
                            <dt class="opacity-80">Anggaran {aktif.sistem.toUpperCase()}</dt>
                            <dd class="mt-1 text-lg font-medium tabular-nums">
                                Rp {rupiah(aktif.total)}
                            </dd>
                        </div>
                    </dl>
                    <UnduhPdf
                        sekolah={aktif}
                        {bulan}
                        label={layarHp ? 'PDF' : 'Unduh PDF'}
                        class="shrink-0 bg-primary-foreground font-medium text-primary hover:bg-primary-foreground/90"
                    />
                </div>
                <!-- Satu ruas untuk tiap kegiatan, seperti deretan lembar berkas. -->
                <div
                    role="progressbar"
                    aria-label="Kemajuan"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={persenSelesai}
                    class="flex gap-1"
                >
                    {#each [...kelompokHp.beres, ...hitung(aktif.kegiatan, 'kurang'), ...hitung(aktif.kegiatan, 'belum'), ...kelompokHp.batal] as k (k.id)}
                        <span
                            class={[
                                'h-2 min-w-0 flex-1 rounded-xs',
                                kelompokHp.beres.includes(k)
                                    ? 'bg-primary-foreground'
                                    : k.status === 'kurang'
                                      ? 'bg-primary-foreground/50'
                                      : 'bg-primary-foreground/20',
                            ]}
                        ></span>
                    {/each}
                </div>
                <div class="flex justify-between gap-2 text-meta md:hidden">
                    <span class="opacity-80">Anggaran bulan ini</span>
                    <span class="font-medium tabular-nums">
                        Rp {rupiah(aktif.total)}
                    </span>
                </div>
            </section>
        {/if}

        {#if jumlahKeputusan > 0}
            <button
                type="button"
                class="flex min-h-13 items-center gap-2 rounded-xl bg-kurang px-3 py-2 text-left text-nama font-medium text-kurang-foreground md:hidden"
                onclick={() => (keputusanTerbuka = true)}
            >
                <CircleAlert class="size-5 shrink-0" />
                <span class="flex-1">{jumlahKeputusan} hal perlu keputusan Anda</span>
                <ChevronRight class="size-4 shrink-0" />
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
                    onpilih={(id) => transisi(() => (sekolahId = id))}
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
                    onpilih={(t) => transisi(() => (tampilan = t))}
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
                        Hapus centang yang tidak ingin ditandai
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
                </div>
            {/if}
        </div>

        {#if tanpaData}
            <p class="py-7 text-center text-sm text-muted-foreground">
                Daftar kegiatan {aktif.nama} muncul setelah PDF-nya diunggah
            </p>
        {:else}
            <div class="grid grid-cols-1 gap-3 md:hidden">
                <div
                    role="group"
                    aria-label="Saring kegiatan"
                    class="flex gap-2 overflow-x-auto"
                >
                    {#each saringHp as s (s.kunci)}
                        <button
                            type="button"
                            aria-pressed={s.kunci === saringanHp}
                            class={[
                                'h-11 shrink-0 rounded-full border px-3 text-sm font-medium whitespace-nowrap transition-[color,background-color,border-color,transform] duration-150 ease-tenang active:scale-[0.98]',
                                s.kunci === saringanHp
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-input bg-card',
                            ]}
                            onclick={() => transisi(() => (saringanHp = s.kunci))}
                        >
                            {s.label}
                            {kelompokHp[s.kunci].length}
                        </button>
                    {/each}
                </div>

                <ul class="grid grid-cols-1 gap-2">
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
                            Tidak ada kegiatan
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
            <div class="hidden gap-4 md:grid">
                {#if kolomKosong.length > 0 && kolomIsi.length > 0}
                    <p class="text-sm text-muted-foreground">
                        Kosong: {kolomKosong
                            .map((k) => k.label.toLowerCase())
                            .join(', ')}
                    </p>
                {/if}
                <div
                    class={[
                        'grid items-start gap-4 md:grid-cols-2',
                        kolomIsi.length === 1 && 'md:grid-cols-1',
                        lebarPapan[kolomIsi.length],
                    ]}
                >
                    {#each kolomIsi as k (k.label)}
                        <section aria-label={k.label} class="grid gap-3">
                            <h2 class="flex items-center gap-2 text-sm font-medium">
                                <span class={`size-2 rounded-full ${k.titik}`}></span>
                                {k.label}
                                <span class="text-muted-foreground tabular-nums">
                                    {k.baris.length}
                                </span>
                            </h2>
                            <!-- Satu kolom saja diisi beberapa kartu sebaris. -->
                            <div class="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-3">
                                {#each k.baris as item (item.id)}
                                    <KartuKegiatan
                                        kegiatan={item}
                                        {menandai}
                                        bind:dipilih={terpilih[item.id]}
                                    />
                                {/each}
                            </div>
                        </section>
                    {:else}
                        <p class="py-8 text-center text-sm text-muted-foreground">
                            Tidak ada kegiatan
                        </p>
                    {/each}
                </div>
            </div>
        {:else if !tanpaData}
            <div class="hidden overflow-hidden rounded-xl border bg-card md:block">
                <div
                    class="hidden gap-4 border-b px-4 py-3 text-meta font-medium text-muted-foreground lg:grid lg:grid-cols-[minmax(0,2fr)_110px_minmax(0,2fr)_160px]"
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
                        Tidak ada kegiatan
                    </p>
                {/each}
            </div>
        {/if}

        {#if aktif.ditunda.length > 0}
            <section
                aria-label="Kegiatan yang ditunda"
                class="grid gap-2 rounded-xl border bg-card p-4 text-sm"
            >
                <h2 class="text-nama font-medium">
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
                    class="grid scroll-mt-4 items-start gap-4 lg:grid-cols-2"
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
