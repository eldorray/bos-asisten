<?php

use App\Models\JenisBerkas;
use App\Models\Kegiatan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function jpegUji(): UploadedFile
{
    ob_start();
    imagejpeg(imagecreatetruecolor(20, 20));

    return UploadedFile::fake()->createWithContent('nota.jpg', (string) ob_get_clean());
}

test('halaman memuat manifest aplikasi', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('rel="manifest" href="/manifest.webmanifest"', false);

    expect(json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true))
        ->toMatchArray(['start_url' => '/dashboard', 'display' => 'standalone']);
});

test('halaman lainnya hanya untuk pengguna yang masuk', function () {
    $this->get(route('lainnya'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('lainnya'))
        ->assertInertia(fn (Assert $page) => $page->component('Lainnya'));
});

test('antrean foto menyimpan tanpa memindahkan halaman', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
    $kegiatan = Kegiatan::factory()->create();
    $berkas = JenisBerkas::factory()->create(['jenis_kegiatan_id' => $kegiatan->jenis_kegiatan_id]);

    $this->postJson(route('foto.store', $kegiatan), ['jenis_berkas_id' => $berkas->id, 'foto' => jpegUji()])
        ->assertNoContent();

    expect($kegiatan->fotos()->count())->toBe(1);
});

test('antrean foto mendapat pesan galat dan status sesi sebagai json', function () {
    $kegiatan = Kegiatan::factory()->create();
    $berkas = JenisBerkas::factory()->create(['jenis_kegiatan_id' => $kegiatan->jenis_kegiatan_id]);

    $this->postJson(route('foto.store', $kegiatan), ['jenis_berkas_id' => $berkas->id, 'foto' => jpegUji()])
        ->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->postJson(route('foto.store', $kegiatan), ['jenis_berkas_id' => $berkas->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('foto');
});
