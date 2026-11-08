<?php

declare(strict_types=1);

use App\Enums\StatusKertasKerja;
use App\Enums\SumberDanaRkas;
use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;
use App\Models\RkasPengaturan;
use App\Models\RkasRefKegiatan;
use App\Models\Sekolah;
use Database\Seeders\DataAwalSeeder;

it('menghitung volume dan jumlah baris dari volume per bulan', function () {
    $baris = RkasBaris::factory()->make([
        'harga' => 15_000,
        'volume_bulan' => array_replace(array_fill_keys(range(1, 12), 0), [1 => 2, 7 => 3]),
    ]);

    expect($baris->volume())->toBe(5)
        ->and($baris->jumlah())->toBe(75_000)
        ->and($baris->jumlahBulan(7))->toBe(45_000);
});

it('membuat pengaturan tahun dengan nilai awal juknis', function () {
    $this->seed(DataAwalSeeder::class);
    $smp = Sekolah::where('nama', 'like', '%Garuda%')->firstOrFail();

    $pengaturan = RkasPengaturan::untuk($smp, 2027);

    expect($pengaturan->persen_tahap_1)->toBe(50)
        ->and($pengaturan->ambang_tahap_1)->toBe(25)
        ->and($pengaturan->kode_honor)->toBe(['07.12.01.', '07.12.02.', '07.12.03.', '07.12.04.'])
        ->and($pengaturan->kuota_pergeseran)->toBeNull()
        ->and(RkasPengaturan::untuk($smp, 2027)->id)->toBe($pengaturan->id);
});

it('memakai kegiatan BOSP Reguler saat sumber dana Lainnya tidak punya kode sendiri', function () {
    RkasRefKegiatan::factory()->create(['tahun' => 2027, 'level' => 3]);
    RkasRefKegiatan::factory()->create(['tahun' => 2027, 'level' => 3, 'kedaluwarsa' => now()->subDay()]);
    RkasRefKegiatan::factory()->create(['tahun' => 2027, 'level' => 3, 'kedaluwarsa' => now()->toDateString()]);

    expect(RkasRefKegiatan::tersediaUntuk(2027, SumberDanaRkas::Lainnya)->count())->toBe(1);
});

it('memberi label RKAS setelah disahkan', function () {
    $kertasKerja = RkasKertasKerja::factory()->create();

    expect($kertasKerja->label())->toBe('Kertas Kerja')
        ->and($kertasKerja->bisaDiubah())->toBeTrue();

    $kertasKerja->update(['status' => StatusKertasKerja::Disahkan]);

    expect($kertasKerja->label())->toBe('RKAS')
        ->and($kertasKerja->bisaDiubah())->toBeFalse();
});
