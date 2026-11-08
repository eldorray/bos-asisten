<?php

use App\Models\Foto;
use App\Models\Kegiatan;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
});

test('sekolah terhapus beserta kegiatan dan file fotonya', function () {
    $sekolah = Sekolah::factory()->create(['nama' => 'MI Contoh']);
    $lain = Sekolah::factory()->create();
    $kegiatan = Kegiatan::factory()->create(['sekolah_id' => $sekolah->id]);
    $milikLain = Kegiatan::factory()->create(['sekolah_id' => $lain->id]);

    Storage::disk('local')->put('foto/a.jpg', 'x');
    Storage::disk('local')->put('foto/b.jpg', 'x');
    $foto = Foto::factory()->create(['pemilik_type' => $kegiatan->getMorphClass(), 'pemilik_id' => $kegiatan->id, 'path' => 'foto/a.jpg']);
    $fotoLain = Foto::factory()->create(['pemilik_type' => $milikLain->getMorphClass(), 'pemilik_id' => $milikLain->id, 'path' => 'foto/b.jpg']);

    $this->delete(route('sekolah.destroy', $sekolah), ['konfirmasi' => 'MI Contoh'])
        ->assertRedirect(route('sekolah.index'));

    expect(Sekolah::find($sekolah->id))->toBeNull()
        ->and(Kegiatan::find($kegiatan->id))->toBeNull()
        ->and(Foto::find($foto->id))->toBeNull()
        ->and(Storage::disk('local')->exists('foto/a.jpg'))->toBeFalse()
        ->and(Sekolah::find($lain->id))->not->toBeNull()
        ->and(Foto::find($fotoLain->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists('foto/b.jpg'))->toBeTrue();
});

test('nama konfirmasi yang salah tidak menghapus apa pun', function () {
    $sekolah = Sekolah::factory()->create(['nama' => 'MI Contoh']);

    $this->delete(route('sekolah.destroy', $sekolah), ['konfirmasi' => 'mi salah'])
        ->assertSessionHasErrors('konfirmasi');
    $this->delete(route('sekolah.destroy', $sekolah))->assertSessionHasErrors('konfirmasi');

    expect(Sekolah::find($sekolah->id))->not->toBeNull();
});

test('tamu tidak bisa menghapus sekolah', function () {
    auth()->logout();
    $sekolah = Sekolah::factory()->create(['nama' => 'MI Contoh']);

    $this->delete(route('sekolah.destroy', $sekolah), ['konfirmasi' => 'MI Contoh'])
        ->assertRedirect(route('login'));

    expect(Sekolah::find($sekolah->id))->not->toBeNull();
});
