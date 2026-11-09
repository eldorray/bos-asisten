<?php

declare(strict_types=1);

use App\Actions\Rkas\BuatRevisi;
use App\Actions\Rkas\PesanRkas;
use App\Actions\Rkas\SimpanKertasKerja;
use App\Enums\JenisVersiRkas;
use App\Enums\StatusKertasKerja;
use App\Enums\SumberDanaRkas;
use App\Models\RkasKertasKerja;
use App\Models\Sekolah;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DataAwalSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(DataAwalSeeder::class);
    $this->smp = Sekolah::where('nama', 'like', '%Garuda%')->firstOrFail();
    $this->actingAs(User::factory()->create());
});

it('membuat kertas kerja BOSP Reguler 2027 dengan pagu', function () {
    $this->post(route('rkas.store'), [
        'sekolah_id' => $this->smp->id,
        'tahun' => 2027,
        'sumber_dana' => 'bosp_reguler',
        'pagu' => 250_000_000,
    ])->assertSessionHasNoErrors();

    $kk = RkasKertasKerja::firstOrFail();
    expect($kk->sekolah_id)->toBe($this->smp->id)
        ->and($kk->tahun)->toBe(2027)
        ->and($kk->sumber_dana)->toBe(SumberDanaRkas::BospReguler)
        ->and($kk->pagu)->toBe(250_000_000)
        ->and($kk->status)->toBe(StatusKertasKerja::Draf)
        ->and($kk->jenis_versi)->toBe(JenisVersiRkas::Awal)
        ->and($kk->nomor_pergeseran)->toBe(0)
        ->and($kk->nomor_perubahan)->toBe(0);
});

it('membuat kertas kerja Lainnya dengan pagu dari jumlah pemberi dana', function () {
    $this->post(route('rkas.store'), [
        'sekolah_id' => $this->smp->id,
        'tahun' => 2027,
        'sumber_dana' => 'lainnya',
        'pagu' => 1,
        'pemberi_dana' => [
            ['nama' => 'Komite', 'jumlah' => 3_000_000],
            ['nama' => 'Donatur', 'jumlah' => 2_500_000],
        ],
    ])->assertSessionHasNoErrors();

    $kk = RkasKertasKerja::firstOrFail();
    expect($kk->pagu)->toBe(5_500_000)
        ->and($kk->pemberiDanas()->count())->toBe(2);
});

it('menolak kertas kerja untuk sekolah ERKAM', function () {
    $erkam = Sekolah::factory()->erkam()->create();

    $this->post(route('rkas.store'), [
        'sekolah_id' => $erkam->id,
        'tahun' => 2027,
        'sumber_dana' => 'bosp_reguler',
        'pagu' => 1_000_000,
    ])->assertSessionHasErrors(['sekolah_id' => PesanRkas::BUKAN_ARKAS]);

    expect(RkasKertasKerja::count())->toBe(0);
});

it('menolak kertas kerja awal ganda untuk sekolah tahun dan sumber dana yang sama', function () {
    RkasKertasKerja::factory()->create(['sekolah_id' => $this->smp->id, 'tahun' => 2027]);

    $this->post(route('rkas.store'), [
        'sekolah_id' => $this->smp->id,
        'tahun' => 2027,
        'sumber_dana' => 'bosp_reguler',
        'pagu' => 1_000_000,
    ])->assertSessionHasErrors(['sumber_dana' => PesanRkas::SUDAH_ADA]);

    expect(RkasKertasKerja::count())->toBe(1);
});

it('mengubah pagu BOSP Reguler selama draf', function () {
    $kk = RkasKertasKerja::factory()->create(['sekolah_id' => $this->smp->id]);

    $this->patch(route('rkas.update', $kk), ['pagu' => 300_000_000])->assertSessionHasNoErrors();

    expect($kk->fresh()->pagu)->toBe(300_000_000);
});

it('mengganti pemberi dana dan menghitung ulang pagu Lainnya', function () {
    $kk = RkasKertasKerja::factory()->create([
        'sekolah_id' => $this->smp->id,
        'sumber_dana' => SumberDanaRkas::Lainnya,
        'pagu' => 1_000_000,
    ]);
    $kk->pemberiDanas()->create(['nama' => 'Lama', 'jumlah' => 1_000_000]);

    $this->patch(route('rkas.update', $kk), [
        'pemberi_dana' => [['nama' => 'Baru', 'jumlah' => 4_000_000]],
    ])->assertSessionHasNoErrors();

    expect($kk->fresh()->pagu)->toBe(4_000_000)
        ->and($kk->pemberiDanas()->pluck('nama')->all())->toBe(['Baru']);
});

it('menolak mengubah pagu kertas kerja yang bukan draf', function () {
    $kk = RkasKertasKerja::factory()->create([
        'sekolah_id' => $this->smp->id,
        'status' => StatusKertasKerja::Siap,
    ]);

    $this->patch(route('rkas.update', $kk), ['pagu' => 1])
        ->assertSessionHasErrors(['pagu' => sprintf(PesanRkas::TERKUNCI, 'Siap')]);

    expect($kk->fresh()->pagu)->toBe(100_000_000);
});

it('menghapus kertas kerja berstatus draf', function () {
    $kk = RkasKertasKerja::factory()->create(['sekolah_id' => $this->smp->id]);

    $this->delete(route('rkas.destroy', $kk))->assertRedirect(route('rkas.index', ['tahun' => 2027, 'sekolah' => $this->smp->uuid]));

    expect(RkasKertasKerja::count())->toBe(0);
});

it('hanya menghapus kertas kerja berstatus draf', function () {
    $kk = RkasKertasKerja::factory()->create([
        'sekolah_id' => $this->smp->id,
        'status' => StatusKertasKerja::Siap,
    ]);

    $this->delete(route('rkas.destroy', $kk))
        ->assertSessionHasErrors(['hapus' => sprintf(PesanRkas::TERKUNCI, 'Siap')]);

    expect(RkasKertasKerja::count())->toBe(1);
});

it('menampilkan daftar kertas kerja per tahun tanpa kolom mentah', function () {
    RkasKertasKerja::factory()->create(['sekolah_id' => $this->smp->id, 'tahun' => 2027]);
    RkasKertasKerja::factory()->create(['sekolah_id' => $this->smp->id, 'tahun' => 2026]);

    $this->get(route('rkas.index', ['tahun' => 2027]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('rkas/Index')
            ->where('tahun', 2027)
            ->where('sekolah.id', $this->smp->id)
            ->has('daftar', 1)
            ->where('daftar.0.versi', 'Awal')
            ->where('daftar.0.pagu', 100_000_000)
            ->where('daftar.0.dianggarkan', 0)
            ->where('daftar.0.sisa', 100_000_000)
            ->missing('daftar.0.created_at'));
});

it('menampilkan kertas kerja dengan pemberi dana', function () {
    $kk = RkasKertasKerja::factory()->create([
        'sekolah_id' => $this->smp->id,
        'sumber_dana' => SumberDanaRkas::Lainnya,
    ]);
    $kk->pemberiDanas()->create(['nama' => 'Komite', 'jumlah' => 1_000_000]);

    $this->get(route('rkas.show', $kk))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('rkas/Show')
            ->where('kertasKerja.id', $kk->id)
            ->where('kertasKerja.label', 'Kertas Kerja')
            ->where('kertasKerja.bisa_diubah', true)
            ->where('kertasKerja.sekolah.id', $this->smp->id)
            ->has('pemberiDana', 1)
            ->where('pemberiDana.0.nama', 'Komite')
            ->missing('kertasKerja.created_at'));
});

it('menolak halaman kertas kerja milik sekolah ERKAM', function () {
    $erkam = Sekolah::factory()->erkam()->create();
    $kk = RkasKertasKerja::factory()->create(['sekolah_id' => $erkam->id]);

    $this->get(route('rkas.show', $kk))->assertNotFound();
});

it('tidak mengubah tabel SPJ', function () {
    $tabel = ['kegiatans', 'kuitansis', 'impor_anggarans'];
    $hitung = fn (): array => array_map(fn (string $t): int => DB::table($t)->count(), $tabel);
    $sebelum = $hitung();

    $this->post(route('rkas.store'), [
        'sekolah_id' => $this->smp->id,
        'tahun' => 2027,
        'sumber_dana' => 'bosp_reguler',
        'pagu' => 1_000_000,
    ])->assertSessionHasNoErrors();
    $kk = RkasKertasKerja::firstOrFail();
    $this->patch(route('rkas.update', $kk), ['pagu' => 2_000_000])->assertSessionHasNoErrors();
    $this->delete(route('rkas.destroy', $kk));

    expect($hitung())->toBe($sebelum);
});

it('wajib login', function () {
    auth()->logout();

    $this->get(route('rkas.index'))->assertRedirect(route('login'));
});

it('menolak mengubah pagu BOSP Reguler pada pergeseran', function () {
    $induk = RkasKertasKerja::factory()->create(['sekolah_id' => $this->smp->id, 'tahun' => 2027, 'status' => StatusKertasKerja::Disahkan]);
    $p = app(BuatRevisi::class)->jalankan($induk, JenisVersiRkas::Pergeseran, CarbonImmutable::parse('2027-07-15'));

    $this->patch(route('rkas.update', $p), ['pagu' => 1])
        ->assertSessionHasErrors(['pagu' => PesanRkas::PAGU_PERGESERAN]);

    expect($p->fresh()->pagu)->toBe(100_000_000);
});

it('menolak mengganti pemberi dana Lainnya pada pergeseran', function () {
    $induk = RkasKertasKerja::factory()->create([
        'sekolah_id' => $this->smp->id, 'tahun' => 2027, 'sumber_dana' => SumberDanaRkas::Lainnya,
        'pagu' => 1_000_000, 'status' => StatusKertasKerja::Disahkan,
    ]);
    $induk->pemberiDanas()->create(['nama' => 'Lama', 'jumlah' => 1_000_000]);
    $p = app(BuatRevisi::class)->jalankan($induk, JenisVersiRkas::Pergeseran, CarbonImmutable::parse('2027-07-15'));

    $this->patch(route('rkas.update', $p), ['pemberi_dana' => [['nama' => 'Baru', 'jumlah' => 4_000_000]]])
        ->assertSessionHasErrors(['pagu' => PesanRkas::PAGU_PERGESERAN]);

    expect($p->fresh()->pagu)->toBe(1_000_000)
        ->and($p->pemberiDanas()->pluck('nama')->all())->toBe(['Lama']);
});

it('menolak ubahPagu dari action pada kertas kerja yang bukan draf', function () {
    $kk = RkasKertasKerja::factory()->create(['sekolah_id' => $this->smp->id, 'status' => StatusKertasKerja::Siap]);

    expect(fn () => app(SimpanKertasKerja::class)->ubahPagu($kk, ['pagu' => 1]))
        ->toThrow(ValidationException::class, sprintf(PesanRkas::TERKUNCI, 'Siap'));

    expect($kk->fresh()->pagu)->toBe(100_000_000);
});
