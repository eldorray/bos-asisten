<?php

declare(strict_types=1);

namespace App\Pdf;

use App\Models\Kuitansi;
use App\Models\Sekolah;
use Exception;
use FPDF;
use Illuminate\Support\Facades\Storage;

/**
 * JPEG disematkan apa adanya oleh FPDF, tanpa dibuka lewat GD, jadi hemat memori di hosting bersama.
 */
class PdfBulanan
{
    /**
     * @param  list<Lembar>  $lembar
     */
    public static function buat(Sekolah $sekolah, array $lembar): string
    {
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $kuitansi = new GambarKuitansi($pdf, $sekolah);

        foreach ($lembar as $satu) {
            $pdf->AddPage();

            foreach ($satu->penempatan as $tempat) {
                if ($tempat->isi instanceof LampiranPdf) {
                    self::foto($pdf, $tempat, $tempat->isi);
                } elseif ($tempat->isi === null || $tempat->isi instanceof Kuitansi) {
                    $kuitansi->gambar($tempat->isi, $tempat->x, $tempat->y, $tempat->lebar, $tempat->tinggi);
                }
            }
        }

        return $pdf->Output('S');
    }

    private static function foto(FPDF $pdf, Penempatan $tempat, LampiranPdf $lampiran): void
    {
        try {
            $pdf->Image(Storage::disk('local')->path($lampiran->path), $tempat->x, $tempat->y, $tempat->lebar, $tempat->tinggi, 'JPG');
        } catch (Exception) {
            self::fotoTidakDitemukan($pdf, $tempat);
        }
    }

    private static function fotoTidakDitemukan(FPDF $pdf, Penempatan $tempat): void
    {
        $pdf->SetDrawColor(150);
        $pdf->SetLineWidth(0.3);
        $pdf->Rect($tempat->x, $tempat->y, $tempat->lebar, $tempat->tinggi);
        $pdf->SetTextColor(90);
        $pdf->SetFont('Helvetica', 'B', 9.0);

        $teks = 'Foto tidak ditemukan';
        $pdf->Text(
            $tempat->x + max(0.0, ($tempat->lebar - $pdf->GetStringWidth($teks)) / 2),
            $tempat->y + $tempat->tinggi / 2,
            $teks,
        );
    }
}
