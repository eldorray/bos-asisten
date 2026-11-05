<?php

use App\Http\Controllers\BulanController;
use App\Http\Controllers\ImporController;
use App\Http\Controllers\JenisKegiatanController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\SekolahController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', BulanController::class)->name('dashboard');

    Route::get('kegiatan/{kegiatan}', [KegiatanController::class, 'show'])->name('kegiatan.show');
    Route::patch('kegiatan/{kegiatan}', [KegiatanController::class, 'update'])->name('kegiatan.update');
    Route::delete('kegiatan/{kegiatan}', [KegiatanController::class, 'destroy'])->name('kegiatan.destroy');
    Route::post('kegiatan-tandai', [KegiatanController::class, 'tandai'])->name('kegiatan.tandai');

    Route::get('impor', [ImporController::class, 'index'])->name('impor.index');
    Route::post('impor/pratinjau', [ImporController::class, 'pratinjau'])->name('impor.pratinjau');
    Route::post('impor/simpan', [ImporController::class, 'simpan'])->name('impor.simpan');

    Route::get('pengaturan/sekolah', [SekolahController::class, 'index'])->name('sekolah.index');
    Route::patch('pengaturan/sekolah/{sekolah}', [SekolahController::class, 'update'])->name('sekolah.update');
    Route::resource('pengaturan/jenis-kegiatan', JenisKegiatanController::class)
        ->parameters(['jenis-kegiatan' => 'jenis_kegiatan'])
        ->except(['create', 'show', 'edit']);
});

require __DIR__.'/settings.php';
