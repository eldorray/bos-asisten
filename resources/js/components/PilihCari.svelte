<script module lang="ts">
    export type Opsi = { value: string | number; label: string };
</script>

<script lang="ts">
    import Check from '@lucide/svelte/icons/check';
    import ChevronsUpDown from '@lucide/svelte/icons/chevrons-up-down';
    import { Popover } from 'bits-ui';
    import { tick } from 'svelte';
    import { cn } from '@/lib/utils';

    // Pengganti <select> yang bisa dicari. Nilai '' berarti belum memilih.
    let {
        opsi,
        value = $bindable<string | number>(''),
        kosong = null,
        placeholder = 'Pilih',
        onchange,
        class: className,
        id,
        ...restProps
    }: {
        opsi: Opsi[];
        value?: string | number;
        /** Teks baris "belum memilih"; bila diisi, baris itu bisa dipilih untuk mengosongkan. */
        kosong?: string | null;
        placeholder?: string;
        onchange?: (value: string | number) => void;
        class?: string;
        id?: string;
        disabled?: boolean;
        'aria-label'?: string;
    } = $props();

    let terbuka = $state(false);
    let cari = $state('');
    let aktif = $state(0);
    let kotakCari = $state<HTMLInputElement | null>(null);
    let daftarEl = $state<HTMLElement | null>(null);

    const kata = $derived(cari.toLowerCase().split(/\s+/).filter(Boolean));
    const baris = $derived<Opsi[]>([
        ...(kosong !== null && kata.length === 0
            ? [{ value: '', label: kosong }]
            : []),
        ...opsi.filter((o) => {
            const label = o.label.toLowerCase();

            return kata.every((k) => label.includes(k));
        }),
    ]);
    const label = $derived(
        opsi.find((o) => o.value === value)?.label ?? kosong ?? placeholder,
    );

    // Menutup lewat pilihan, Escape, atau klik luar: pencarian selalu mulai dari kosong.
    $effect(() => {
        if (!terbuka) {
            cari = '';
        }
    });

    // Sorotan awal: pilihan saat ini, atau baris pertama bila sedang mencari.
    $effect(() => {
        if (terbuka) {
            const sekarang = kata.length === 0 ? untukNilai(value) : 0;
            aktif = Math.max(sekarang, 0);
            // Daftar baru ada di DOM setelah giliran render berikutnya.
            void tick().then(gulirKeAktif);
            // Lebar popover baru final setelah diposisikan, dan itu mengubah tinggi baris yang membungkus.
            setTimeout(gulirKeAktif, 80);
        }
    });

    // Menggulung hanya di dalam daftar; scrollIntoView akan menggulung seluruh halaman.
    function gulirKeAktif(): void {
        const item = daftarEl?.children[aktif] as HTMLElement | undefined;

        if (!daftarEl || !item) {
            return;
        }

        const atas = item.offsetTop;
        const bawah = atas + item.offsetHeight;

        if (atas < daftarEl.scrollTop) {
            daftarEl.scrollTop = atas;
        } else if (bawah > daftarEl.scrollTop + daftarEl.clientHeight) {
            daftarEl.scrollTop = bawah - daftarEl.clientHeight;
        }
    }

    $effect(() => {
        void aktif;
        gulirKeAktif();
    });

    function untukNilai(nilai: string | number): number {
        return baris.findIndex((b) => b.value === nilai);
    }

    function pilih(baru: string | number): void {
        const berubah = baru !== value;
        value = baru;
        terbuka = false;

        if (berubah) {
            onchange?.(baru);
        }
    }

    function tekanTombol(e: KeyboardEvent): void {
        const terakhir = baris.length - 1;

        if (e.key === 'ArrowDown') {
            aktif = Math.min(aktif + 1, terakhir);
        } else if (e.key === 'ArrowUp') {
            aktif = Math.max(aktif - 1, 0);
        } else if (e.key === 'Home') {
            aktif = 0;
        } else if (e.key === 'End') {
            aktif = Math.max(terakhir, 0);
        } else if (e.key === 'Enter') {
            if (baris[aktif]) {
                pilih(baris[aktif].value);
            }
        } else {
            return;
        }

        e.preventDefault();
    }
</script>

<Popover.Root bind:open={terbuka}>
    <Popover.Trigger
        {id}
        role="combobox"
        class={cn(
            'flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border bg-background px-2 text-left text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50',
            value === '' && 'text-muted-foreground',
            className,
        )}
        {...restProps}
    >
        <span class="min-w-0 truncate">{label}</span>
        <ChevronsUpDown class="size-4 shrink-0 opacity-50" />
    </Popover.Trigger>
    <Popover.Portal>
        <Popover.Content
            sideOffset={4}
            align="start"
            onOpenAutoFocus={(e) => {
                // Fokus bawaan terjadi sebelum popover diposisikan, sehingga layar tergulung ke atas.
                e.preventDefault();
                kotakCari?.focus({ preventScroll: true });
            }}
            class="z-50 w-(--bits-popover-anchor-width) min-w-56 rounded-md border bg-popover p-0 text-popover-foreground shadow-md outline-hidden"
        >
            <input
                bind:this={kotakCari}
                bind:value={cari}
                oninput={() => (aktif = 0)}
                onkeydown={tekanTombol}
                type="text"
                role="combobox"
                aria-expanded="true"
                aria-controls={`${id ?? 'pilih'}-daftar`}
                aria-activedescendant={baris[aktif]
                    ? `${id ?? 'pilih'}-opsi-${aktif}`
                    : undefined}
                aria-label="Cari"
                autocomplete="off"
                placeholder="Cari…"
                class="h-9 w-full border-b bg-transparent px-3 text-sm outline-none placeholder:text-muted-foreground"
            />
            <div
                bind:this={daftarEl}
                id={`${id ?? 'pilih'}-daftar`}
                role="listbox"
                class="relative max-h-64 overflow-y-auto p-1"
            >
                {#each baris as o, i (o.value)}
                    <div
                        id={`${id ?? 'pilih'}-opsi-${i}`}
                        role="option"
                        aria-selected={o.value === value}
                        tabindex="-1"
                        class={cn(
                            'flex cursor-pointer items-start gap-2 rounded-sm px-2 py-1.5 text-sm',
                            o.value === '' && 'text-muted-foreground',
                            i === aktif && 'bg-accent text-accent-foreground',
                        )}
                        onpointermove={() => (aktif = i)}
                        onclick={() => pilih(o.value)}
                        onkeydown={() => {}}
                    >
                        <Check
                            class={cn(
                                'mt-0.5 size-4 shrink-0',
                                o.value !== value && 'invisible',
                            )}
                        />
                        <span class="min-w-0 break-words">{o.label}</span>
                    </div>
                {/each}
                {#if baris.length === 0}
                    <p
                        class="px-2 py-6 text-center text-sm text-muted-foreground"
                    >
                        Tidak ada yang cocok.
                    </p>
                {/if}
            </div>
        </Popover.Content>
    </Popover.Portal>
</Popover.Root>
