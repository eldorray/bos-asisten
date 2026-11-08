<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

use App\Enums\JenisVersiRkas;
use App\Enums\StatusKertasKerja;
use App\Enums\SumberDanaRkas;
use App\Models\RkasKertasKerja;
use App\Models\Sekolah;
use Illuminate\Support\Facades\DB;

class SimpanKertasKerja
{
    /**
     * Buat kertas kerja versi awal berstatus draf. Untuk sumber dana Lainnya, pagu adalah jumlah semua pemberi dana.
     *
     * @param  array{tahun: int, sumber_dana: string, pagu?: int, pemberi_dana?: list<array{nama: string, jumlah: int}>}  $data
     */
    public function buat(Sekolah $sekolah, array $data): RkasKertasKerja
    {
        $sumber = SumberDanaRkas::from($data['sumber_dana']);
        $pemberi = $sumber === SumberDanaRkas::Lainnya ? ($data['pemberi_dana'] ?? []) : [];

        return DB::transaction(function () use ($sekolah, $data, $sumber, $pemberi): RkasKertasKerja {
            $kertasKerja = RkasKertasKerja::create([
                'sekolah_id' => $sekolah->id,
                'tahun' => $data['tahun'],
                'sumber_dana' => $sumber,
                'pagu' => $sumber === SumberDanaRkas::Lainnya ? $this->jumlah($pemberi) : (int) ($data['pagu'] ?? 0),
                'jenis_versi' => JenisVersiRkas::Awal,
                'nomor_pergeseran' => 0,
                'nomor_perubahan' => 0,
                'status' => StatusKertasKerja::Draf,
            ]);

            $this->simpanPemberi($kertasKerja, $pemberi);

            return $kertasKerja;
        });
    }

    /**
     * Ubah pagu (BOSP Reguler) atau ganti daftar pemberi dana (Lainnya).
     *
     * @param  array{pagu?: int, pemberi_dana?: list<array{nama: string, jumlah: int}>}  $data
     */
    public function ubahPagu(RkasKertasKerja $kertasKerja, array $data): void
    {
        DB::transaction(function () use ($kertasKerja, $data): void {
            if ($kertasKerja->sumber_dana === SumberDanaRkas::Lainnya) {
                $pemberi = $data['pemberi_dana'] ?? [];
                $kertasKerja->pemberiDanas()->delete();
                $this->simpanPemberi($kertasKerja, $pemberi);
                $kertasKerja->update(['pagu' => $this->jumlah($pemberi)]);

                return;
            }

            $kertasKerja->update(['pagu' => (int) ($data['pagu'] ?? 0)]);
        });
    }

    /**
     * Hapus kertas kerja berstatus draf. Mengembalikan alasan penolakan, atau null bila terhapus.
     */
    public function hapus(RkasKertasKerja $kertasKerja): ?string
    {
        if (! $kertasKerja->bisaDiubah()) {
            return sprintf(PesanRkas::TERKUNCI, $kertasKerja->status->label());
        }

        DB::transaction(function () use ($kertasKerja): void {
            $kertasKerja->baris()->delete();
            $kertasKerja->pemberiDanas()->delete();
            $kertasKerja->delete();
        });

        return null;
    }

    /**
     * @param  list<array{nama: string, jumlah: int}>  $pemberi
     */
    private function simpanPemberi(RkasKertasKerja $kertasKerja, array $pemberi): void
    {
        foreach ($pemberi as $p) {
            $kertasKerja->pemberiDanas()->create(['nama' => $p['nama'], 'jumlah' => $p['jumlah']]);
        }
    }

    /**
     * @param  list<array{nama: string, jumlah: int}>  $pemberi
     */
    private function jumlah(array $pemberi): int
    {
        return array_sum(array_map(fn (array $p): int => (int) $p['jumlah'], $pemberi));
    }
}
