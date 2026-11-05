<?php

use App\Actions\HitungStatusKegiatan;
use App\Actions\StatusKegiatan;
use App\Enums\StatusManual;
use App\Models\Acara;
use App\Models\Foto;
use App\Models\JenisBerkas;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Kuitansi;
use App\Models\User;

function jenisDenganStruk(): array
{
    $jenis = JenisKegiatan::factory()->create();
    $struk = JenisBerkas::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'nama' => 'Struk bayar', 'urutan' => 1]);

    return [$jenis, $struk];
}

function hitungStatus(Kegiatan $kegiatan): StatusKegiatan
{
    return HitungStatusKegiatan::untuk(Kegiatan::with(HitungStatusKegiatan::RELASI)->findOrFail($kegiatan->id));
}

test('status manual menang atas segalanya', function () {
    [$jenis] = jenisDenganStruk();
    $kegiatan = Kegiatan::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'status_manual' => StatusManual::TidakDilaksanakan, 'catatan_status' => 'Batal']);
    $kegiatan->kuitansi()->attach(Kuitansi::factory()->create(['sekolah_id' => $kegiatan->sekolah_id, 'bulan' => 12]));

    expect(hitungStatus($kegiatan)->status)->toBe('tidak_dilaksanakan');

    $kegiatan->update(['status_manual' => StatusManual::DiLuarAplikasi, 'catatan_status' => null]);

    $status = hitungStatus($kegiatan);
    expect($status->status)->toBe('di_luar_aplikasi')->and($status->menunggak())->toBeFalse();
});

test('kuitansi bulan lebih besar membuat kegiatan dirapel', function () {
    [$jenis] = jenisDenganStruk();
    $kegiatan = Kegiatan::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'bulan' => 3]);
    $kuitansi = Kuitansi::factory()->create(['sekolah_id' => $kegiatan->sekolah_id, 'bulan' => 5]);
    $kegiatan->kuitansi()->attach($kuitansi);

    $status = hitungStatus($kegiatan);

    expect($status->status)->toBe('dirapel')
        ->and($status->kuitansiRapel?->id)->toBe($kuitansi->id)
        ->and($status->menunggak())->toBeFalse();
});

test('kuitansi di bulan yang sama bukan rapel', function () {
    [$jenis] = jenisDenganStruk();
    $kegiatan = Kegiatan::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'bulan' => 3]);
    $kegiatan->kuitansi()->attach(Kuitansi::factory()->create(['sekolah_id' => $kegiatan->sekolah_id, 'bulan' => 3]));

    $status = hitungStatus($kegiatan);

    expect($status->status)->toBe('kurang')->and($status->kuitansiRapel)->toBeNull();
});

test('tanpa satu berkas pun statusnya belum', function () {
    [$jenis] = jenisDenganStruk();
    $kegiatan = Kegiatan::factory()->create(['jenis_kegiatan_id' => $jenis->id]);

    $status = hitungStatus($kegiatan);

    expect($status->status)->toBe('belum')
        ->and($status->kurang)->toBe(2)
        ->and(array_column($status->berkas, 'nama'))->toBe(['Kuitansi', 'Struk bayar'])
        ->and($status->menunggak())->toBeTrue();
});

test('berkas sebagian kurang, lengkap selesai', function () {
    [$jenis, $struk] = jenisDenganStruk();
    $kegiatan = Kegiatan::factory()->create(['jenis_kegiatan_id' => $jenis->id]);
    $kegiatan->kuitansi()->attach(Kuitansi::factory()->create(['sekolah_id' => $kegiatan->sekolah_id, 'bulan' => $kegiatan->bulan]));

    $status = hitungStatus($kegiatan);
    expect($status->status)->toBe('kurang')->and($status->kurang)->toBe(1)->and($status->menunggak())->toBeTrue();

    Foto::factory()->count(2)->create(['pemilik_type' => Kegiatan::class, 'pemilik_id' => $kegiatan->id, 'jenis_berkas_id' => $struk->id]);

    $status = hitungStatus($kegiatan);
    expect($status->status)->toBe('selesai')
        ->and($status->kurang)->toBe(0)
        ->and($status->berkas[1])->toBe(['nama' => 'Struk bayar', 'ada' => true, 'jumlahFoto' => 2]);
});

test('foto acara dihitung untuk semua baris acara', function () {
    $jenis = JenisKegiatan::factory()->create();
    $daftar = JenisBerkas::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'nama' => 'Daftar hadir']);
    $acara = Acara::factory()->create(['jenis_kegiatan_id' => $jenis->id]);
    $a = Kegiatan::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'acara_id' => $acara->id, 'sekolah_id' => $acara->sekolah_id]);
    $b = Kegiatan::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'acara_id' => $acara->id, 'sekolah_id' => $acara->sekolah_id]);
    Foto::factory()->create(['pemilik_type' => Acara::class, 'pemilik_id' => $acara->id, 'jenis_berkas_id' => $daftar->id]);

    expect(hitungStatus($a)->berkas[1]['ada'])->toBeTrue()
        ->and(hitungStatus($b)->berkas[1]['ada'])->toBeTrue();
});

test('catatan wajib saat tidak dilaksanakan', function () {
    $this->actingAs(User::factory()->create());
    $kegiatan = Kegiatan::factory()->create();

    $this->patch(route('kegiatan.update', $kegiatan), ['status_manual' => 'tidak_dilaksanakan'])
        ->assertSessionHasErrors('catatan_status');

    $this->patch(route('kegiatan.update', $kegiatan), ['status_manual' => 'tidak_dilaksanakan', 'catatan_status' => 'Dana dialihkan'])
        ->assertSessionHasNoErrors();

    expect($kegiatan->fresh()->status_manual)->toBe(StatusManual::TidakDilaksanakan)
        ->and($kegiatan->fresh()->catatan_status)->toBe('Dana dialihkan');
});

test('penandaan massal tidak menerima tidak dilaksanakan', function () {
    $this->actingAs(User::factory()->create());
    $kegiatan = Kegiatan::factory()->create();

    $this->post(route('kegiatan.tandai'), ['ids' => [$kegiatan->id], 'status_manual' => 'tidak_dilaksanakan'])
        ->assertSessionHasErrors('status_manual');

    expect($kegiatan->fresh()->status_manual)->toBeNull();
});
