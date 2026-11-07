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
    Route::post('pengaturan/sekolah/{sekolah}/urutkan-nomor', [SekolahController::class, 'urutkanNomor'])->name('sekolah.urutkan-nomor');
    Route::get('cadangan', CadanganController::class)->name('cadangan.unduh');
    Route::inertia('lainnya', 'Lainnya')->name('lainnya');

    Route::resource('pengaturan/jenis-kegiatan', JenisKegiatanController::class)
        ->parameters(['jenis-kegiatan' => 'jenis_kegiatan'])
        ->except(['create', 'show', 'edit']);
});

require __DIR__.'/settings.php';
