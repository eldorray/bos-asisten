<script module lang="ts">
    import JenisKegiatanController from '@/actions/App/Http/Controllers/JenisKegiatanController';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Jenis kegiatan',
                href: JenisKegiatanController.index(),
            },
        ],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import FormJenisKegiatan from '@/components/spj/FormJenisKegiatan.svelte';
    import type { JenisData } from '@/components/spj/FormJenisKegiatan.svelte';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';

    let {
        jenisKegiatans,
    }: {
        jenisKegiatans: JenisData[];
    } = $props();
</script>

<AppHead title="Jenis kegiatan" />

<div class="flex flex-col gap-5 p-5 md:p-7">
    <Heading
        title="Jenis kegiatan"
        description="Kata kunci mencocokkan kegiatan dari anggaran. Urutan berkas menentukan urutan halaman cetak setelah kuitansi."
    />

    <div class="grid items-start gap-3.5 xl:grid-cols-2">
        {#each jenisKegiatans as jenis (jenis.id)}
            <Card>
                <CardHeader class="grid-cols-[1fr_auto] items-center">
                    <CardTitle class="text-base">{jenis.nama}</CardTitle>
                    <span
                        class="rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                    >
                        {jenis.berkas.length + 1} berkas
                    </span>
                </CardHeader>
                <CardContent>
                    <FormJenisKegiatan {jenis} />
                </CardContent>
            </Card>
        {/each}

        <Card class="border-dashed border-input bg-muted">
            <CardHeader>
                <CardTitle class="text-base">Tambah jenis kegiatan</CardTitle>
            </CardHeader>
            <CardContent>
                <FormJenisKegiatan />
            </CardContent>
        </Card>
    </div>
</div>
