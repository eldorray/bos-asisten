<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rkas;

use App\Actions\Rkas\HitungRingkasan;
use App\Enums\SistemAnggaran;
use App\Http\Controllers\Controller;
use App\Models\RkasKertasKerja;
use App\Pdf\PdfRincianKertasKerja;
use Illuminate\Http\Response;

class PdfKertasKerjaController extends Controller
{
    public function __invoke(RkasKertasKerja $kertasKerja, HitungRingkasan $hitung): Response
    {
        abort_unless($kertasKerja->sekolah->sistem === SistemAnggaran::Arkas, 404);

        $namaFile = sprintf(
            'RKAS-%d-%s-v%d.%d.pdf',
            $kertasKerja->tahun,
            $kertasKerja->sumber_dana->value,
            $kertasKerja->nomor_perubahan,
            $kertasKerja->nomor_pergeseran,
        );

        return response(PdfRincianKertasKerja::buat($kertasKerja, $hitung->untuk($kertasKerja)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$namaFile}\"",
        ]);
    }
}
