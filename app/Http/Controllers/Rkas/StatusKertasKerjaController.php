<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rkas;

use App\Actions\Rkas\UbahStatusKertasKerja;
use App\Enums\SistemAnggaran;
use App\Http\Controllers\Controller;
use App\Models\RkasKertasKerja;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StatusKertasKerjaController extends Controller
{
    public function siap(RkasKertasKerja $kertasKerja, UbahStatusKertasKerja $ubah): RedirectResponse
    {
        $this->pastikanArkas($kertasKerja);

        Inertia::flash('pemeriksaan', $ubah->nyatakanSiap($kertasKerja)->props());

        return back();
    }

    public function draf(RkasKertasKerja $kertasKerja, UbahStatusKertasKerja $ubah): RedirectResponse
    {
        $this->pastikanArkas($kertasKerja);

        $ubah->kembalikanKeDraf($kertasKerja);

        return back();
    }

    public function disahkan(RkasKertasKerja $kertasKerja, UbahStatusKertasKerja $ubah): RedirectResponse
    {
        $this->pastikanArkas($kertasKerja);

        $ubah->tandaiDisahkan($kertasKerja);

        return back();
    }

    private function pastikanArkas(RkasKertasKerja $kertasKerja): void
    {
        abort_unless($kertasKerja->sekolah->sistem === SistemAnggaran::Arkas, 404);
    }
}
