<script lang="ts">
    import Check from '@lucide/svelte/icons/check';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import LembarBawah from '@/components/spj/LembarBawah.svelte';
    import { Button } from '@/components/ui/button';
    import { pasangAplikasi } from '@/lib/pasangAplikasi.svelte';

    const manfaat = [
        'Buka langsung dari layar utama, tanpa mengetik alamat',
        'Tampil penuh seperti aplikasi biasa',
        'Foto tetap tersimpan saat sinyal hilang',
    ];
</script>

<LembarBawah
    judul="Pasang SPJ BOS di HP"
    terbuka={pasangAplikasi.terbuka}
    ontutup={() => pasangAplikasi.terbuka && pasangAplikasi.tutup()}
>
    <div class="flex items-center gap-3">
        <span
            class="grid size-14 shrink-0 place-items-center rounded-[15px] bg-primary text-primary-foreground"
        >
            <AppLogoIcon class="size-7" />
        </span>
        <p class="text-[15px] text-muted-foreground">Gratis, tanpa Play Store.</p>
    </div>
    <ul class="grid gap-2.5 text-[15px]">
        {#each manfaat as m (m)}
            <li class="flex gap-2.5">
                <Check class="mt-0.5 size-5 shrink-0 text-selesai-foreground" />
                {m}
            </li>
        {/each}
    </ul>
    {#if pasangAplikasi.perluPetunjukIos}
        <p class="rounded-[10px] bg-muted px-3 py-2.5 text-sm">
            Buka di Safari, ketuk Bagikan, lalu pilih
            <strong class="font-semibold">Tambah ke Layar Utama</strong>.
        </p>
        <Button size="lg" onclick={() => pasangAplikasi.tutup()}>Mengerti</Button>
    {:else}
        <Button size="lg" onclick={() => pasangAplikasi.pasang()}>
            Pasang sekarang
        </Button>
        <Button variant="ghost" onclick={() => pasangAplikasi.tutup()}>
            Nanti saja
        </Button>
    {/if}
</LembarBawah>
