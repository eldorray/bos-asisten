<?php

declare(strict_types=1);

use App\Actions\Rkas\HitungRingkasan;
use App\Actions\Rkas\PesanRkas;
use App\Enums\SumberDanaRkas;
use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;
use App\Models\RkasPengaturan;
use App\Models\RkasRefBarang;
use App\Models\RkasRefKegiatan;
use App\Models\RkasRefRekening;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\DataAwalSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(DataAwalSeeder::class);
    $this->smp = Sekolah::where('nama', 'like', '%Garuda%')->firstOrFail();
    $this->actingAs(User::factory()->create());

    $this->kk = fn (int $pagu, SumberDanaRkas $sumber = SumberDanaRkas::BospReguler): RkasKertasKerja => RkasKertasKerja::factory()->create([
        'sekolah_id' => $this->smp->id, 'tahun' => 2027, 'pagu' => $pagu, 'sumber_dana' => $sumber,
    ]);

    $this->baris = function (RkasKertasKerja $kk, array $volume, int $harga, string $kodeKegiatan = '07.01.01.', string $kodeRekening = '5.1.02.01.01.0001', array $lain = []): RkasBaris {
        $kegiatan = RkasRefKegiatan::firstOrCreate(['tahun' => 2027, 'kode' => $kodeKegiatan], ['uraian' => 'K '.$kodeKegiatan, 'level' => 3]);
        $rekening = RkasRefRekening::firstOrCreate(['tahun' => 2027, 'kode' => $kodeRekening], ['nama' => 'R '.$kodeRekening]);

        return RkasBaris::factory()->create(array_replace([
            'kertas_kerja_id' => $kk->id,
            'ref_kegiatan_id' => $kegiatan->id,
            'ref_rekening_id' => $rekening->id,
            'harga' => $harga,
            'volume_bulan' => array_replace(array_fill_keys(range(1, 12), 0), $volume),
        ], $lain));
    };

    $this->hitung = fn (RkasKertasKerja $kk) => app(HitungRingkasan::class)->untuk($kk->fresh());
});

it('membagi pagu ganjil ke tahap dengan pembulatan ke bawah', function () {
    $r = ($this->hitung)(($this->kk)(100_000_001));

    expect($r->tahap[0]['alokasi'])->toBe(50_000_000)
        ->and($r->tahap[1]['alokasi'])->toBe(50_000_001);
});

it('memperingatkan tahap 1 yang melebihi alokasi tanpa memblokir', function () {
    $kk = ($this->kk)(100_000_000);
    ($this->baris)($kk, [1 => 6], 10_000_000);
    ($this->baris)($kk, [7 => 4], 10_000_000, lain: ['urutan' => 2]);

    $r = ($this->hitung)($kk);

    expect($r->peringatan)->toContain(sprintf(PesanRkas::TAHAP_LEBIH, 1, 'Rp 10.000.000'))
        ->and(collect($r->peringatan)->filter(fn ($p) => str_contains($p, 'tahap 2')))->toBeEmpty()
        ->and($r->tahap[0]['selisih'])->toBe(-10_000_000)
        ->and($r->tahap[1]['selisih'])->toBe(10_000_000)
        ->and($r->dianggarkan)->toBe(100_000_000)
        ->and($r->sisa)->toBe(0)
        ->and($r->perBulan[1])->toBe(60_000_000)
        ->and($r->perBulan[7])->toBe(40_000_000);
});

it('memakai persentase tahap dari pengaturan', function () {
    RkasPengaturan::untuk($this->smp, 2027)->update(['persen_tahap_1' => 40]);

    $r = ($this->hitung)(($this->kk)(100_000_000));

    expect($r->tahap[0]['alokasi'])->toBe(40_000_000)
        ->and($r->tahap[1]['alokasi'])->toBe(60_000_000);
});

it('tidak membuat tahap dan juknis untuk sumber dana Lainnya', function () {
    $kk = ($this->kk)(100_000_000, SumberDanaRkas::Lainnya);
    ($this->baris)($kk, [1 => 1], 90_000_000);

    $r = ($this->hitung)($kk);

    expect($r->tahap)->toBe([])
        ->and($r->juknis)->toBe([])
        ->and($r->peringatan)->toBe([]);
});

it('memperingatkan honor di atas 20 persen untuk negeri dan tidak untuk 40 persen swasta', function () {
    $kk = ($this->kk)(100_000_000);
    ($this->baris)($kk, [1 => 1], 25_000_000, '07.12.01.', '5.1.02.02.01.0013');

    $r = ($this->hitung)($kk);
    expect($r->peringatan)->toContain(sprintf(PesanRkas::HONOR, 20))
        ->and($r->juknis[0]['persen'])->toBe(25.0);

    RkasPengaturan::untuk($this->smp, 2027)->update(['swasta' => true]);
    expect(($this->hitung)($kk)->peringatan)->not->toContain(sprintf(PesanRkas::HONOR, 40))
        ->and(($this->hitung)($kk)->peringatan)->not->toContain(sprintf(PesanRkas::HONOR, 20));
});

it('memperingatkan buku di bawah 10 persen', function () {
    $kk = ($this->kk)(100_000_000);
    ($this->baris)($kk, [1 => 1], 9_000_000, '05.02.02.');

    expect(($this->hitung)($kk)->peringatan)->toContain(sprintf(PesanRkas::BUKU, 10));

    ($this->baris)($kk, [1 => 1], 1_000_000, '05.02.02.', lain: ['urutan' => 2]);
    expect(($this->hitung)($kk)->peringatan)->not->toContain(sprintf(PesanRkas::BUKU, 10));
});

it('memperingatkan sarpras di atas 20 persen termasuk rekening mebel', function () {
    $kk = ($this->kk)(100_000_000);
    ($this->baris)($kk, [1 => 1], 12_000_000, '05.08.01.', '5.1.02.03.01.0001');
    ($this->baris)($kk, [1 => 1], 8_000_001, '05.08.01.', '5.2.02.05.02.0001', ['urutan' => 2]);
    // Rekening 5.2 lain di kegiatan sarpras tidak dihitung.
    ($this->baris)($kk, [1 => 1], 50_000_000, '05.08.01.', '5.2.02.10.01.0001');

    expect(($this->hitung)($kk)->peringatan)->toContain(sprintf(PesanRkas::SARPRAS, 20));
});

it('mengelompokkan jenis belanja dari awalan rekening', function () {
    $kk = ($this->kk)(100_000_000);
    ($this->baris)($kk, [1 => 1], 1_000, '07.01.01.', '5.1.02.01.01.0001');
    ($this->baris)($kk, [1 => 1], 2_000, '07.01.01.', '5.2.02.10.01.0001');
    ($this->baris)($kk, [1 => 1], 4_000, '07.01.01.', '5.2.05.01.01.0001');
    ($this->baris)($kk, [1 => 1], 8_000, '07.01.01.', '5.1.02.02.01.0013');
    ($this->baris)($kk, [1 => 1], 16_000, '07.01.01.', '5.1.02.02.01.0002');

    $r = ($this->hitung)($kk);

    expect($r->jenisBelanja)->toBe(['operasional' => 25_000, 'alat_mesin' => 2_000, 'aset' => 4_000])
        ->and($r->rincianBelanja)->toBe([
            'honor' => 8_000, 'barang' => 1_000, 'jasa' => 16_000, 'pemeliharaan' => 0,
            'perjalanan' => 0, 'peralatan_mesin' => 2_000, 'aset_lain' => 4_000,
        ]);
});

it('menghasilkan nol tanpa error untuk pagu nol dan tanpa baris', function () {
    $kk = ($this->kk)(0);
    $r = ($this->hitung)($kk);

    expect($r->dianggarkan)->toBe(0)
        ->and($r->sisa)->toBe(0)
        ->and(collect($r->juknis)->pluck('persen')->all())->toBe([0.0, 0.0, 0.0])
        ->and($r->peringatan)->toBe([])
        ->and($r->jumlahBarisBermasalah)->toBe(0);

    $this->get(route('rkas.show', $kk))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('ringkasan.pagu', 0)->where('ringkasan.jumlah_baris_bermasalah', 0));
});

it('menandai baris yang rekeningnya kedaluwarsa tanpa menghapusnya', function () {
    $kk = ($this->kk)(10_000_000);
    $b = ($this->baris)($kk, [1 => 1], 1_000);
    $b->refRekening->update(['kedaluwarsa' => now()->toDateString()]);
    $b->refKegiatan->update(['kedaluwarsa' => now()->subDay()->toDateString()]);

    $r = ($this->hitung)($kk);

    expect($r->masalahPerBaris[$b->id])->toBe([PesanRkas::KEGIATAN_BERUBAH, PesanRkas::REKENING_BERUBAH])
        ->and($r->jumlahBarisBermasalah)->toBe(1)
        ->and(RkasBaris::count())->toBe(1);

    $this->get(route('rkas.show', $kk))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('baris.0.masalah', [PesanRkas::KEGIATAN_BERUBAH, PesanRkas::REKENING_BERUBAH])
            ->where('ringkasan.jumlah_baris_bermasalah', 1));
});

it('tidak menandai rekening yang kedaluwarsa besok', function () {
    $kk = ($this->kk)(10_000_000);
    $b = ($this->baris)($kk, [1 => 1], 1_000);
    $b->refRekening->update(['kedaluwarsa' => now()->addDay()->toDateString()]);

    expect(($this->hitung)($kk)->masalahPerBaris)->toBe([]);
});

it('menandai barang kedaluwarsa, uraian beda, dan harga di luar batas', function () {
    $kk = ($this->kk)(10_000_000);
    $barang = RkasRefBarang::factory()->create(['kode_rekening' => '5.1.02.01.01.0001', 'nama' => 'Kertas HVS', 'batas_bawah' => 1_000, 'batas_atas' => 5_000]);

    $ok = ($this->baris)($kk, [1 => 1], 3_000, lain: ['ref_barang_id' => $barang->id, 'uraian' => 'Kertas HVS']);
    $murah = ($this->baris)($kk, [1 => 1], 500, lain: ['ref_barang_id' => $barang->id, 'uraian' => 'Kertas HVS', 'urutan' => 2]);
    $mahal = ($this->baris)($kk, [1 => 1], 5_001, lain: ['ref_barang_id' => $barang->id, 'uraian' => 'Kertas HVS', 'urutan' => 3]);
    $beda = ($this->baris)($kk, [1 => 1], 3_000, lain: ['ref_barang_id' => $barang->id, 'uraian' => 'Lain', 'urutan' => 4]);

    $r = ($this->hitung)($kk);
    expect($r->masalahPerBaris)->not->toHaveKey($ok->id)
        ->and($r->masalahPerBaris[$murah->id])->toBe([PesanRkas::BATAS_BERUBAH])
        ->and($r->masalahPerBaris[$mahal->id])->toBe([PesanRkas::BATAS_BERUBAH])
        ->and($r->masalahPerBaris[$beda->id])->toBe([PesanRkas::BARANG_BERUBAH]);

    $barang->update(['kedaluwarsa' => now()->toDateString()]);
    expect(($this->hitung)($kk)->masalahPerBaris[$ok->id])->toBe([PesanRkas::BARANG_BERUBAH]);
});

it('hanya menandai baris tanpa barang bila daftar barang yang cocok ada', function () {
    $kk = ($this->kk)(10_000_000);
    $teks = ($this->baris)($kk, [1 => 1], 1_000, '07.01.01.', '5.2.02.10.01.0001', ['uraian' => 'Laptop']);
    $honor = ($this->baris)($kk, [1 => 1], 1_000, '07.12.01.', '5.2.02.10.01.0001', ['uraian' => 'Honor', 'urutan' => 2]);

    expect(($this->hitung)($kk)->masalahPerBaris)->toBe([]);

    // Daftar milik tahun lain tidak berpengaruh.
    RkasRefBarang::factory()->create(['tahun' => 2026, 'kode_rekening' => null]);
    expect(($this->hitung)($kk)->masalahPerBaris)->toBe([]);

    RkasRefBarang::factory()->create(['tahun' => 2027, 'kode_rekening' => null]);
    $r = ($this->hitung)($kk);
    expect($r->masalahPerBaris[$teks->id])->toBe([PesanRkas::BARANG_BERUBAH])
        ->and($r->masalahPerBaris)->not->toHaveKey($honor->id);
});

it('menampilkan nilai riil dianggarkan dan sisa di daftar kertas kerja', function () {
    $kk = ($this->kk)(10_000_000);
    ($this->baris)($kk, [1 => 2, 7 => 1], 1_000_000);

    $this->get(route('rkas.index', ['tahun' => 2027, 'sekolah_id' => $this->smp->id]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('daftar.0.dianggarkan', 3_000_000)->where('daftar.0.sisa', 7_000_000));
});
