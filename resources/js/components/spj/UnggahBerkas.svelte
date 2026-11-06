<script lang="ts">
    import Camera from '@lucide/svelte/icons/camera';
    import Image from '@lucide/svelte/icons/image';
    import { Button } from '@/components/ui/button';
    import { antreanFoto } from '@/lib/antreanFoto.svelte';

    let {
        kegiatanId,
        jenisBerkasId,
        onpilih,
    }: {
        kegiatanId: number;
        jenisBerkasId: number;
        onpilih?: () => void;
    } = $props();

    const antrean = $derived(antreanFoto.untuk(kegiatanId, jenisBerkasId));

    let inputKamera: HTMLInputElement;
    let inputBerkas: HTMLInputElement;

    function tambah(event: Event): void {
        const input = event.currentTarget as HTMLInputElement;
        const berkas = Array.from(input.files ?? []);

        input.value = '';

        if (berkas.length > 0) {
            void antreanFoto.tambah(kegiatanId, jenisBerkasId, berkas);
            onpilih?.();
        }
    }
</script>

<div class="grid gap-2">
    <div class="flex flex-wrap gap-2">
        <Button
            type="button"
            variant="outline"
            class="max-md:h-15 max-md:w-full max-md:border-0 max-md:bg-primary max-md:text-base max-md:font-semibold max-md:text-primary-foreground"
            onclick={() => inputKamera.click()}
        >
            <Camera class="size-5 md:size-4" />
            Ambil foto
        </Button>
        <Button
            type="button"
            variant="outline"
            class="max-md:h-13 max-md:w-full max-md:font-semibold"
            onclick={() => inputBerkas.click()}
        >
            <Image class="size-5 md:size-4" />
            <span class="md:hidden">Pilih dari galeri</span>
            <span class="max-md:hidden">Pilih file</span>
        </Button>
        <input
            bind:this={inputKamera}
            type="file"
            accept="image/*"
            capture="environment"
            class="hidden"
            onchange={tambah}
        />
        <input
            bind:this={inputBerkas}
            type="file"
            accept="image/*"
            multiple
            class="hidden"
            onchange={tambah}
        />
    </div>

    {#each antrean as item (item.kunci)}
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="max-w-48 truncate">{item.namaFile}</span>
            {#if item.status === 'gagal'}
                <span class="text-belum-foreground">{item.pesan}</span>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onclick={() => antreanFoto.ulangi(item)}
                >
                    Coba lagi
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onclick={() => antreanFoto.buang(item)}
                >
                    Buang
                </Button>
            {:else if item.status === 'mengunggah'}
                <span class="text-muted-foreground">Mengunggah…</span>
            {:else}
                <span class="text-kurang-foreground">Menunggu dikirim</span>
            {/if}
        </div>
    {/each}
</div>
