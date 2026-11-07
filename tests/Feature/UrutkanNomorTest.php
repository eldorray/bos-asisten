<?php

use App\Models\Kuitansi;
use App\Models\Sekolah;
use App\Models\User;

function kuitansiNomor(Sekolah $sekolah, string $nomor, int $bulan, string $lunas, int $tahun = 2026): Kuitansi
{
    return Kuitansi::factory()->create([
        'sekolah_id' => $sekolah->id,
        'tahun' => $tahun,
        'bulan' => $bulan,
        'nomor' => $nomor,
        'nomor_urut' => (int) $nomor,
        'tanggal_lunas' => $lunas,
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('nomor bukti diurutkan per bulan lalu tanggal lunas', function () {
    $sekolah = Sekolah::factory()->create(['kode_bukti' => 'MIDH']);
    $februari = kuitansiNomor($sekolah, '001/T1/MIDH/2026', 2, '2026-02-10');
    $januariAkhir = kuitansiNomor($sekolah, '002/T1/MIDH/2026', 1, '2026-01-20');
    $januariAwal = kuitansiNomor($sekolah, '003/T1/MIDH/2026', 1, '2026-01-05');

    $this->post(route('sekolah.urutkan-nomor', $sekolah), ['tahun' => 2026])->assertSessionHasNoErrors();

    expect($januariAwal->fresh()->nomor)->toBe('001/T1/MIDH/2026')
        ->and($januariAkhir->fresh()->nomor)->toBe('002/T1/MIDH/2026')
        ->and($februari->fresh()->nomor)->toBe('003/T1/MIDH/2026')
        ->and($februari->fresh()->nomor_urut)->toBe(3);
});

test('urutkan ulang dua kali tidak mengubah apa pun, dan mengikuti nomor awal', function () {
    $sekolah = Sekolah::factory()->create(['nomor_awal' => ['2026' => 5]]);
    $a = kuitansiNomor($sekolah, '009/T1/X/2026', 2, '2026-02-10');
    $b = kuitansiNomor($sekolah, '010/T1/X/2026', 1, '2026-01-10');

    $this->post(route('sekolah.urutkan-nomor', $sekolah), ['tahun' => 2026]);
    $nomor = [$b->fresh()->nomor, $a->fresh()->nomor];

    expect($nomor)->toBe(['005/T1/X/2026', '006/T1/X/2026']);

    $this->post(route('sekolah.urutkan-nomor', $sekolah), ['tahun' => 2026]);

    expect([$b->fresh()->nomor, $a->fresh()->nomor])->toBe($nomor);
});

test('sekolah dan tahun lain tidak tersentuh', function () {
    $sekolah = Sekolah::factory()->create();
    $lain = Sekolah::factory()->create();
    kuitansiNomor($sekolah, '007/T1/X/2026', 3, '2026-03-01');
    $milikLain = kuitansiNomor($lain, '007/T1/Y/2026', 3, '2026-03-01');
    $tahunLain = kuitansiNomor($sekolah, '004/T1/X/2025', 3, '2025-03-01', 2025);

    $this->post(route('sekolah.urutkan-nomor', $sekolah), ['tahun' => 2026]);

    expect($milikLain->fresh()->nomor)->toBe('007/T1/Y/2026')
        ->and($tahunLain->fresh()->nomor)->toBe('004/T1/X/2025');
});

test('tamu ditolak', function () {
    auth()->logout();

    $this->post(route('sekolah.urutkan-nomor', Sekolah::factory()->create()), ['tahun' => 2026])->assertRedirect(route('login'));
});
