<?php

use App\Http\Controllers\AcaraController;
use App\Http\Controllers\BulanController;
use App\Http\Controllers\CadanganController;
use App\Http\Controllers\FotoController;
use App\Http\Controllers\ImporController;
use App\Http\Controllers\JenisKegiatanController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\KuitansiController;
use App\Http\Controllers\PdfBulananController;
use App\Http\Controllers\PdfKegiatanController;
use App\Http\Controllers\Rkas\BarisRkasController;
use App\Http\Controllers\Rkas\KertasKerjaController;
use App\Http\Controllers\Rkas\PdfKertasKerjaController;
use App\Http\Controllers\Rkas\PengaturanRkasController;
use App\Http\Controllers\Rkas\PerbandinganController;
use App\Http\Controllers\Rkas\ReferensiRkasController;
use App\Http\Controllers\Rkas\RevisiController;
use App\Http\Controllers\Rkas\StatusKertasKerjaController;
use App\Http\Controllers\SekolahController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', BulanController::class)->name('dashboard');

    Route::get('kegiatan/{kegiatan}', [KegiatanController::class, 'show'])->name('kegiatan.show');
    Route::patch('kegiatan/{kegiatan}', [KegiatanController::class, 'update'])->name('kegiatan.update');
    Route::delete('kegiatan/{kegiatan}', [KegiatanController::class, 'destroy'])->name('kegiatan.destroy');
    Route::post('kegiatan/{kegiatan}/foto', [FotoController::class, 'store'])->name('foto.store');
    Route::get('foto/{foto}', [FotoController::class, 'show'])->name('foto.show');
    Route::delete('foto/{foto}', [FotoController::class, 'destroy'])->name('foto.destroy');
    Route::post('kegiatan/{kegiatan}/kuitansi', [KuitansiController::class, 'store'])->name('kuitansi.store');
    Route::patch('kuitansi/{kuitansi}', [KuitansiController::class, 'update'])->name('kuitansi.update');
    Route::delete('kuitansi/{kuitansi}', [KuitansiController::class, 'destroy'])->name('kuitansi.destroy');
    Route::post('acara', [AcaraController::class, 'store'])->name('acara.store');
    Route::delete('acara/{acara}', [AcaraController::class, 'destroy'])->name('acara.destroy');
    Route::post('kegiatan-tandai', [KegiatanController::class, 'tandai'])->name('kegiatan.tandai');

    Route::get('sekolah/{sekolah}/pdf/{tahun}/{bulan}', PdfBulananController::class)
        ->where(['tahun' => '\d{4}', 'bulan' => '0?[1-9]|1[0-2]'])
        ->name('pdf.bulanan');

    Route::get('kegiatan/{kegiatan}/pdf', PdfKegiatanController::class)->name('pdf.kegiatan');

    Route::get('impor', [ImporController::class, 'index'])->name('impor.index');
    Route::post('impor/pratinjau', [ImporController::class, 'pratinjau'])->name('impor.pratinjau');
    Route::post('impor/simpan', [ImporController::class, 'simpan'])->name('impor.simpan');

    Route::get('pengaturan/sekolah', [SekolahController::class, 'index'])->name('sekolah.index');
    Route::patch('pengaturan/sekolah/{sekolah}', [SekolahController::class, 'update'])->name('sekolah.update');
    Route::delete('pengaturan/sekolah/{sekolah}', [SekolahController::class, 'destroy'])->name('sekolah.destroy');
    Route::post('pengaturan/sekolah/{sekolah}/urutkan-nomor', [SekolahController::class, 'urutkanNomor'])->name('sekolah.urutkan-nomor');
    Route::get('pengaturan/rkas', [PengaturanRkasController::class, 'edit'])->name('rkas.pengaturan.edit');
    Route::patch('pengaturan/rkas/{pengaturan}', [PengaturanRkasController::class, 'update'])->name('rkas.pengaturan.update');
    Route::prefix('pengaturan/rkas/referensi')->name('rkas.referensi.')->group(function () {
        Route::get('/', [ReferensiRkasController::class, 'index'])->name('index');
        Route::post('salin', [ReferensiRkasController::class, 'salin'])->name('salin');
        Route::post('muat-kegiatan', [ReferensiRkasController::class, 'muatKegiatan'])->name('muat-kegiatan');
        Route::post('muat-rekening', [ReferensiRkasController::class, 'muatRekening'])->name('muat-rekening');
        Route::post('{jenis}', [ReferensiRkasController::class, 'store'])
            ->where('jenis', 'kegiatan|rekening|barang|satuan|ptk')->name('store');
        Route::delete('{jenis}/{uuid}', [ReferensiRkasController::class, 'destroy'])
            ->where(['jenis' => 'kegiatan|rekening|barang|satuan|ptk', 'uuid' => '[0-9a-f-]{36}'])->name('destroy');
    });
    Route::resource('rkas', KertasKerjaController::class)
        ->parameters(['rkas' => 'kertasKerja'])
        ->except(['create', 'edit']);
    Route::get('rkas/{kertasKerja}/pdf', PdfKertasKerjaController::class)->name('rkas.pdf');
    Route::get('rkas/{kertasKerja}/perbandingan', PerbandinganController::class)->name('rkas.perbandingan');
    Route::post('rkas/{kertasKerja}/siap', [StatusKertasKerjaController::class, 'siap'])->name('rkas.status.siap');
    Route::post('rkas/{kertasKerja}/draf', [StatusKertasKerjaController::class, 'draf'])->name('rkas.status.draf');
    Route::post('rkas/{kertasKerja}/disahkan', [StatusKertasKerjaController::class, 'disahkan'])->name('rkas.status.disahkan');
    Route::post('rkas/{kertasKerja}/revisi', [RevisiController::class, 'store'])->name('rkas.revisi.store');
    Route::post('rkas/{kertasKerja}/baris', [BarisRkasController::class, 'store'])->name('rkas.baris.store');
    Route::patch('rkas/{kertasKerja}/baris/volume', [BarisRkasController::class, 'volume'])->name('rkas.baris.volume');
    Route::patch('rkas-baris/{baris}', [BarisRkasController::class, 'update'])->name('rkas.baris.update');
    Route::delete('rkas-baris/{baris}', [BarisRkasController::class, 'destroy'])->name('rkas.baris.destroy');
    Route::get('cadangan', CadanganController::class)->name('cadangan.unduh');
    Route::inertia('lainnya', 'Lainnya')->name('lainnya');

    Route::resource('pengaturan/jenis-kegiatan', JenisKegiatanController::class)
        ->parameters(['jenis-kegiatan' => 'jenis_kegiatan'])
        ->except(['create', 'show', 'edit']);
});

require __DIR__.'/settings.php';
