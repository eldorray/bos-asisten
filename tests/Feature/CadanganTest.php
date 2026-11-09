<?php

use App\Actions\BuatCadangan;
use App\Models\JenisKegiatan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// VACUUM INTO tidak boleh jalan di dalam transaksi, sedangkan RefreshDatabase membungkus
// tiap tes dalam satu. Keluar dari transaksi itu, lalu bangun ulang skema sesudahnya
// supaya data tes ini tidak bocor ke tes lain (basis data tes ada di memori).
beforeEach(function () {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

afterEach(fn () => $this->artisan('migrate:fresh'));

function bukaZip(string $path): ZipArchive
{
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();

    return $zip;
}

it('menolak tamu', function () {
    $this->get(route('cadangan.unduh'))->assertRedirect('/login');
});

it('membuat zip berisi salinan basis data dan semua foto', function () {
    Storage::fake('local');
    Storage::disk('local')->put('foto/2026/a.jpg', 'isi-a');
    Storage::disk('local')->put('foto/b.jpg', 'isi-b');
    Storage::disk('local')->put('lain/c.txt', 'bukan foto');
    JenisKegiatan::factory()->create(['nama' => 'Tanda Cadangan']);

    $path = BuatCadangan::jalankan();
    $zip = bukaZip($path);

    expect($zip->getFromName('foto/2026/a.jpg'))->toBe('isi-a')
        ->and($zip->getFromName('foto/b.jpg'))->toBe('isi-b')
        ->and($zip->locateName('lain/c.txt'))->toBeFalse();

    $salinan = tempnam(sys_get_temp_dir(), 'uji-').'.sqlite';
    file_put_contents($salinan, $zip->getFromName('database.sqlite'));
    $zip->close();

    $pdo = new PDO('sqlite:'.$salinan);
    $nama = $pdo->query("select nama from jenis_kegiatans where nama = 'Tanda Cadangan'")->fetchColumn();

    expect($nama)->toBe('Tanda Cadangan');

    unlink($salinan);
    unlink($path);
});

it('tidak meninggalkan salinan basis data sementara', function () {
    $pola = sys_get_temp_dir().'/{cadangan,salinan-db}-*';
    $sebelum = glob($pola, GLOB_BRACE) ?: [];

    $path = BuatCadangan::jalankan();
    $sesudah = array_values(array_diff(glob($pola, GLOB_BRACE) ?: [], $sebelum));

    expect($sesudah)->toHaveCount(1)
        ->and(realpath($sesudah[0]))->toBe(realpath($path));

    unlink($path);
});

it('mengirim zip ke pengguna lalu menghapus file sementara', function () {
    Storage::fake('local');
    Storage::disk('local')->put('foto/a.jpg', 'isi');
    $sebelum = glob(sys_get_temp_dir().'/cadangan-*') ?: [];

    $respons = $this->actingAs(User::factory()->create())->get(route('cadangan.unduh'));

    $respons->assertOk()->assertDownload();
    expect($respons->headers->get('content-disposition'))->toContain('cadangan-spj-')->toContain('.zip');
    expect($respons->headers->get('Cache-Control'))->toContain('no-store');

    $file = $respons->baseResponse->getFile()->getPathname();
    expect(file_exists($file))->toBeTrue();

    ob_start();
    $respons->baseResponse->sendContent();
    ob_end_clean();

    expect(array_diff(glob(sys_get_temp_dir().'/cadangan-*') ?: [], $sebelum))->toBe([]);
});
