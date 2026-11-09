<?php

use App\Enums\SistemAnggaran;
use App\Models\Foto;
use App\Models\Kegiatan;
use App\Models\RkasKertasKerja;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\DataAwalSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('setiap model beralamat uuid dan terisi otomatis', function () {
    $kegiatan = Kegiatan::factory()->create();

    expect(Str::isUuid($kegiatan->uuid))->toBeTrue()
        ->and(Str::isUuid($kegiatan->sekolah->uuid))->toBeTrue()
        ->and($kegiatan->getRouteKey())->toBe($kegiatan->uuid)
        ->and(route('kegiatan.show', $kegiatan, false))->toBe('/kegiatan/'.$kegiatan->uuid);
});

test('alamat dengan uuid terbuka dan dengan id angka tidak ditemukan', function () {
    $kegiatan = Kegiatan::factory()->create();

    $this->get('/kegiatan/'.$kegiatan->uuid)->assertOk();
    $this->get('/kegiatan/'.$kegiatan->id)->assertNotFound();
    $this->get('/kegiatan/bukan-uuid')->assertNotFound();
});

test('id angka juga ditolak untuk aksi ubah dan hapus', function () {
    $sekolah = Sekolah::factory()->create(['nama' => 'MI Contoh']);

    $this->delete('/pengaturan/sekolah/'.$sekolah->id, ['konfirmasi' => 'MI Contoh'])->assertNotFound();
    expect(Sekolah::find($sekolah->id))->not->toBeNull();
});

test('uuid tidak bisa ditebak dari urutan dan unik antar baris', function () {
    $a = Sekolah::factory()->create();
    $b = Sekolah::factory()->create();

    expect($a->uuid)->not->toBe($b->uuid);
});

test('foto diakses lewat uuid', function () {
    $foto = Foto::factory()->create();

    expect(route('foto.show', $foto, false))->toBe('/foto/'.$foto->uuid);
});

test('props halaman membawa uuid untuk membangun alamat', function () {
    $kegiatan = Kegiatan::factory()->create();

    $this->get(route('kegiatan.show', $kegiatan))
        ->assertInertia(fn (Assert $page) => $page->where('kegiatan.uuid', $kegiatan->uuid));

    $this->get(route('sekolah.index'))
        ->assertInertia(fn (Assert $page) => $page->where('sekolahs.0.uuid', Sekolah::orderBy('nama')->first()->uuid));
});

test('filter sekolah di daftar RKAS memakai uuid', function () {
    $this->seed(DataAwalSeeder::class);
    $smp = Sekolah::where('sistem', SistemAnggaran::Arkas)->firstOrFail();
    $kk = RkasKertasKerja::factory()->create(['sekolah_id' => $smp->id, 'tahun' => 2027]);

    $this->get(route('rkas.index', ['tahun' => 2027, 'sekolah' => $smp->uuid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sekolah.uuid', $smp->uuid)
            ->where('daftar.0.uuid', $kk->uuid));

    $this->get(route('rkas.show', $kk))
        ->assertInertia(fn (Assert $page) => $page
            ->where('kertasKerja.uuid', $kk->uuid)
            ->where('kertasKerja.sekolah.uuid', $smp->uuid));
});
