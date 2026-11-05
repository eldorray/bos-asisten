<?php

use App\Enums\StatusManual;
use App\Models\Acara;
use App\Models\Foto;
use App\Models\ImporAnggaran;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Kuitansi;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('sekolah arkas tanpa impor bulan itu diberi peringatan', function () {
    $sekolah = Sekolah::factory()->create();
    ImporAnggaran::factory()->create(['sekolah_id' => $sekolah->id, 'tahun' => 2026, 'periode' => 2]);

    $this->get(route('dashboard', ['bulan' => '2026-03']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('bulan', '2026-03')
            ->where('sekolah.0.peringatan', 'PDF ARKAS bulan ini belum diunggah')
            ->where('sekolah.0.kegiatan', []));

    $this->get(route('dashboard', ['bulan' => '2026-02']))
        ->assertInertia(fn (Assert $page) => $page->where('sekolah.0.peringatan', null));
});

test('sekolah erkam tanpa impor tahun itu diberi peringatan rkam', function () {
    $sekolah = Sekolah::factory()->erkam()->create();

    $this->get(route('dashboard', ['bulan' => '2026-03']))
        ->assertInertia(fn (Assert $page) => $page->where('sekolah.0.peringatan', 'RKAM ERKAM tahun ini belum diunggah'));

    ImporAnggaran::factory()->create(['sekolah_id' => $sekolah->id, 'tahun' => 2026, 'periode' => 0]);

    $this->get(route('dashboard', ['bulan' => '2026-03']))
        ->assertInertia(fn (Assert $page) => $page->where('sekolah.0.peringatan', null));
});

test('format bulan salah memakai bulan berjalan', function () {
    $this->travelTo('2026-11-05');

    $this->get(route('dashboard', ['bulan' => 'ngawur']))
        ->assertInertia(fn (Assert $page) => $page->where('bulan', '2026-11'));
});

test('kegiatan tanpa bulan masuk belum berbulan per tahap', function () {
    $sekolah = Sekolah::factory()->erkam()->create();
    Kegiatan::factory()->create(['sekolah_id' => $sekolah->id, 'bulan' => null, 'tahap' => 1, 'nama' => 'Pagar Besi']);
    Kegiatan::factory()->create(['sekolah_id' => $sekolah->id, 'bulan' => null, 'tahap' => 2, 'nama' => 'Kipas']);
    Kegiatan::factory()->create(['sekolah_id' => $sekolah->id, 'bulan' => 3, 'tahap' => 1]);

    $this->get(route('dashboard', ['bulan' => '2026-03']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sekolah.0.kegiatan', 1)
            ->has('sekolah.0.belumBerbulan', 2)
            ->where('sekolah.0.belumBerbulan.0.tahap', 1)
            ->where('sekolah.0.belumBerbulan.0.kegiatan.0.nama', 'Pagar Besi')
            ->where('sekolah.0.belumBerbulan.1.tahap', 2));
});

test('kegiatan jenis lainnya perlu ditinjau sampai jenisnya diubah atau dikonfirmasi', function () {
    $lainnya = JenisKegiatan::factory()->create(['cadangan' => true]);
    $baru = JenisKegiatan::factory()->create();
    $a = Kegiatan::factory()->create(['bulan' => 3, 'jenis_kegiatan_id' => $lainnya->id]);
    $b = Kegiatan::factory()->create(['bulan' => 3, 'jenis_kegiatan_id' => $lainnya->id, 'sekolah_id' => $a->sekolah_id]);

    $perlu = fn () => $this->get(route('dashboard', ['bulan' => '2026-03']))->inertiaProps('sekolah.0.kegiatan');

    expect(collect($perlu())->pluck('perluDitinjau')->all())->toBe([true, true]);

    $this->patch(route('kegiatan.update', $a), ['jenis_kegiatan_id' => $baru->id])->assertSessionHasNoErrors();
    $b->update(['jenis_dikonfirmasi' => true]);

    expect($a->fresh()->jenis_dikonfirmasi)->toBeTrue()
        ->and(collect($perlu())->pluck('perluDitinjau')->all())->toBe([false, false]);
});

test('bulan kegiatan erkam hanya boleh dalam tahapnya', function () {
    $sekolah = Sekolah::factory()->erkam()->create();
    $kegiatan = Kegiatan::factory()->create(['sekolah_id' => $sekolah->id, 'bulan' => null, 'tahap' => 1]);

    $this->patch(route('kegiatan.update', $kegiatan), ['bulan' => 8])->assertSessionHasErrors('bulan');
    expect($kegiatan->fresh()->bulan)->toBeNull();

    $this->patch(route('kegiatan.update', $kegiatan), ['bulan' => 3])->assertSessionHasNoErrors();
    expect($kegiatan->fresh()->bulan)->toBe(3);
});

test('bulan kegiatan arkas tidak bisa diubah', function () {
    $kegiatan = Kegiatan::factory()->create(['bulan' => 3]);

    $this->patch(route('kegiatan.update', $kegiatan), ['bulan' => 4])->assertSessionHasErrors('bulan');
    expect($kegiatan->fresh()->bulan)->toBe(3);
});

test('bulan kegiatan erkam yang punya kuitansi atau acara tidak bisa diubah', function () {
    $sekolah = Sekolah::factory()->erkam()->create();
    $denganKuitansi = Kegiatan::factory()->create(['sekolah_id' => $sekolah->id, 'bulan' => 2, 'tahap' => 1]);
    $denganKuitansi->kuitansi()->attach(Kuitansi::factory()->create(['sekolah_id' => $sekolah->id, 'bulan' => 2]));
    $acara = Acara::factory()->create(['sekolah_id' => $sekolah->id]);
    $denganAcara = Kegiatan::factory()->create(['sekolah_id' => $sekolah->id, 'bulan' => 2, 'tahap' => 1, 'acara_id' => $acara->id]);

    $this->patch(route('kegiatan.update', $denganKuitansi), ['bulan' => 3])->assertSessionHasErrors('bulan');
    $this->patch(route('kegiatan.update', $denganAcara), ['bulan' => 3])->assertSessionHasErrors('bulan');

    expect($denganKuitansi->fresh()->bulan)->toBe(2)->and($denganAcara->fresh()->bulan)->toBe(2);
});

test('tandai massal di luar aplikasi mengeluarkan baris dari tunggakan dan bisa dibatalkan', function () {
    $sekolah = Sekolah::factory()->create();
    $baris = Kegiatan::factory()->count(3)->create(['sekolah_id' => $sekolah->id, 'bulan' => 3]);
    $tunggakan = fn () => $this->get(route('dashboard', ['bulan' => '2026-03']))->inertiaProps('menunggakPerBulan');

    expect($tunggakan()[3])->toBe(3);

    $ids = $baris->take(2)->pluck('id')->all();
    $this->post(route('kegiatan.tandai'), ['ids' => $ids, 'status_manual' => 'di_luar_aplikasi'])->assertSessionHasNoErrors();

    expect($tunggakan()[3])->toBe(1)
        ->and(Kegiatan::whereIn('id', $ids)->pluck('status_manual')->unique()->all())->toBe([StatusManual::DiLuarAplikasi]);

    $this->get(route('dashboard', ['bulan' => '2026-03']))
        ->assertInertia(fn (Assert $page) => $page->where('ringkasan', ['selesai' => 2, 'kurang' => 0, 'belum' => 1]));

    $this->post(route('kegiatan.tandai'), ['ids' => $ids, 'status_manual' => null])->assertSessionHasNoErrors();

    expect($tunggakan()[3])->toBe(3)
        ->and(Kegiatan::whereIn('id', $ids)->whereNotNull('status_manual')->count())->toBe(0);
});

test('menunggak per bulan berisi dua belas bulan', function () {
    $this->get(route('dashboard', ['bulan' => '2026-03']))
        ->assertInertia(fn (Assert $page) => $page->has('menunggakPerBulan', 12));
});

test('kegiatan yang hilang dari anggaran tetap tampil di daftar bulan', function () {
    $kegiatan = Kegiatan::factory()->create(['bulan' => 3, 'hilang_dari_anggaran' => true, 'status_manual' => StatusManual::DiLuarAplikasi]);

    $this->get(route('dashboard', ['bulan' => '2026-03']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sekolah.0.kegiatan.0.id', $kegiatan->id)
            ->where('sekolah.0.kegiatan.0.hilangDariAnggaran', true));
});

test('kegiatan dihapus hanya kalau sudah hilang dari anggaran', function () {
    $aman = Kegiatan::factory()->create(['hilang_dari_anggaran' => false]);

    $this->delete(route('kegiatan.destroy', $aman))->assertStatus(422);
    expect(Kegiatan::find($aman->id))->not->toBeNull();
});

test('menghapus kegiatan yang hilang menghapus foto, file, dan kuitansi miliknya sendiri', function () {
    Storage::fake('local');
    $kegiatan = Kegiatan::factory()->create(['hilang_dari_anggaran' => true]);
    Storage::disk('local')->put('foto/a.jpg', 'x');
    $foto = Foto::factory()->create(['pemilik_type' => Kegiatan::class, 'pemilik_id' => $kegiatan->id, 'path' => 'foto/a.jpg']);
    $kuitansi = Kuitansi::factory()->create(['sekolah_id' => $kegiatan->sekolah_id]);
    $kegiatan->kuitansi()->attach($kuitansi);

    $this->delete(route('kegiatan.destroy', $kegiatan))->assertRedirect(route('dashboard'));

    expect(Kegiatan::find($kegiatan->id))->toBeNull()
        ->and(Foto::find($foto->id))->toBeNull()
        ->and(Storage::disk('local')->exists('foto/a.jpg'))->toBeFalse()
        ->and(DB::table('kegiatan_kuitansi')->where('kegiatan_id', $kegiatan->id)->count())->toBe(0)
        ->and(Kuitansi::find($kuitansi->id))->toBeNull();
});

test('halaman rincian kegiatan tampil', function () {
    $kegiatan = Kegiatan::factory()->create();

    $this->get(route('kegiatan.show', $kegiatan))
        ->assertInertia(fn (Assert $page) => $page->component('kegiatan/Show')->where('kegiatan.id', $kegiatan->id));
});

test('tamu tidak bisa memakai layar bulan', function () {
    auth()->logout();
    $kegiatan = Kegiatan::factory()->create();

    $this->post(route('kegiatan.tandai'), ['ids' => [$kegiatan->id], 'status_manual' => 'di_luar_aplikasi'])->assertRedirect(route('login'));
});
