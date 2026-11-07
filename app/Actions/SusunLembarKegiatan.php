<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Kegiatan;
use App\Pdf\BlokPdf;
use App\Pdf\Lembar;
use App\Pdf\Penempatan;
use App\Pdf\TataLetakPdf;

class SusunLembarKegiatan
{
    /**
     * PDF satu kegiatan: tiap kuitansi satu halaman penuh, lalu nota/struk dan foto menurut urutan berkas.
     *
     * @return list<Lembar>
     */
    public static function untuk(Kegiatan $kegiatan): array
    {
        $blok = SusunBlokBulan::untukKegiatan($kegiatan);

        $kuitansi = array_map(
            fn ($isi): Lembar => new Lembar([new Penempatan('kuitansi_penuh', 0.0, 0.0, 210.0, 297.0, $isi)]),
            $blok->kuitansi,
        );

        return [...$kuitansi, ...TataLetakPdf::susun([new BlokPdf([], $blok->lampiran)])];
    }
}
