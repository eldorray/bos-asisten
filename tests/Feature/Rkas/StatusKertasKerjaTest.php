<?php

declare(strict_types=1);

use App\Actions\Rkas\PesanRkas;
use App\Actions\Rkas\UbahStatusKertasKerja;
use App\Enums\SistemAnggaran;
use App\Enums\StatusKertasKerja;
use App\Enums\SumberDanaRkas;
use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;
use App\Models\RkasRefKegiatan;
use App\Models\RkasRefRekening;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\DataAwalSeeder;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(DataAwalSeeder::class);
    $this->smp = Sekolah::where('nama', 'like', '%Garuda%')->firstOrFail();
    $this->actingAs(User::factory()->create());

    $this->kk = fn (int $pagu = 100_000_000, SumberDanaRkas $sumber = SumberDanaRkas::BospReguler, StatusKertasKerja $status = StatusKertasKerja::Draf): RkasKertasKerja => RkasKertasKerja::factory()->create([
        'sekolah_id' => $this->smp->id, 'tahun' => 2027, 'pagu' => $pagu, 'sumber_dana' => $sumber, 'status' => $status,
    ]);

    $this->baris = function (RkasKertasKerja $kk, array $volume, int $harga, array $lain = []): RkasBaris {
        $kegiatan = RkasRefKegiatan::firstOrCreate(['tahun' => 2027, 'kode' => '07.01.01.'], ['uraian' => 'K', 'level' => 3]);
        $rekening = RkasRefRekening::firstOrCreate(['tahun' => 2027, 'kode' => '5.1.02.01.01.0001'], ['nama' => 'R']);

        return RkasBaris::factory()->create(array_replace([
            'kertas_kerja_id' => $kk->id,
            'ref_kegiatan_id' => $kegiatan->id,
            'ref_rekening_id' => $rekening->id,
            'harga' => $harga,
            'volume_bulan' => array_replace(array_fill_keys(range(1, 12), 0), $volume),
        ], $lain));
    };
});

it('menyatakan siap bila total sama dengan pagu dan tahap 1 cukup', function () {
    $kk = ($this->kk)();
    ($this->baris)($kk, [1 => 1], 30_000_000);
    ($this->baris)($kk, [8 => 1], 70_000_000, ['urutan' => 2]);

    $this->post(route('rkas.status.siap', $kk))
        ->assertRedirect()
        ->assertInertiaFlash('pemeriksaan');

    expect($kk->fresh()->status)->toBe(StatusKertasKerja::Siap);
});

it('menolak siap bila Januari sampai Juni di bawah 25 persen pagu', function () {
    $kk = ($this->kk)();
    ($this->baris)($kk, [1 => 1], 24_999_999);
    ($this->baris)($kk, [8 => 1], 75_000_001, ['urutan' => 2]);

    $this->post(route('rkas.status.siap', $kk))->assertRedirect();

    expect($kk->fresh()->status)->toBe(StatusKertasKerja::Draf);

    $hasil = app(UbahStatusKertasKerja::class)->nyatakanSiap($kk->fresh());
    expect($hasil->pemblokir[0])->toBe(PesanRkas::TAHAP_1_MINIMAL)
        ->and($hasil->lolos())->toBeFalse();
});

it('menolak siap dengan sisa dan dengan kelebihan beserta nominalnya', function () {
    $kk = ($this->kk)();
    ($this->baris)($kk, [1 => 1], 40_000_000);
    $hasil = app(UbahStatusKertasKerja::class)->nyatakanSiap($kk->fresh());
    expect($hasil->pemblokir)->toBe([sprintf(PesanRkas::SISA, 'Rp 60.000.000')])
        ->and($kk->fresh()->status)->toBe(StatusKertasKerja::Draf);

    $lebih = ($this->kk)();
    ($this->baris)($lebih, [1 => 1], 130_000_000);
    $hasil = app(UbahStatusKertasKerja::class)->nyatakanSiap($lebih->fresh());
    expect($hasil->pemblokir)->toBe([sprintf(PesanRkas::LEBIH, 'Rp 30.000.000')]);
});

it('tetap menyatakan siap walau ada peringatan tahap dan juknis', function () {
    $kk = ($this->kk)();
    ($this->baris)($kk, [1 => 1], 100_000_000);

    $hasil = app(UbahStatusKertasKerja::class)->nyatakanSiap($kk->fresh());

    expect($hasil->lolos())->toBeTrue()
        ->and($hasil->peringatan)->not->toBeEmpty()
        ->and($kk->fresh()->status)->toBe(StatusKertasKerja::Siap);
});

it('hanya memeriksa total dan referensi untuk sumber dana Lainnya', function () {
    $kk = ($this->kk)(100_000_000, SumberDanaRkas::Lainnya);
    ($this->baris)($kk, [9 => 1], 100_000_000);

    $hasil = app(UbahStatusKertasKerja::class)->nyatakanSiap($kk->fresh());

    expect($hasil->lolos())->toBeTrue()
        ->and($kk->fresh()->status)->toBe(StatusKertasKerja::Siap);
});

it('menolak siap bila ada baris dengan referensi bermasalah', function () {
    $kk = ($this->kk)(100_000_000, SumberDanaRkas::Lainnya);
    $baris = ($this->baris)($kk, [9 => 1], 100_000_000);
    $baris->refRekening->update(['kedaluwarsa' => now()->subDay()]);

    $hasil = app(UbahStatusKertasKerja::class)->nyatakanSiap($kk->fresh());

    expect($hasil->pemblokir)->toBe([sprintf(PesanRkas::REFERENSI_BERMASALAH, 1)])
        ->and($kk->fresh()->status)->toBe(StatusKertasKerja::Draf);
});

it('mengembalikan siap ke draf dan menandai disahkan hanya dari siap', function () {
    $ubah = app(UbahStatusKertasKerja::class);

    $draf = ($this->kk)();
    expect(fn () => $ubah->kembalikanKeDraf($draf))->toThrow(ValidationException::class)
        ->and(fn () => $ubah->tandaiDisahkan($draf))->toThrow(ValidationException::class);

    $siap = ($this->kk)(status: StatusKertasKerja::Siap);
    $this->post(route('rkas.status.draf', $siap))->assertRedirect();
    expect($siap->fresh()->status)->toBe(StatusKertasKerja::Draf);

    $siap->refresh()->update(['status' => StatusKertasKerja::Siap]);
    $this->post(route('rkas.status.disahkan', $siap))->assertRedirect();
    expect($siap->fresh()->status)->toBe(StatusKertasKerja::Disahkan);

    $this->post(route('rkas.status.draf', $siap))->assertSessionHasErrors(['kertas_kerja' => sprintf(PesanRkas::TERKUNCI, 'Disahkan')]);
    $this->post(route('rkas.status.siap', $siap))->assertSessionHasErrors('kertas_kerja');
    expect($siap->fresh()->status)->toBe(StatusKertasKerja::Disahkan);
});

it('memakai label RKAS di props setelah disahkan', function () {
    $kk = ($this->kk)(status: StatusKertasKerja::Siap);
    $this->post(route('rkas.status.disahkan', $kk));

    $this->get(route('rkas.show', $kk))->assertInertia(fn (Assert $page) => $page
        ->where('kertasKerja.label', 'RKAS')
        ->where('kertasKerja.status', 'disahkan'));
});

it('menolak perubahan status untuk sekolah non-ARKAS', function () {
    $kk = ($this->kk)();
    $this->smp->update(['sistem' => SistemAnggaran::Erkam]);

    $this->post(route('rkas.status.siap', $kk))->assertNotFound();
    expect($kk->fresh()->status)->toBe(StatusKertasKerja::Draf);
});
