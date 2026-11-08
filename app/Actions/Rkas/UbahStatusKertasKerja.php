<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

use App\Enums\StatusKertasKerja;
use App\Models\RkasKertasKerja;
use Illuminate\Validation\ValidationException;

class UbahStatusKertasKerja
{
    public function __construct(private readonly PeriksaSiap $periksa) {}

    /**
     * Draf -> Siap, hanya bila tidak ada pemblokir. Hasil pemeriksaan selalu dikembalikan.
     */
    public function nyatakanSiap(RkasKertasKerja $kertasKerja): HasilPemeriksaan
    {
        $this->pastikanDari($kertasKerja, StatusKertasKerja::Draf);

        $hasil = $this->periksa->untuk($kertasKerja);
        if ($hasil->lolos()) {
            $kertasKerja->update(['status' => StatusKertasKerja::Siap]);
        }

        return $hasil;
    }

    public function kembalikanKeDraf(RkasKertasKerja $kertasKerja): void
    {
        $this->pastikanDari($kertasKerja, StatusKertasKerja::Siap);
        $kertasKerja->update(['status' => StatusKertasKerja::Draf]);
    }

    public function tandaiDisahkan(RkasKertasKerja $kertasKerja): void
    {
        $this->pastikanDari($kertasKerja, StatusKertasKerja::Siap);
        $kertasKerja->update(['status' => StatusKertasKerja::Disahkan]);
    }

    private function pastikanDari(RkasKertasKerja $kertasKerja, StatusKertasKerja $dari): void
    {
        if ($kertasKerja->status !== $dari) {
            throw ValidationException::withMessages([
                'kertas_kerja' => sprintf(PesanRkas::TERKUNCI, $kertasKerja->status->label()),
            ]);
        }
    }
}
