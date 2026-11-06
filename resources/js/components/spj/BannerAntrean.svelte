<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import CloudUpload from '@lucide/svelte/icons/cloud-upload';
    import WifiOff from '@lucide/svelte/icons/wifi-off';
    import { antreanFoto } from '@/lib/antreanFoto.svelte';
    import { login } from '@/routes';

    const jumlah = $derived(
        antreanFoto.daftar.filter((a) => a.status !== 'gagal').length,
    );
</script>

{#if antreanFoto.sesiHabis}
    <div
        role="alert"
        class="flex flex-wrap items-center justify-between gap-2 bg-belum px-4 py-2.5 text-sm text-belum-foreground md:rounded-t-2xl"
    >
        <span>Sesi berakhir. {jumlah} foto menunggu sampai Anda masuk lagi.</span>
        <Link href={login()} class="font-semibold underline">Masuk</Link>
    </div>
{:else if !antreanFoto.online}
    <div
        role="status"
        class="flex items-start gap-2.5 bg-primary px-4 py-2.5 text-sm text-primary-foreground md:rounded-t-2xl"
    >
        <WifiOff class="mt-0.5 size-4 shrink-0" />
        <span>
            Tidak ada sinyal. {jumlah > 0 ? `${jumlah} foto` : 'Foto'} tersimpan di
            HP dan terkirim otomatis saat online.
        </span>
    </div>
{:else if jumlah > 0}
    <div
        role="status"
        class="flex items-center gap-2.5 bg-muted px-4 py-2.5 text-sm md:rounded-t-2xl"
    >
        <CloudUpload class="size-4 shrink-0" />
        <span>Mengirim {jumlah} foto…</span>
    </div>
{/if}
