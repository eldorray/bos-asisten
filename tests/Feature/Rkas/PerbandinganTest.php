<?php

declare(strict_types=1);

use App\Enums\SistemAnggaran;
use App\Enums\StatusKertasKerja;
use App\Enums\SumberDanaRkas;
use App\Models\ImporAnggaran;
use App\Models\Kegiatan;
use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;
use App\Models\RkasRefKegiatan;
use App\Models\RkasRefRekening;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\DataAwalSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(DataAwalSeeder::class);
    $this->smp = Sekolah::where('nama', 'like', '%Garuda%')->firstOrFail();

    $this->kertas = fn (array $kk = []): RkasKertasKerja => RkasKertasKerja::factory()->create($kk + [
        'sekolah_id' => $this->smp->id, 'tahun' => 2027, 'pagu' => 100_000_000, 'sumber_dana' => SumberDanaRkas::BospReguler,
    ]);

    $this->baris = function (RkasKertasKerja $kk, string $kode, string $uraian, int $harga, int $volume): RkasBaris {
        $kegiatan = RkasRefKegiatan::firstOrCreate(['tahun' => 2027, 'kode' => '07.01.01.'], ['uraian' => 'Kegiatan', 'level' => 3]);
        $rekening = RkasRefRekening::firstOrCreate(['tahun' => 2027, 'kode' => $kode], ['nama' => 'Rekening '.$kode]);

        return RkasBaris::factory()->create([
            'kertas_kerja_id' => $kk->id, 'ref_kegiatan_id' => $kegiatan->id, 'ref_rekening_id' => $rekening->id,
            'uraian' => $uraian, 'harga' => $harga,
            'volume_bulan' => array_replace(array_fill_keys(range(1, 12), 0), [3 => $volume]),
        ]);
    };

    $this->impor = fn (string $kode, string $uraian, int $jumlah, array $lain = []): Kegiatan => Kegiatan::factory()->create($lain + [
        'sekolah_id' => $this->smp->id, 'tahun' => 2027, 'periode_impor' => 3, 'bulan' => 3,
        'kode' => $kode, 'uraian' => $uraian, 'nama' => $uraian, 'harga' => $jumlah, 'jumlah' => $jumlah,
    ]);

    $this->diimpor = fn () => ImporAnggaran::factory()->create(['sekolah_id' => $this->smp->id, 'tahun' => 2027, 'periode' => 3]);
});

it('menampilkan belum diimpor tanpa selisih bila bulan belum diimpor', function () {
    $this->actingAs(User::factory()->create());
    $kk = ($this->kertas)();
    ($this->baris)($kk, '5.1.02.01.01.0001', 'Kertas HVS', 50_000, 2);

    $this->get(route('rkas.perbandingan', [$kk, 'bulan' => 3]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('rkas/Perbandingan')
            ->where('perbandingan.sudahDiimpor', false)
            ->where('perbandingan.hanyaDiDraf', [])
            ->where('perbandingan.hanyaDiImpor', [])
            ->where('perbandingan.berbeda', []));
});

it('mencocokkan baris draf dengan kegiatan impor per rekening dan uraian', function () {
    $this->actingAs(User::factory()->create());
    $kk = ($this->kertas)();
    ($this->baris)($kk, '5.1.02.01.01.0001', 'Kertas  HVS', 50_000, 2);      // sama
    ($this->baris)($kk, '5.1.02.01.01.0002', 'Tinta', 100_000, 3);           // berbeda
    ($this->baris)($kk, '5.1.02.01.01.0003', 'Spidol', 10_000, 1);           // hanya di draf
    ($this->baris)($kk, '5.1.02.01.01.0004', 'Bulan lain', 10_000, 0);       // volume 0 bulan ini: diabaikan

    ($this->diimpor)();
    ($this->impor)('5.1.02.01.01.0001', 'kertas hvs', 100_000);
    ($this->impor)('5.1.02.01.01.0002', 'Tinta', 250_000);
    ($this->impor)('5.1.02.01.01.0005', 'Penghapus', 5_000, ['kode_program' => '06.07.01']);
    ($this->impor)('5.1.02.01.01.0006', 'Hilang', 9_000, ['hilang_dari_anggaran' => true]);
    ($this->impor)('5.1.02.01.01.0001', 'Kertas HVS', 7_000, ['periode_impor' => 4, 'bulan' => 4]);

    $this->get(route('rkas.perbandingan', [$kk, 'bulan' => 3]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('perbandingan.sudahDiimpor', true)
            ->where('perbandingan.totalDraf', 100_000 + 300_000 + 10_000)
            ->where('perbandingan.totalImpor', 100_000 + 250_000 + 5_000)
            ->where('perbandingan.hanyaDiDraf', [['kode_rekening' => '5.1.02.01.01.0003', 'uraian' => 'Spidol', 'jumlah' => 10_000]])
            ->where('perbandingan.hanyaDiImpor', [['kode' => '5.1.02.01.01.0005', 'uraian' => 'Penghapus', 'jumlah' => 5_000, 'kode_program' => '06.07.01']])
            ->where('perbandingan.berbeda', [['kode_rekening' => '5.1.02.01.01.0002', 'uraian' => 'Tinta', 'draf' => 300_000, 'impor' => 250_000]]));
});

it('menjumlahkan baris dengan kunci sama di kedua sisi', function () {
    $this->actingAs(User::factory()->create());
    $kk = ($this->kertas)();
    ($this->baris)($kk, '5.1.02.01.01.0001', 'Gaji', 100, 1);
    ($this->baris)($kk, '5.1.02.01.01.0001', 'gaji', 100, 2);
    ($this->diimpor)();
    ($this->impor)('5.1.02.01.01.0001', 'Gaji', 100);
    ($this->impor)('5.1.02.01.01.0001', 'Gaji', 200);

    $this->get(route('rkas.perbandingan', [$kk, 'bulan' => 3]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('perbandingan.berbeda', [])
            ->where('perbandingan.hanyaDiDraf', [])
            ->where('perbandingan.hanyaDiImpor', []));
});

it('membandingkan versi disahkan terakhir dalam rantai', function () {
    $this->actingAs(User::factory()->create());
    $awal = ($this->kertas)(['status' => StatusKertasKerja::Disahkan]);
    ($this->baris)($awal, '5.1.02.01.01.0001', 'Kertas', 1_000, 1);
    $revisi = ($this->kertas)(['induk_id' => $awal->id, 'status' => StatusKertasKerja::Draf, 'nomor_perubahan' => 1]);
    ($this->baris)($revisi, '5.1.02.01.01.0001', 'Kertas', 9_000, 1);
    ($this->diimpor)();
    ($this->impor)('5.1.02.01.01.0001', 'Kertas', 1_000);

    $this->get(route('rkas.perbandingan', [$revisi, 'bulan' => 3]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pembanding.id', $awal->id)
            ->where('perbandingan.berbeda', []));
});

it('tidak tersedia untuk sumber dana lainnya', function () {
    $this->actingAs(User::factory()->create());
    $kk = ($this->kertas)(['sumber_dana' => SumberDanaRkas::Lainnya]);

    $this->get(route('rkas.perbandingan', [$kk, 'bulan' => 3]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('perbandingan', null));
});

it('menolak bulan tidak valid dan tamu, serta 404 untuk sekolah non ARKAS', function () {
    $kk = ($this->kertas)();
    $this->get(route('rkas.perbandingan', $kk))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create());
    $this->get(route('rkas.perbandingan', [$kk, 'bulan' => 13]))->assertSessionHasErrors('bulan');

    $mi = Sekolah::where('id', '!=', $this->smp->id)->firstOrFail();
    $lain = RkasKertasKerja::factory()->create(['sekolah_id' => $mi->id, 'tahun' => 2027, 'sumber_dana' => SumberDanaRkas::BospReguler]);
    if ($mi->sistem === SistemAnggaran::Arkas) {
        $mi->update(['sistem' => SistemAnggaran::Erkam]);
    }
    $this->get(route('rkas.perbandingan', [$lain, 'bulan' => 3]))->assertNotFound();
});

it('tidak mengubah kegiatans saat membandingkan', function () {
    $this->actingAs(User::factory()->create());
    $kk = ($this->kertas)();
    ($this->baris)($kk, '5.1.02.01.01.0001', 'Kertas', 1_000, 1);
    ($this->diimpor)();
    ($this->impor)('5.1.02.01.01.0001', 'Kertas', 5_000);
    $sebelum = Kegiatan::query()->orderBy('id')->get()->toArray();
    $jumlahImpor = ImporAnggaran::count();

    $this->get(route('rkas.perbandingan', [$kk, 'bulan' => 3]))->assertOk();

    expect(Kegiatan::query()->orderBy('id')->get()->toArray())->toBe($sebelum)
        ->and(ImporAnggaran::count())->toBe($jumlahImpor);
});
