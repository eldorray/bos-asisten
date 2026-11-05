<?php

use App\Models\Acara;
use App\Models\Foto;
use App\Models\JenisBerkas;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

const PESAN_FORMAT = 'Format foto tidak didukung. Ubah ke JPG lalu unggah lagi.';

/**
 * @return array{0: Kegiatan, 1: JenisBerkas}
 */
function kegiatanDenganBerkas(): array
{
    $jenis = JenisKegiatan::factory()->create();
    $berkas = JenisBerkas::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'nama' => 'Struk bayar', 'urutan' => 1]);

    return [Kegiatan::factory()->create(['jenis_kegiatan_id' => $jenis->id]), $berkas];
}

function jpegBytes(int $lebar, int $tinggi, int $orientasi = 1): string
{
    $gambar = imagecreatetruecolor($lebar, $tinggi);
    imagefilledrectangle($gambar, 0, 0, $lebar - 1, $tinggi - 1, imagecolorallocate($gambar, 200, 30, 30));
    ob_start();
    imagejpeg($gambar, null, 90);
    $jpeg = (string) ob_get_clean();

    if ($orientasi === 1) {
        return $jpeg;
    }

    // Sisipkan segmen APP1 EXIF (little endian, satu entri Orientation) tepat setelah SOI.
    $tiff = 'II'.pack('vV', 42, 8).pack('v', 1).pack('vvVv', 0x0112, 3, 1, $orientasi).pack('v', 0).pack('V', 0);
    $isi = "Exif\0\0".$tiff;

    return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($isi) + 2).$isi.substr($jpeg, 2);
}

function unggah(Kegiatan $kegiatan, JenisBerkas $berkas, UploadedFile $file)
{
    return test()->post(route('foto.store', $kegiatan), ['jenis_berkas_id' => $berkas->id, 'foto' => $file]);
}

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
});

test('foto tersimpan privat dengan ukuran dan pemilik kegiatan', function () {
    [$kegiatan, $berkas] = kegiatanDenganBerkas();

    unggah($kegiatan, $berkas, UploadedFile::fake()->createWithContent('struk.jpg', jpegBytes(800, 600)))
        ->assertSessionHasNoErrors();

    $foto = Foto::firstOrFail();
    expect($foto->pemilik_type)->toBe(Kegiatan::class)
        ->and($foto->pemilik_id)->toBe($kegiatan->id)
        ->and($foto->jenis_berkas_id)->toBe($berkas->id)
        ->and($foto->lebar)->toBe(800)
        ->and($foto->tinggi)->toBe(600)
        ->and($foto->urutan)->toBe(1)
        ->and($foto->path)->toMatch('#^foto/\d{4}/[0-9a-f-]{36}\.jpg$#');
    Storage::disk('local')->assertExists($foto->path);
    expect(file_exists(public_path($foto->path)))->toBeFalse();
});

test('kegiatan beracara menyimpan foto atas nama acara dan urutan berlanjut', function () {
    [$kegiatan, $berkas] = kegiatanDenganBerkas();
    $acara = Acara::factory()->create(['sekolah_id' => $kegiatan->sekolah_id, 'jenis_kegiatan_id' => $kegiatan->jenis_kegiatan_id]);
    $kegiatan->update(['acara_id' => $acara->id]);

    unggah($kegiatan, $berkas, UploadedFile::fake()->createWithContent('a.jpg', jpegBytes(400, 300)));
    unggah($kegiatan, $berkas, UploadedFile::fake()->createWithContent('b.jpg', jpegBytes(400, 300)));

    expect(Foto::count())->toBe(2)
        ->and(Foto::pluck('pemilik_type')->unique()->all())->toBe([Acara::class])
        ->and(Foto::orderBy('id')->pluck('urutan')->all())->toBe([1, 2]);
});

test('jpeg kecil tanpa orientasi disimpan apa adanya', function () {
    [$kegiatan, $berkas] = kegiatanDenganBerkas();
    $bytes = jpegBytes(640, 480);

    unggah($kegiatan, $berkas, UploadedFile::fake()->createWithContent('a.jpg', $bytes));

    expect(Storage::disk('local')->get(Foto::firstOrFail()->path))->toBe($bytes);
});

test('jpeg besar dengan orientasi 6 tersimpan tegak dan maksimal 1600 px', function () {
    if (! function_exists('exif_read_data')) {
        $this->markTestSkipped('Ekstensi exif tidak tersedia.');
    }

    [$kegiatan, $berkas] = kegiatanDenganBerkas();

    unggah($kegiatan, $berkas, UploadedFile::fake()->createWithContent('hp.jpg', jpegBytes(2000, 1000, 6)))
        ->assertSessionHasNoErrors();

    $foto = Foto::firstOrFail();
    expect($foto->lebar)->toBeLessThan($foto->tinggi)
        ->and($foto->tinggi)->toBe(1600)
        ->and($foto->lebar)->toBe(800);

    [$lebar, $tinggi] = getimagesizefromstring(Storage::disk('local')->get($foto->path));
    expect([$lebar, $tinggi])->toBe([800, 1600]);
});

test('png diubah menjadi jpeg', function () {
    [$kegiatan, $berkas] = kegiatanDenganBerkas();
    $png = imagecreatetruecolor(300, 200);
    ob_start();
    imagepng($png);
    $bytes = (string) ob_get_clean();

    unggah($kegiatan, $berkas, UploadedFile::fake()->createWithContent('a.png', $bytes))->assertSessionHasNoErrors();

    $foto = Foto::firstOrFail();
    expect(getimagesizefromstring(Storage::disk('local')->get($foto->path))[2])->toBe(IMAGETYPE_JPEG)
        ->and($foto->path)->toEndWith('.jpg');
});

test('heic dan pdf ditolak', function () {
    [$kegiatan, $berkas] = kegiatanDenganBerkas();

    unggah($kegiatan, $berkas, UploadedFile::fake()->create('a.heic', 100, 'image/heic'))
        ->assertSessionHasErrors(['foto' => PESAN_FORMAT]);
    unggah($kegiatan, $berkas, UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'))
        ->assertSessionHasErrors('foto');

    expect(Foto::count())->toBe(0);
});

test('file lebih dari 15 MB ditolak', function () {
    [$kegiatan, $berkas] = kegiatanDenganBerkas();

    unggah($kegiatan, $berkas, UploadedFile::fake()->create('besar.jpg', 15 * 1024 + 1, 'image/jpeg'))
        ->assertSessionHasErrors('foto');

    expect(Foto::count())->toBe(0);
});

test('gambar rusak yang lolos pemeriksaan tipe menjadi galat validasi', function () {
    [$kegiatan, $berkas] = kegiatanDenganBerkas();
    $rusak = "\x89PNG\r\n\x1a\n".str_repeat('x', 200);

    unggah($kegiatan, $berkas, UploadedFile::fake()->createWithContent('a.png', $rusak))
        ->assertSessionHasErrors(['foto' => PESAN_FORMAT]);

    expect(Foto::count())->toBe(0);
});

test('jenis berkas milik jenis kegiatan lain ditolak', function () {
    [$kegiatan] = kegiatanDenganBerkas();
    $lain = JenisBerkas::factory()->create();

    unggah($kegiatan, $lain, UploadedFile::fake()->createWithContent('a.jpg', jpegBytes(100, 100)))
        ->assertSessionHasErrors('jenis_berkas_id');

    expect(Foto::count())->toBe(0);
});

test('tamu diarahkan ke login dan pengguna boleh melihat foto', function () {
    [$kegiatan, $berkas] = kegiatanDenganBerkas();
    unggah($kegiatan, $berkas, UploadedFile::fake()->createWithContent('a.jpg', jpegBytes(100, 100)));
    $foto = Foto::firstOrFail();

    $this->get(route('foto.show', $foto))->assertOk();

    auth()->logout();
    $this->get(route('foto.show', $foto))->assertRedirect(route('login'));
    $this->post(route('foto.store', $kegiatan), [])->assertRedirect(route('login'));
    $this->delete(route('foto.destroy', $foto))->assertRedirect(route('login'));
});

test('menghapus foto menghapus berkasnya', function () {
    [$kegiatan, $berkas] = kegiatanDenganBerkas();
    unggah($kegiatan, $berkas, UploadedFile::fake()->createWithContent('a.jpg', jpegBytes(100, 100)));
    $foto = Foto::firstOrFail();

    $this->delete(route('foto.destroy', $foto))->assertRedirect();

    expect(Foto::count())->toBe(0);
    Storage::disk('local')->assertMissing($foto->path);
});
