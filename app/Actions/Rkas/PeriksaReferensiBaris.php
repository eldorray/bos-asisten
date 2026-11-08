<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;
use App\Models\RkasPengaturan;
use App\Models\RkasRefBarang;
use Carbon\CarbonInterface;

/**
 * Memeriksa referensi sebuah baris terhadap daftar referensi saat ini (spec §7.1).
 * Baris yang bermasalah hanya ditandai, tidak pernah dihapus.
 */
class PeriksaReferensiBaris
{
    /**
     * @param  array{kode_honor: list<string>, kode_berdaftar: array<string, bool>, ada_tanpa_kode: bool}|null  $konteks
     * @return list<string>
     */
    public function untuk(RkasBaris $baris, ?array $konteks = null): array
    {
        $konteks ??= $this->konteks($baris->kertasKerja);
        $pesan = [];

        if ($this->kedaluwarsa($baris->refKegiatan->kedaluwarsa)) {
            $pesan[] = PesanRkas::KEGIATAN_BERUBAH;
        }

        if ($this->kedaluwarsa($baris->refRekening->kedaluwarsa)) {
            $pesan[] = PesanRkas::REKENING_BERUBAH;
        }

        $barang = $baris->refBarang;
        if ($barang !== null) {
            if ($this->kedaluwarsa($barang->kedaluwarsa) || $baris->uraian !== $barang->nama) {
                $pesan[] = PesanRkas::BARANG_BERUBAH;
            }

            if ($baris->harga < $barang->batas_bawah || ($barang->batas_atas > 0 && $baris->harga > $barang->batas_atas)) {
                $pesan[] = PesanRkas::BATAS_BERUBAH;
            }
        } elseif ($this->barangDiperlukan($baris, $konteks)) {
            $pesan[] = PesanRkas::BARANG_BERUBAH;
        }

        return array_values(array_unique($pesan));
    }

    /**
     * Konteks daftar barang yang berlaku untuk tahun kertas kerja, dimuat sekali untuk banyak baris.
     *
     * @return array{kode_honor: list<string>, kode_berdaftar: array<string, bool>, ada_tanpa_kode: bool}
     */
    public function konteks(RkasKertasKerja $kertasKerja): array
    {
        $barang = RkasRefBarang::query()
            ->where('tahun', $kertasKerja->tahun)
            ->belumKedaluwarsa()
            ->get(['kode_rekening']);

        return [
            'kode_honor' => RkasPengaturan::untuk($kertasKerja->sekolah, $kertasKerja->tahun)->kode_honor,
            'kode_berdaftar' => $barang->pluck('kode_rekening')->filter()->unique()->mapWithKeys(fn (string $k): array => [$k => true])->all(),
            'ada_tanpa_kode' => $barang->contains(fn (RkasRefBarang $b): bool => $b->kode_rekening === null),
        ];
    }

    /**
     * @param  array{kode_honor: list<string>, kode_berdaftar: array<string, bool>, ada_tanpa_kode: bool}  $konteks
     */
    private function barangDiperlukan(RkasBaris $baris, array $konteks): bool
    {
        if (in_array($baris->refKegiatan->kode, $konteks['kode_honor'], true)) {
            return false;
        }

        $kode = $baris->refRekening->kode;

        return isset($konteks['kode_berdaftar'][$kode])
            || ($konteks['ada_tanpa_kode'] && str_starts_with($kode, '5.2'));
    }

    /**
     * Sama dengan aturan belumKedaluwarsa: kedaluwarsa pada atau sebelum hari ini berarti sudah kedaluwarsa.
     */
    private function kedaluwarsa(?CarbonInterface $tanggal): bool
    {
        return $tanggal !== null && $tanggal->toDateString() <= now()->toDateString();
    }
}
