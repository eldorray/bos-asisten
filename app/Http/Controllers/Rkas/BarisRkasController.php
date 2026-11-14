<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rkas;

use App\Actions\Rkas\SimpanBarisRkas;
use App\Enums\SistemAnggaran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rkas\SimpanBarisRkasRequest;
use App\Http\Requests\Rkas\UbahVolumeSekaligusRequest;
use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;
use Illuminate\Http\RedirectResponse;

class BarisRkasController extends Controller
{
    public function store(SimpanBarisRkasRequest $request, RkasKertasKerja $kertasKerja, SimpanBarisRkas $simpan): RedirectResponse
    {
        $simpan->simpan($kertasKerja, null, $request->dataBaris());

        return back();
    }

    public function update(SimpanBarisRkasRequest $request, RkasBaris $baris, SimpanBarisRkas $simpan): RedirectResponse
    {
        $simpan->simpan($baris->kertasKerja, $baris, $request->dataBaris());

        return back();
    }

    public function volume(UbahVolumeSekaligusRequest $request, RkasKertasKerja $kertasKerja, SimpanBarisRkas $simpan): RedirectResponse
    {
        $simpan->ubahVolume($kertasKerja, $request->uuidBaris(), $request->bulan(), $request->integer('volume'));

        return back();
    }

    public function destroy(RkasBaris $baris, SimpanBarisRkas $simpan): RedirectResponse
    {
        abort_unless($baris->kertasKerja->sekolah->sistem === SistemAnggaran::Arkas, 404);

        $simpan->hapus($baris);

        return back();
    }
}
