<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Acara;
use App\Models\Kegiatan;
use App\Models\Sekolah;
use Illuminate\Support\Facades\DB;

class HapusSekolah
{
    /**
     * Menghapus sekolah beserta seluruh datanya. Foto berelasi morph, jadi tidak ikut
     * terhapus oleh cascade database dan filenya harus dibuang lewat model.
     */
    public function jalankan(Sekolah $sekolah): void
    {
        DB::transaction(function () use ($sekolah): void {
            Kegiatan::query()->where('sekolah_id', $sekolah->id)->with('fotos')->get()
                ->each(fn (Kegiatan $kegiatan) => $kegiatan->fotos->each->delete());
            Acara::query()->where('sekolah_id', $sekolah->id)->with('fotos')->get()
                ->each(fn (Acara $acara) => $acara->fotos->each->delete());

            $sekolah->delete();
        });
    }
}
