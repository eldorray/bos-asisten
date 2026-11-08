<script module lang="ts">
    import { lainnya } from '@/routes';

    export const layout = {
        breadcrumbs: [{ title: 'Lainnya', href: lainnya() }],
    };
</script>

<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import Smartphone from '@lucide/svelte/icons/smartphone';
    import JenisKegiatanController from '@/actions/App/Http/Controllers/JenisKegiatanController';
    import PengaturanRkasController from '@/actions/App/Http/Controllers/Rkas/PengaturanRkasController';
    import ReferensiRkasController from '@/actions/App/Http/Controllers/Rkas/ReferensiRkasController';
    import SekolahController from '@/actions/App/Http/Controllers/SekolahController';
    import AppHead from '@/components/AppHead.svelte';
    import UserInfo from '@/components/UserInfo.svelte';
    import { pasangAplikasi } from '@/lib/pasangAplikasi.svelte';
    import { toUrl } from '@/lib/utils';
    import { logout } from '@/routes';
    import { edit as editAppearance } from '@/routes/appearance';
    import { unduh as cadanganUnduh } from '@/routes/cadangan';
    import { edit as editProfile } from '@/routes/profile';
    import { edit as editSecurity } from '@/routes/security';

    const user = $derived(page.props.auth.user);

    const dataSekolah = [
        {
            judul: 'Sekolah',
            keterangan: 'Penandatangan dan nomor kuitansi',
            href: SekolahController.index(),
        },
        {
            judul: 'Jenis Kegiatan',
            keterangan: 'Kata kunci dan daftar berkas',
            href: JenisKegiatanController.index(),
        },
        {
            judul: 'Pengaturan RKAS',
            keterangan: 'Batas juknis dan kode kegiatan per tahun',
            href: PengaturanRkasController.edit(),
        },
        {
            judul: 'Referensi RKAS',
            keterangan: 'Kegiatan, rekening, barang acuan, satuan, dan PTK',
            href: ReferensiRkasController.index(),
        },
    ];

    const akun = [
        { judul: 'Profil', href: editProfile() },
        { judul: 'Keamanan', href: editSecurity() },
        { judul: 'Tampilan', href: editAppearance() },
    ];
</script>

<AppHead title="Lainnya" />

<div class="flex flex-col gap-4 p-4 md:p-7">
    <h1 class="text-[26px] leading-tight font-semibold tracking-tight">
        Lainnya
    </h1>

    {#if user}
        <section
            aria-label="Akun"
            class="flex items-center gap-3 rounded-2xl border bg-card p-3.5"
        >
            <UserInfo {user} showEmail />
        </section>
    {/if}

    {#if pasangAplikasi.tersedia}
        <button
            type="button"
            class="flex items-center gap-3 rounded-2xl bg-primary p-3.5 text-left text-primary-foreground"
            onclick={() => pasangAplikasi.buka()}
        >
            <Smartphone class="size-[22px] shrink-0" />
            <span class="flex-1">
                <span class="block font-semibold">Pasang di HP</span>
                <span class="block text-[13px] opacity-80">
                    Buka langsung dari layar utama
                </span>
            </span>
            <ChevronRight class="size-5 shrink-0" />
        </button>
    {/if}

    <section aria-labelledby="judul-data" class="grid gap-1.5">
        <h2
            id="judul-data"
            class="mx-1 text-[13px] font-medium text-muted-foreground"
        >
            Data sekolah
        </h2>
        <ul class="divide-y rounded-2xl border bg-card">
            {#each dataSekolah as item (item.judul)}
                <li>
                    <Link
                        href={item.href}
                        class="flex min-h-14 items-center gap-2 py-1.5 pr-2.5 pl-3.5"
                    >
                        <span class="flex-1">
                            <span class="block font-medium">{item.judul}</span>
                            <span class="block text-[13px] text-muted-foreground">
                                {item.keterangan}
                            </span>
                        </span>
                        <ChevronRight class="size-5 shrink-0 text-muted-foreground" />
                    </Link>
                </li>
            {/each}
            <li>
                <a
                    href={toUrl(cadanganUnduh())}
                    download
                    class="flex min-h-14 items-center gap-2 py-1.5 pr-2.5 pl-3.5"
                >
                    <span class="flex-1">
                        <span class="block font-medium">Unduh Cadangan</span>
                        <span class="block text-[13px] text-muted-foreground">
                            Data dan foto dalam satu file
                        </span>
                    </span>
                    <ChevronRight class="size-5 shrink-0 text-muted-foreground" />
                </a>
            </li>
        </ul>
    </section>

    <section aria-labelledby="judul-akun" class="grid gap-1.5">
        <h2
            id="judul-akun"
            class="mx-1 text-[13px] font-medium text-muted-foreground"
        >
            Akun
        </h2>
        <ul class="divide-y rounded-2xl border bg-card">
            {#each akun as item (item.judul)}
                <li>
                    <Link
                        href={item.href}
                        class="flex min-h-13 items-center gap-2 py-1.5 pr-2.5 pl-3.5 font-medium"
                    >
                        <span class="flex-1">{item.judul}</span>
                        <ChevronRight class="size-5 shrink-0 text-muted-foreground" />
                    </Link>
                </li>
            {/each}
            <li>
                <Link
                    href={logout()}
                    as="button"
                    onclick={() => router.flushAll()}
                    class="flex min-h-13 w-full items-center px-3.5 text-left font-medium text-belum-foreground"
                >
                    Keluar
                </Link>
            </li>
        </ul>
    </section>
</div>
