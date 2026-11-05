<?php

use App\Http\Controllers\ImporController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('impor', [ImporController::class, 'index'])->name('impor.index');
    Route::post('impor/pratinjau', [ImporController::class, 'pratinjau'])->name('impor.pratinjau');
    Route::post('impor/simpan', [ImporController::class, 'simpan'])->name('impor.simpan');
});

require __DIR__.'/settings.php';
