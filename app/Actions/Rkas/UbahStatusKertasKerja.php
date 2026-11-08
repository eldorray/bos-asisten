<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

use App\Enums\StatusKertasKerja;
use App\Models\RkasKertasKerja;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UbahStatusKertasKerja
{
    public function __construct(private readonly PeriksaSiap $periksa) {}

    /**
     * Draf -> Siap, hanya bila tidak ada pemblokir. Hasil pemeriksaan selalu dikembalikan.
     */
    public function nyatakanSiap(RkasKertasKerja $kertasKerja): HasilPemeriksaan
    {
        return DB::transaction(function () use ($kertasKerja): HasilPemeriksaan {
            $kertasKerja->refresh();
            $this->pastikanDari($kertasKerja, StatusKertasKerja::Draf);

            $hasil = $this->periksa->untuk($kertasKerja);
            if ($hasil->lolos()) {
                $this->pindah($kertasKerja, StatusKertasKerja::Draf, StatusKertasKerja::Siap);
            }

            return $hasil;
        });
    }

    public function kembalikanKeDraf(RkasKertasKerja $kertasKerja): void
    {
        $this->ubah($kertasKerja, StatusKertasKerja::Siap, StatusKertasKerja::Draf);
    }

    public function tandaiDisahkan(RkasKertasKerja $kertasKerja): void
    {
        $this->ubah($kertasKerja, StatusKertasKerja::Siap, StatusKertasKerja::Disahkan);
    }

    private function ubah(RkasKertasKerja $kertasKerja, StatusKertasKerja $dari, StatusKertasKerja $ke): void
    {
        DB::transaction(function () use ($kertasKerja, $dari, $ke): void {
            $kertasKerja->refresh();
            $this->pastikanDari($kertasKerja, $dari);
            $this->pindah($kertasKerja, $dari, $ke);
        });
    }

    /**
     * Update bersyarat: hanya berhasil bila status di database masih $dari.
     */
    private function pindah(RkasKertasKerja $kertasKerja, StatusKertasKerja $dari, StatusKertasKerja $ke): void
    {
        $terubah = RkasKertasKerja::query()
            ->whereKey($kertasKerja->getKey())
            ->where('status', $dari->value)
            ->update(['status' => $ke->value]);

        if ($terubah === 0) {
            $kertasKerja->refresh();
            $this->tolak($kertasKerja);
        }

        $kertasKerja->refresh();
    }

    private function pastikanDari(RkasKertasKerja $kertasKerja, StatusKertasKerja $dari): void
    {
        if ($kertasKerja->status !== $dari) {
            $this->tolak($kertasKerja);
        }
    }

    private function tolak(RkasKertasKerja $kertasKerja): never
    {
        throw ValidationException::withMessages([
            'kertas_kerja' => sprintf(PesanRkas::TERKUNCI, $kertasKerja->status->label()),
        ]);
    }
}
