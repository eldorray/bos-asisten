<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import BarisRkasController from '@/actions/App/Http/Controllers/Rkas/BarisRkasController';
    import InputError from '@/components/InputError.svelte';
    import type { BarisRkas } from '@/components/rkas/FormBarisRkas.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { BULAN } from '@/lib/rkas';

    let {
        terbuka = $bindable(false),
        kertasKerjaUuid,
        baris,
        sisaPagu,
        onSelesai,
    }: {
        terbuka?: boolean;
        kertasKerjaUuid: string;
        /** Belanja yang dipilih; jadwal volumenya diganti semua. */
        baris: BarisRkas[];
        sisaPagu: number;
        onSelesai: () => void;
    } = $props();

    const form = useForm({ bulan: [] as number[], volume: '1' });

    const galat = $derived(form.errors as Record<string, string>);
    const volume = $derived(Number(form.volume) || 0);
    const jumlahBaru = $derived(
        baris.reduce((n, b) => n + b.harga, 0) * volume * form.bulan.length,
    );
    const sisaSetelah = $derived(
        sisaPagu + baris.reduce((n, b) => n + b.jumlah, 0) - jumlahBaru,
    );

    function rupiah(n: number): string {
        return `Rp ${n.toLocaleString('id-ID')}`;
    }

    function pilihRentang(dari: number, sampai: number): void {
        form.bulan = Array.from(
            { length: sampai - dari + 1 },
            (_, i) => dari + i,
        );
    }

    function ganti(bulan: number): void {
        form.bulan = form.bulan.includes(bulan)
            ? form.bulan.filter((b) => b !== bulan)
            : [...form.bulan, bulan];
    }

    function kirim(event: SubmitEvent): void {
        event.preventDefault();
        form
            .transform((data) => ({
                baris: baris.map((b) => b.uuid),
                bulan: data.bulan,
                volume: Number(data.volume) || 0,
            }))
            .submit(BarisRkasController.volume(kertasKerjaUuid), {
                preserveScroll: true,
                onSuccess: () => {
                    terbuka = false;
                    onSelesai();
                },
            });
    }
</script>

<Dialog bind:open={terbuka}>
    <DialogContent
        class="max-h-[90dvh] overflow-y-auto max-md:max-w-[calc(100%-2rem)]"
    >
        <form class="grid gap-4" onsubmit={kirim}>
            <DialogTitle>Atur volume {baris.length} belanja</DialogTitle>
            <DialogDescription>
                Bulan yang dipilih diisi volume yang sama untuk semua belanja
                terpilih. Volume di bulan lain dikosongkan.
            </DialogDescription>

            <div class="grid gap-1.5">
                <Label for="sekaligus-volume">Volume tiap bulan terpilih</Label>
                <Input
                    id="sekaligus-volume"
                    class="w-28"
                    inputmode="numeric"
                    maxlength={6}
                    value={form.volume}
                    oninput={(e) =>
                        (form.volume = (
                            e.target as HTMLInputElement
                        ).value.replace(/\D/g, ''))}
                />
                <InputError message={galat.volume} />
            </div>

            <fieldset class="grid gap-2.5 rounded-xl border p-3">
                <legend class="px-1 text-sm font-medium">Bulan</legend>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        onclick={() => pilihRentang(1, 12)}
                    >
                        Tiap bulan
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        onclick={() => pilihRentang(1, 6)}
                    >
                        Tahap 1
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        onclick={() => pilihRentang(7, 12)}
                    >
                        Tahap 2
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        onclick={() => (form.bulan = [])}
                    >
                        Kosongkan
                    </Button>
                </div>
                <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                    {#each BULAN as nama, i (nama)}
                        {@const dipilih = form.bulan.includes(i + 1)}
                        <button
                            type="button"
                            aria-pressed={dipilih}
                            class={[
                                'h-11 rounded-[10px] border text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                                dipilih
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-input bg-card hover:bg-accent',
                            ]}
                            onclick={() => ganti(i + 1)}
                        >
                            {nama}
                        </button>
                    {/each}
                </div>
                <InputError message={galat.bulan} />
            </fieldset>

            <InputError message={galat.baris ?? galat.kertas_kerja} />

            <div class="grid gap-0.5 text-sm tabular-nums">
                <span>
                    Jumlah {baris.length} belanja menjadi
                    <strong>{rupiah(jumlahBaru)}</strong>
                </span>
                <span
                    class={sisaSetelah < 0
                        ? 'font-medium text-belum-foreground'
                        : 'text-muted-foreground'}
                >
                    {sisaSetelah < 0 ? 'Melebihi pagu' : 'Sisa pagu setelah ini'}
                    <strong>{rupiah(Math.abs(sisaSetelah))}</strong>
                </span>
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="secondary"
                    onclick={() => (terbuka = false)}
                >
                    Batal
                </Button>
                <Button
                    type="submit"
                    disabled={form.processing ||
                        form.bulan.length === 0 ||
                        volume < 1}
                >
                    Terapkan
                </Button>
            </div>
        </form>
    </DialogContent>
</Dialog>
