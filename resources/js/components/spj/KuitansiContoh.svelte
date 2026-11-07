<script lang="ts">
    import SlotBerkas from '@/components/spj/SlotBerkas.svelte';
    import { namaBulan } from '@/types';

    // Hanya contoh: halaman login bisa dibuka siapa saja, jadi tidak ada data asli di sini.
    const sekarang = new Date();
    const tahun = sekarang.getFullYear();
    const nomorBulan = String(sekarang.getMonth() + 1).padStart(2, '0');
    const bulan = namaBulan[sekarang.getMonth()];

    const berkas = [
        { nama: 'Kuitansi', ada: true, jumlahFoto: 0 },
        { nama: 'Struk bayar', ada: true, jumlahFoto: 1 },
    ];

    const penanda = ['Kepala Sekolah', 'Bendahara', 'Penerima'];
</script>

<div class="grid w-full max-w-lg gap-4">
    <!-- Warna kertas dan tinta sengaja tetap, juga pada mode gelap: ini benda, bukan antarmuka. -->
    <div
        class="relative -rotate-1 rounded-sm bg-white p-8 text-[#1c1c1a] shadow-[0_8px_24px_rgba(0,0,0,0.08)]"
    >
        <div
            class="flex items-baseline justify-between gap-4 border-b-2 border-[#1c1c1a] pb-2"
        >
            <span class="text-base font-medium">Kuitansi</span>
            <span class="text-xs text-[#62625e] tabular-nums">
                No. 012/{nomorBulan}/{tahun}
            </span>
        </div>

        <dl class="mt-4 grid grid-cols-[72px_1fr] gap-y-1 text-sm">
            <dt class="text-[#62625e]">Untuk</dt>
            <dd>Listrik {bulan} {tahun}</dd>
            <dt class="text-[#62625e]">Dana</dt>
            <dd>BOSP Reguler {tahun}</dd>
        </dl>

        <div class="mt-4 text-2xl font-medium tabular-nums">Rp 1.500.000</div>
        <div class="text-sm text-[#62625e]">
            Satu juta lima ratus ribu rupiah
        </div>

        <div class="mt-8 grid grid-cols-3 gap-4 text-center text-xs">
            {#each penanda as peran (peran)}
                <div>
                    <div>{peran}</div>
                    <div class="mt-12 border-t border-[#1c1c1a]/30"></div>
                </div>
            {/each}
        </div>

        <!-- Tanda tangan bendahara dan cap lunas, seperti kuitansi yang sudah dibayar. -->
        <svg
            viewBox="0 0 96 32"
            class="absolute bottom-10 left-1/2 w-24 -translate-x-1/2 text-[#27496d]"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <path
                d="M4 22c6-12 11-17 14-15 3 3-6 17-4 18 3 1 8-13 11-12 2 1-2 10 1 10s7-8 10-7 0 7 3 7 9-9 13-8c3 1-1 7 2 7 6 0 14-5 26-9"
            />
        </svg>
        <div
            class="absolute top-24 right-8 rotate-[-8deg] rounded-sm border-2 border-[#27496d] px-4 py-1 text-xl font-semibold tracking-widest text-[#27496d] opacity-80"
        >
            LUNAS
        </div>
    </div>

    <div class="flex items-center gap-2 text-sm text-muted-foreground">
        <SlotBerkas {berkas} />
        2 dari 2 berkas lengkap
    </div>
</div>
