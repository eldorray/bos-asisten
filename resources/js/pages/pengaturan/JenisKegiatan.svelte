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

<div class="flex flex-col gap-6 p-4">
    <Heading
        title="Jenis kegiatan"
        description="Kata kunci mencocokkan kegiatan dari anggaran. Urutan berkas menentukan urutan halaman cetak setelah kuitansi."
    />

    {#each jenisKegiatans as jenis (jenis.id)}
        <Card>
            <CardHeader>
                <CardTitle class="text-base">{jenis.nama}</CardTitle>
            </CardHeader>
            <CardContent>
                <FormJenisKegiatan {jenis} />
            </CardContent>
        </Card>
    {/each}

    <Card>
        <CardHeader>
            <CardTitle class="text-base">Tambah jenis kegiatan</CardTitle>
        </CardHeader>
        <CardContent>
            <FormJenisKegiatan />
        </CardContent>
    </Card>
</div>
