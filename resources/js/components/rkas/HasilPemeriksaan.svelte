<script module lang="ts">
    export type HasilPemeriksaan = {
        pemblokir: string[];
        peringatan: string[];
    };
</script>

<script lang="ts">
    import CircleAlert from '@lucide/svelte/icons/circle-alert';
    import TriangleAlert from '@lucide/svelte/icons/triangle-alert';

    let { hasil }: { hasil: HasilPemeriksaan } = $props();
</script>

{#if hasil.pemblokir.length > 0}
    <div
        class="rounded-lg border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive"
        role="alert"
    >
        <p class="mb-2 font-medium">Belum bisa dinyatakan siap</p>
        <ul class="grid gap-1.5">
            {#each hasil.pemblokir as pesan (pesan)}
                <li class="flex gap-2">
                    <CircleAlert class="mt-0.5 size-4 shrink-0" />
                    <span>{pesan}</span>
                </li>
            {/each}
        </ul>
    </div>
{/if}

{#if hasil.peringatan.length > 0}
    <div
        class="rounded-lg border border-yellow-500/40 bg-yellow-500/10 p-4 text-sm text-yellow-800 dark:text-yellow-300"
    >
        <p class="mb-2 font-medium">Peringatan</p>
        <ul class="grid gap-1.5">
            {#each hasil.peringatan as pesan (pesan)}
                <li class="flex gap-2">
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    <span>{pesan}</span>
                </li>
            {/each}
        </ul>
    </div>
{/if}
