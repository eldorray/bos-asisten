<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

use App\Enums\JenisVersiRkas;
use App\Enums\StatusKertasKerja;
use App\Enums\SumberDanaRkas;
use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;
use App\Models\RkasPengaturan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Membuat versi revisi (pergeseran atau perubahan) dari kertas kerja yang sudah disahkan (spec §6.2).
 */
class BuatRevisi
{
    public function jalankan(RkasKertasKerja $disahkan, JenisVersiRkas $jenis, CarbonImmutable $tanggalRevisi, ?int $paguBaru = null): RkasKertasKerja
    {
        if ($jenis === JenisVersiRkas::Awal) {
            throw new \InvalidArgumentException('Revisi harus berupa pergeseran atau perubahan.');
        }

        return DB::transaction(function () use ($disahkan, $jenis, $tanggalRevisi, $paguBaru): RkasKertasKerja {
            $sumber = RkasKertasKerja::query()->whereKey($disahkan->getKey())->lockForUpdate()->firstOrFail();

            // Sumber dana Lainnya: pagu tetap jumlah pemberi dana (disalin); pagu_baru hanya untuk BOSP Reguler.
            $paguBerubah = $jenis === JenisVersiRkas::Perubahan && $sumber->sumber_dana === SumberDanaRkas::BospReguler;
            if ($paguBerubah && $paguBaru === null) {
                $this->tolak('pagu_baru', PesanRkas::WAJIB);
            }

            if ($sumber->status !== StatusKertasKerja::Disahkan) {
                $this->tolak('kertas_kerja', sprintf(PesanRkas::TERKUNCI, $sumber->status->label()));
            }

            if ($this->adaRevisiTerbuka($sumber)) {
                $this->tolak('kertas_kerja', PesanRkas::REVISI_TERBUKA);
            }

            $pengaturan = RkasPengaturan::untuk($sumber->sekolah, $sumber->tahun);
            $pergeseran = $sumber->nomor_pergeseran;
            $perubahan = $sumber->nomor_perubahan;

            if ($jenis === JenisVersiRkas::Pergeseran) {
                $pergeseran++;
                if ($pengaturan->kuota_pergeseran !== null && $pergeseran > $pengaturan->kuota_pergeseran) {
                    $this->tolak('kertas_kerja', PesanRkas::KUOTA_PERGESERAN);
                }
            } else {
                $perubahan++;
                $pergeseran = 0;
                if ($pengaturan->kuota_perubahan !== null && $perubahan > $pengaturan->kuota_perubahan) {
                    $this->tolak('kertas_kerja', PesanRkas::KUOTA_PERUBAHAN);
                }
            }

            $baru = RkasKertasKerja::create([
                'sekolah_id' => $sumber->sekolah_id,
                'tahun' => $sumber->tahun,
                'sumber_dana' => $sumber->sumber_dana,
                'pagu' => $paguBerubah ? (int) $paguBaru : $sumber->pagu,
                'jenis_versi' => $jenis,
                'nomor_pergeseran' => $pergeseran,
                'nomor_perubahan' => $perubahan,
                'induk_id' => $sumber->id,
                'tanggal_revisi' => $tanggalRevisi->toDateString(),
                'status' => StatusKertasKerja::Draf,
            ]);

            foreach ($sumber->pemberiDanas()->orderBy('id')->get() as $pemberi) {
                $baru->pemberiDanas()->create(['nama' => $pemberi->nama, 'jumlah' => $pemberi->jumlah]);
            }

            foreach ($sumber->baris()->orderBy('id')->get() as $baris) {
                RkasBaris::create([
                    'kertas_kerja_id' => $baru->id,
                    'ref_kegiatan_id' => $baris->ref_kegiatan_id,
                    'ref_rekening_id' => $baris->ref_rekening_id,
                    'ref_barang_id' => $baris->ref_barang_id,
                    'ptk_id' => $baris->ptk_id,
                    'asal_baris_id' => $baris->id,
                    'urutan' => $baris->urutan,
                    'uraian' => $baris->uraian,
                    'satuan' => $baris->satuan,
                    'harga' => $baris->harga,
                    'volume_bulan' => $baris->volume_bulan,
                    'keterangan' => $baris->keterangan,
                ]);
            }

            return $baru;
        });
    }

    /**
     * Satu rantai (induk dan turunannya) hanya boleh punya satu versi yang belum disahkan,
     * dan versi asal tidak boleh sudah punya turunan.
     */
    private function adaRevisiTerbuka(RkasKertasKerja $sumber): bool
    {
        if (RkasKertasKerja::query()->where('induk_id', $sumber->id)->exists()) {
            return true;
        }

        return $sumber->rantai()->contains(fn (RkasKertasKerja $k): bool => $k->status !== StatusKertasKerja::Disahkan);
    }

    private function tolak(string $kunci, string $pesan): never
    {
        throw ValidationException::withMessages([$kunci => $pesan]);
    }
}
