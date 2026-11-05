<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SusunBlokBulan;
use App\Models\Sekolah;
use App\Pdf\PdfBulanan;
use App\Pdf\TataLetakPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;

class PdfBulananController extends Controller
{
    public function __invoke(Sekolah $sekolah, int $tahun, int $bulan): Response|RedirectResponse
    {
        $blok = SusunBlokBulan::untuk($sekolah, $tahun, $bulan);

        if ($blok === []) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Belum ada berkas untuk bulan ini']);

            return to_route('dashboard', ['bulan' => sprintf('%04d-%02d', $tahun, $bulan)]);
        }

        $kode = preg_replace('/[^A-Za-z0-9_-]+/', '-', $sekolah->kode_bukti);
        $namaFile = sprintf('SPJ-%s-%04d-%02d.pdf', $kode, $tahun, $bulan);

        return response(PdfBulanan::buat($sekolah, TataLetakPdf::susun($blok)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$namaFile}\"",
        ]);
    }
}
