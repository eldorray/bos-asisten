<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

use App\Enums\SumberDanaRkas;
use App\Models\RkasKertasKerja;
use App\Models\RkasPengaturan;

/**
 * Pemeriksaan pemblokir sebelum kertas kerja dinyatakan siap (spec §7.2).
 */
class PeriksaSiap
{
    public function __construct(private readonly HitungRingkasan $hitung) {}

    public function untuk(RkasKertasKerja $kertasKerja): HasilPemeriksaan
    {
        $ringkasan = $this->hitung->untuk($kertasKerja);
        $pemblokir = [];

        if ($kertasKerja->sumber_dana === SumberDanaRkas::BospReguler) {
            $ambang = RkasPengaturan::untuk($kertasKerja->sekolah, $kertasKerja->tahun)->ambang_tahap_1;
            $tahap1 = array_sum(array_slice($ringkasan->perBulan, 0, 6));
            if ($tahap1 * 100 < $ambang * $ringkasan->pagu) {
                $pemblokir[] = PesanRkas::TAHAP_1_MINIMAL;
            }
        }

        if ($ringkasan->sisa > 0) {
            $pemblokir[] = sprintf(PesanRkas::SISA, PesanRkas::rupiah($ringkasan->sisa));
        } elseif ($ringkasan->sisa < 0) {
            $pemblokir[] = sprintf(PesanRkas::LEBIH, PesanRkas::rupiah(abs($ringkasan->sisa)));
        }

        // Pemblokir 3 (pergeseran lintas jenis belanja) ditambahkan di sini oleh Task 8, hanya bila ada induk.

        if ($ringkasan->jumlahBarisBermasalah > 0) {
            $pemblokir[] = sprintf(PesanRkas::REFERENSI_BERMASALAH, $ringkasan->jumlahBarisBermasalah);
        }

        return new HasilPemeriksaan($pemblokir, $ringkasan->peringatan);
    }
}
