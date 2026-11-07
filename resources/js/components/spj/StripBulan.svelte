<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { dashboard } from '@/routes';
    import { namaBulan } from '@/types';

    let {
        bulan,
        menunggakPerBulan,
        pdfBelumPerBulan,
    }: {
        bulan: string;
        menunggakPerBulan: Record<string, number>;
        pdfBelumPerBulan: Record<string, boolean>;
    } = $props();

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

<nav
    aria-label="Pilih bulan"
    class="hidden grid-cols-6 gap-1 rounded-xl bg-accent p-1 md:grid lg:grid-cols-12"
>
    {#each namaBulan as nama, i (nama)}
        {@const n = i + 1}
        {@const k = keadaan(n)}
        <Link
            href={dashboard({
                query: { bulan: `${tahun}-${String(n).padStart(2, '0')}` },
            })}
            aria-current={n === nomor ? 'date' : undefined}
            aria-label={judul(nama, n)}
            title={judul(nama, n)}
            class={[
                'grid gap-1 rounded-md p-2 transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                n === nomor ? 'bg-card shadow-xs' : 'hover:bg-card/60',
            ]}
        >
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
        </Link>
    {/each}
</nav>
