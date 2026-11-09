<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rkas;

use App\Actions\Rkas\BandingkanDenganImpor;
use App\Enums\SistemAnggaran;
use App\Enums\SumberDanaRkas;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rkas\PerbandinganRequest;
use App\Models\RkasKertasKerja;
use Inertia\Inertia;
use Inertia\Response;

class PerbandinganController extends Controller
{
    public function __invoke(PerbandinganRequest $request, RkasKertasKerja $kertasKerja, BandingkanDenganImpor $bandingkan): Response
    {
        $sekolah = $kertasKerja->sekolah;
        abort_unless($sekolah->sistem === SistemAnggaran::Arkas, 404);

        $bulan = $request->bulan();
        $tersedia = $kertasKerja->sumber_dana === SumberDanaRkas::BospReguler;
        $pembanding = $tersedia ? $bandingkan->pembanding($kertasKerja) : null;

        return Inertia::render('rkas/Perbandingan', [
            'kertasKerja' => [
                'id' => $kertasKerja->id,
                'uuid' => $kertasKerja->uuid,
                'tahun' => $kertasKerja->tahun,
                'versi' => $kertasKerja->labelVersi(),
                'sekolah' => $sekolah->nama,
            ],
            'pembanding' => $pembanding === null ? null : [
                'id' => $pembanding->id,
                'versi' => $pembanding->labelVersi(),
                'status_label' => $pembanding->status->label(),
            ],
            'bulan' => $bulan,
            'perbandingan' => $tersedia ? $bandingkan->untuk($kertasKerja, $bulan)->props() : null,
        ]);
    }
}
