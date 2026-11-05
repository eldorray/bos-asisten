<?php

declare(strict_types=1);

namespace App\Pdf;

use App\Enums\TataLetakBerkas;
use App\Models\Kuitansi;

/**
 * Menyusun blok menjadi lembar A4 potret. Murni hitung koordinat, tanpa I/O.
 */
class TataLetakPdf
{
    private const TINGGI_A4 = 297.0;

    private const LEBAR_A4 = 210.0;

    private const MARGIN = 8.0;

    private const TINGGI_KUITANSI = 99.0;

    private const KUITANSI_PER_LEMBAR = 3;

    private const BARIS_PER_LEMBAR = 2;

    private const KOLOM_KISI = 2;

    private const JARAK = 4.0;

    /**
     * @param  list<BlokPdf>  $blok
     * @return list<Lembar>
     */
    public static function susun(array $blok): array
    {
        $lembar = [];
        $terbuka = [];

        foreach ($blok as $satu) {
            if ($satu->lampiran !== [] && $terbuka !== []) {
                $lembar[] = new Lembar($terbuka);
                $terbuka = [];
            }

            foreach ($satu->kuitansi as $kuitansi) {
                if (count($terbuka) === self::KUITANSI_PER_LEMBAR) {
                    $lembar[] = new Lembar($terbuka);
                    $terbuka = [];
                }

                $terbuka[] = self::tempatKuitansi($kuitansi, count($terbuka));
            }

            if ($satu->lampiran !== []) {
                array_push($lembar, ...self::lembarLampiran($terbuka, $satu->lampiran));
                $terbuka = [];
            }
        }

        if ($terbuka !== []) {
            $lembar[] = new Lembar($terbuka);
        }

        return $lembar;
    }

    private static function tempatKuitansi(?Kuitansi $kuitansi, int $urutan): Penempatan
    {
        return new Penempatan('kuitansi', 0.0, $urutan * self::TINGGI_KUITANSI, self::LEBAR_A4, self::TINGGI_KUITANSI, $kuitansi);
    }

    /**
     * Lampiran pertama mengisi sisa lembar kuitansi. Kalau lampiran itu kisi, sisa lembar
     * menjadi awal kisi: sebanyak baris yang muat. Sisanya dua baris per lembar.
     *
     * @param  list<Penempatan>  $kuitansi  Kuitansi di lembar yang masih terbuka.
     * @param  non-empty-list<LampiranPdf>  $lampiran
     * @return non-empty-list<Lembar>
     */
    private static function lembarLampiran(array $kuitansi, array $lampiran): array
    {
        $atas = count($kuitansi) * self::TINGGI_KUITANSI + self::MARGIN;
        $sisaTinggi = self::TINGGI_A4 - self::MARGIN - $atas;
        $barisDiSisa = 0;

        if (count($kuitansi) < self::KUITANSI_PER_LEMBAR) {
            if ($lampiran[0]->tataLetak === TataLetakBerkas::Kisi) {
                $barisDiSisa = (int) floor(($sisaTinggi + self::JARAK) / (self::tinggiBaris() + self::JARAK));
            } else {
                $kuitansi[] = self::foto(array_shift($lampiran), self::MARGIN, $atas, self::lebarIsi(), $sisaTinggi);
            }
        }

        $baris = self::bagiBaris($lampiran);
        $hasil = [new Lembar([...$kuitansi, ...self::tempatBaris(array_slice($baris, 0, $barisDiSisa), $atas)])];

        foreach (array_chunk(array_slice($baris, $barisDiSisa), self::BARIS_PER_LEMBAR) as $bagian) {
            $hasil[] = new Lembar(self::tempatBaris($bagian, self::MARGIN));
        }

        return $hasil;
    }

    /**
     * Lampiran penuh memakai satu baris sendiri, lampiran kisi berurutan berbagi baris.
     * Urutan masukan tidak diubah.
     *
     * @param  list<LampiranPdf>  $lampiran
     * @return list<non-empty-list<LampiranPdf>>
     */
    private static function bagiBaris(array $lampiran): array
    {
        $baris = [];
        $kisi = [];

        foreach ($lampiran as $satu) {
            if ($satu->tataLetak === TataLetakBerkas::Kisi) {
                $kisi[] = $satu;

                if (count($kisi) === self::KOLOM_KISI) {
                    $baris[] = $kisi;
                    $kisi = [];
                }

                continue;
            }

            if ($kisi !== []) {
                $baris[] = $kisi;
                $kisi = [];
            }

            $baris[] = [$satu];
        }

        if ($kisi !== []) {
            $baris[] = $kisi;
        }

        return $baris;
    }

    /**
     * @param  array<int, non-empty-list<LampiranPdf>>  $daftarBaris
     * @return list<Penempatan>
     */
    private static function tempatBaris(array $daftarBaris, float $atas): array
    {
        $tinggiBaris = self::tinggiBaris();
        $lebarKisi = (self::lebarIsi() - self::JARAK) / self::KOLOM_KISI;
        $tempat = [];

        foreach (array_values($daftarBaris) as $nomorBaris => $baris) {
            $y = $atas + $nomorBaris * ($tinggiBaris + self::JARAK);

            foreach ($baris as $kolom => $lampiran) {
                $tempat[] = $lampiran->tataLetak === TataLetakBerkas::Kisi
                    ? self::foto($lampiran, self::MARGIN + $kolom * ($lebarKisi + self::JARAK), $y, $lebarKisi, $tinggiBaris)
                    : self::foto($lampiran, self::MARGIN, $y, self::lebarIsi(), $tinggiBaris);
            }
        }

        return $tempat;
    }

    /**
     * Foto dipaskan ke kotak tanpa terpotong dan tanpa gepeng, di tengah kotak.
     */
    private static function foto(LampiranPdf $lampiran, float $x, float $y, float $lebarKotak, float $tinggiKotak): Penempatan
    {
        $skala = min($lebarKotak / $lampiran->lebar, $tinggiKotak / $lampiran->tinggi);
        $lebar = $lampiran->lebar * $skala;
        $tinggi = $lampiran->tinggi * $skala;

        return new Penempatan(
            'foto',
            $x + ($lebarKotak - $lebar) / 2,
            $y + ($tinggiKotak - $tinggi) / 2,
            $lebar,
            $tinggi,
            $lampiran,
        );
    }

    private static function tinggiBaris(): float
    {
        return (self::TINGGI_A4 - 2 * self::MARGIN - self::JARAK) / self::BARIS_PER_LEMBAR;
    }

    private static function lebarIsi(): float
    {
        return self::LEBAR_A4 - 2 * self::MARGIN;
    }
}
