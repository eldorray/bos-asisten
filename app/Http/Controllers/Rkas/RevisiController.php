<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rkas;

use App\Actions\Rkas\BuatRevisi;
use App\Enums\JenisVersiRkas;
use App\Enums\SistemAnggaran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rkas\BuatRevisiRequest;
use App\Models\RkasKertasKerja;
use Illuminate\Http\RedirectResponse;

class RevisiController extends Controller
{
    public function store(BuatRevisiRequest $request, RkasKertasKerja $kertasKerja, BuatRevisi $buat): RedirectResponse
    {
        abort_unless($kertasKerja->sekolah->sistem === SistemAnggaran::Arkas, 404);

        $baru = $buat->jalankan(
            $kertasKerja,
            $request->jenis(),
            $request->tanggalRevisi(),
            $request->jenis() === JenisVersiRkas::Perubahan ? $request->paguBaru() : null,
        );

        return to_route('rkas.show', $baru);
    }
}
