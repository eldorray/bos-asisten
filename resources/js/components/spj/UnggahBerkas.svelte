<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import FotoController from '@/actions/App/Http/Controllers/FotoController';
    import { Button } from '@/components/ui/button';
    import { compressImage } from '@/lib/compressImage';

    type Antrean = {
        kunci: number;
        file: File;
        status: 'menunggu' | 'mengunggah' | 'gagal';
        pesan: string | null;
    };

    let {
        kegiatanId,
        jenisBerkasId,
    }: {
        kegiatanId: number;
        jenisBerkasId: number;
    } = $props();

    let antrean = $state<Antrean[]>([]);
    let berjalan = false;
    let kunciBerikut = 0;
    let inputKamera: HTMLInputElement;
    let inputBerkas: HTMLInputElement;

    function tambah(event: Event): void {
        const input = event.currentTarget as HTMLInputElement;

        for (const file of Array.from(input.files ?? [])) {
            antrean.push({
                kunci: kunciBerikut++,
                file,
                status: 'menunggu',
                pesan: null,
            });
        }

        input.value = '';
        void jalankan();
    }

    function ulangi(item: Antrean): void {
        item.status = 'menunggu';
        item.pesan = null;
        void jalankan();
    }

    function kirim(item: Antrean, file: File): Promise<string | null> {
        return new Promise((selesai) => {
            let pesan: string | null = null;
            let berhasil = false;

            router.post(
                FotoController.store(kegiatanId),
                { jenis_berkas_id: jenisBerkasId, foto: file },
                {
                    forceFormData: true,
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        berhasil = true;
                    },
                    onHttpException: () => {
                        pesan = 'Gagal mengunggah. Coba lagi.';

                        return false;
                    },
                    onNetworkError: () => {
                        pesan = 'Koneksi terputus. Coba lagi.';

                        return false;
                    },
                    onError: (errors) => {
                        pesan =
                            errors.foto ??
                            errors.jenis_berkas_id ??
                            'Gagal mengunggah.';
                    },
                    onCancel: () => {
                        pesan = 'Unggahan dibatalkan.';
                    },
                    onFinish: () =>
                        selesai(
                            berhasil
                                ? null
                                : (pesan ?? 'Gagal mengunggah. Coba lagi.'),
                        ),
                },
            );
        });
    }

    // Satu per satu: Inertia membatalkan kunjungan yang tumpang tindih.
    async function jalankan(): Promise<void> {
        if (berjalan) {
            return;
        }

        berjalan = true;

        try {
            let item = antrean.find((a) => a.status === 'menunggu');

            while (item) {
                item.status = 'mengunggah';
                const kecil = await compressImage(item.file);
                const pesan = await kirim(item, kecil).catch(
                    () => 'Koneksi terputus.',
                );

                if (pesan === null) {
                    antrean = antrean.filter((a) => a.kunci !== item!.kunci);
                } else {
                    item.status = 'gagal';
                    item.pesan = pesan;
                }

                item = antrean.find((a) => a.status === 'menunggu');
            }
        } finally {
            berjalan = false;
        }
    }
</script>

<div class="grid gap-2">
    <div class="flex flex-wrap gap-2">
        <Button type="button" variant="outline" onclick={() => inputKamera.click()}>
            Ambil foto
        </Button>
        <Button type="button" variant="outline" onclick={() => inputBerkas.click()}>
            Pilih file
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
            <span class="max-w-48 truncate">{item.file.name}</span>
            {#if item.status === 'gagal'}
                <span class="text-belum-foreground">{item.pesan}</span>
                <Button type="button" size="sm" variant="outline" onclick={() => ulangi(item)}>
                    Coba lagi
                </Button>
            {:else if item.status === 'mengunggah'}
                <span class="text-muted-foreground">Mengunggah…</span>
            {:else}
                <span class="text-muted-foreground">Menunggu</span>
            {/if}
        </div>
    {/each}
</div>
