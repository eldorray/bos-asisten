<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\HitungStatusKegiatan;
use App\Actions\SusunBlokBulan;
use App\Models\Kegiatan;
use App\Pdf\PdfBulanan;
use App\Pdf\TataLetakPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PdfKegiatanController extends Controller
{
    public function __invoke(Kegiatan $kegiatan): Response|RedirectResponse
    {
        $kegiatan->load([...HitungStatusKegiatan::RELASI, 'sekolah', 'fotos.jenisBerkas', 'acara.fotos.jenisBerkas']);

        if (! HitungStatusKegiatan::untuk($kegiatan)->bisaUnduhPdf()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'PDF baru bisa diunduh kalau berkas lengkap atau kurang satu']);

            return to_route('kegiatan.show', $kegiatan);
        }

        $kode = preg_replace('/[^A-Za-z0-9_-]+/', '-', $kegiatan->sekolah->kode_bukti);
        $namaFile = sprintf('SPJ-%s-%s.pdf', $kode, Str::slug(Str::limit($kegiatan->nama, 50, '')) ?: $kegiatan->id);
        $lembar = TataLetakPdf::susun([SusunBlokBulan::untukKegiatan($kegiatan)]);

        return response(PdfBulanan::buat($kegiatan->sekolah, $lembar), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$namaFile}\"",
        ]);
    }
}
