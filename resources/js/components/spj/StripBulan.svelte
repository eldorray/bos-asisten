<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { dashboard } from '@/routes';
    import { Button } from '@/components/ui/button';
    import { namaBulan, rupiah } from '@/types';

    let {
        bulan,
        menunggakPerBulan,
        pdfBelumPerBulan,
        anggaranPerBulan,
        sekolah,
    }: {
        bulan: string;
        menunggakPerBulan: Record<string, number>;
        pdfBelumPerBulan: Record<string, boolean>;
        anggaranPerBulan: Record<string, Record<string, number>>;
        sekolah: { id: number; nama: string }[];
    } = $props();

    // Mode hitung: klik bulan menandai, bukan pindah halaman, lalu anggarannya dijumlahkan.
    let menghitung = $state(false);
    let terpilih = $state<number[]>([]);

    function ubahMode(): void {
        menghitung = !menghitung;
        terpilih = [];
    }

    function tandai(n: number): void {
        terpilih = terpilih.includes(n)
            ? terpilih.filter((b) => b !== n)
            : [...terpilih, n].sort((a, b) => a - b);
    }

    const perSekolah = $derived(
        sekolah.map((s) => ({
            nama: s.nama,
            jumlah: terpilih.reduce(
                (total, n) => total + (anggaranPerBulan[s.id]?.[n] ?? 0),
                0,
            ),
        })),
    );
    const jumlahTerpilih = $derived(
        perSekolah.reduce((total, s) => total + s.jumlah, 0),
    );

    const tahun = $derived(Number(bulan.slice(0, 4)));
    const nomor = $derived(Number(bulan.slice(5, 7)));

    const sekarang = new Date();
    const tahunIni = sekarang.getFullYear();
    const bulanIni = sekarang.getMonth() + 1;

    function keadaan(n: number): { catatan: string; garis: string; teks: string } {
        const tunggak = menunggakPerBulan[n] ?? 0;
        const sudahTiba =
            tahun < tahunIni || (tahun === tahunIni && n <= bulanIni);

        if (pdfBelumPerBulan[n]) {
            return {
                catatan: 'PDF belum',
                garis: 'bg-belum-foreground',
                teks: 'text-belum-foreground',
            };
        }

        if (tunggak > 0) {
            return {
                catatan: `${tunggak} belum`,
                garis: sudahTiba ? 'bg-kurang-foreground' : 'bg-input',
                teks: sudahTiba
                    ? 'text-kurang-foreground'
                    : 'text-muted-foreground',
            };
        }

        return sudahTiba
            ? {
                  catatan: 'Lengkap',
                  garis: 'bg-selesai-foreground',
                  teks: 'text-selesai-foreground',
              }
            : { catatan: '–', garis: 'bg-input', teks: 'text-muted-foreground' };
    }

    function judul(nama: string, n: number): string {
        const tunggak = menunggakPerBulan[n] ?? 0;
        const catatan = [
            pdfBelumPerBulan[n] ? 'PDF belum diunggah' : null,
            tunggak > 0 ? `${tunggak} kegiatan belum selesai` : null,
        ].filter(Boolean);

        return catatan.length > 0 ? `${nama}: ${catatan.join(', ')}` : nama;
    }
</script>

{#snippet isi(nama: string, n: number)}
    {@const k = keadaan(n)}
    <span class={`h-1 rounded-full ${k.garis}`}></span>
    <span class="flex items-center gap-1 text-sm font-medium">
        {nama.slice(0, 3)}
        {#if n === bulanIni && tahun === tahunIni}
            <span class="size-1.5 rounded-full bg-foreground"></span>
        {/if}
    </span>
    <span class={`text-xs whitespace-nowrap tabular-nums ${k.teks}`}>
        {k.catatan}
    </span>
{/snippet}

<div class="hidden gap-2 md:grid">
    <div class="flex justify-end">
        <Button
            type="button"
            size="sm"
            variant={menghitung ? 'default' : 'outline'}
            aria-pressed={menghitung}
            onclick={ubahMode}
        >
            {menghitung ? 'Selesai menghitung' : 'Hitung beberapa bulan'}
        </Button>
    </div>

    <nav
        aria-label="Pilih bulan"
        class="grid grid-cols-6 gap-1 rounded-xl bg-accent p-1 lg:grid-cols-12"
    >
        {#each namaBulan as nama, i (nama)}
            {@const n = i + 1}
            {#if menghitung}
                <button
                    type="button"
                    aria-pressed={terpilih.includes(n)}
                    aria-label={judul(nama, n)}
                    title={judul(nama, n)}
                    class={[
                        'grid gap-1 rounded-md p-2 text-left transition-[background-color,transform] duration-150 ease-tenang active:scale-[0.98] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        terpilih.includes(n)
                            ? 'bg-card shadow-xs ring-2 ring-primary'
                            : 'hover:bg-card/60',
                    ]}
                    onclick={() => tandai(n)}
                >
                    {@render isi(nama, n)}
                </button>
            {:else}
                <Link
                    href={dashboard({
                        query: {
                            bulan: `${tahun}-${String(n).padStart(2, '0')}`,
                        },
                    })}
                    aria-current={n === nomor ? 'date' : undefined}
                    aria-label={judul(nama, n)}
                    title={judul(nama, n)}
                    class={[
                        'grid gap-1 rounded-md p-2 transition-[background-color,transform] duration-150 ease-tenang active:scale-[0.98] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        n === nomor ? 'bg-card shadow-xs' : 'hover:bg-card/60',
                    ]}
                >
                    {@render isi(nama, n)}
                </Link>
            {/if}
        {/each}
    </nav>

    {#if menghitung}
        <div
            aria-live="polite"
            class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 rounded-xl border bg-card px-4 py-3"
        >
            {#if terpilih.length === 0}
                <p class="text-sm text-muted-foreground">
                    Klik beberapa bulan untuk menjumlahkan anggarannya.
                </p>
            {:else}
                <div class="grid gap-0.5">
                    <p class="text-sm text-muted-foreground">
                        Anggaran {terpilih
                            .map((n) => namaBulan[n - 1])
                            .join(', ')}
                        {tahun}
                    </p>
                    {#if perSekolah.length > 1}
                        <p class="text-meta text-muted-foreground tabular-nums">
                            {perSekolah
                                .map((s) => `${s.nama}: Rp ${rupiah(s.jumlah)}`)
                                .join(' · ')}
                        </p>
                    {/if}
                </div>
                <div class="flex items-center gap-3">
                    <strong class="text-lg font-semibold tabular-nums">
                        Rp {rupiah(jumlahTerpilih)}
                    </strong>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onclick={() => (terpilih = [])}
                    >
                        Kosongkan
                    </Button>
                </div>
            {/if}
        </div>
    {/if}
</div>
