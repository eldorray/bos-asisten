<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

use App\Enums\JenisPtk;
use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;
use App\Models\RkasPengaturan;
use App\Models\RkasPtk;
use App\Models\RkasRefBarang;
use App\Models\RkasRefKegiatan;
use App\Models\RkasRefRekening;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SimpanBarisRkas
{
    /**
     * Simpan baris baru ($baris null) atau ubah baris yang ada. Aturan isian ditolak lewat ValidationException.
     *
     * @param  array{ref_kegiatan_id: int, ref_rekening_id: int, ref_barang_id?: int|null, ptk_id?: int|null, uraian?: string|null, satuan?: string|null, harga: int, volume_bulan: array<int|string, int|string|null>, keterangan?: string|null}  $data
     */
    public function simpan(RkasKertasKerja $kertasKerja, ?RkasBaris $baris, array $data): RkasBaris
    {
        $this->pastikanBisaDiubah($kertasKerja);

        $volume = $this->volumePerBulan($data['volume_bulan']);
        if (array_sum($volume) === 0) {
            $this->tolak('volume_bulan', PesanRkas::VOLUME_KOSONG);
        }

        $kegiatan = RkasRefKegiatan::findOrFail($data['ref_kegiatan_id']);
        $rekening = RkasRefRekening::findOrFail($data['ref_rekening_id']);
        $honor = in_array($kegiatan->kode, RkasPengaturan::untuk($kertasKerja->sekolah, $kertasKerja->tahun)->kode_honor, true);

        $harga = (int) $data['harga'];
        $uraian = trim((string) ($data['uraian'] ?? ''));
        $satuan = trim((string) ($data['satuan'] ?? ''));
        $barang = null;
        $ptk = null;

        if ($honor) {
            $ptk = $this->ptkHonor($kertasKerja, $kegiatan, $data['ptk_id'] ?? null);
            $uraian = $ptk->nuptk === null || $ptk->nuptk === '' ? $ptk->nama : "{$ptk->nama} ({$ptk->nuptk})";
        } else {
            $barang = $this->barang($rekening, $data['ref_barang_id'] ?? null);

            if ($barang !== null) {
                $this->periksaHarga($barang, $harga);
                $uraian = $barang->nama;
                $satuan = $barang->satuan;
            }
        }

        if ($uraian === '') {
            $this->tolak('uraian', PesanRkas::WAJIB);
        }
        if ($satuan === '') {
            $this->tolak('satuan', PesanRkas::WAJIB);
        }

        return DB::transaction(function () use ($kertasKerja, $baris, $data, $kegiatan, $rekening, $barang, $ptk, $uraian, $satuan, $harga, $volume): RkasBaris {
            if ($this->ganda($kertasKerja, $baris, $kegiatan->id, $rekening->id, $uraian, $satuan, $harga)) {
                $this->tolak('uraian', PesanRkas::GANDA);
            }

            $isi = [
                'ref_kegiatan_id' => $kegiatan->id,
                'ref_rekening_id' => $rekening->id,
                'ref_barang_id' => $barang?->id,
                'ptk_id' => $ptk?->id,
                'uraian' => $uraian,
                'satuan' => $satuan,
                'harga' => $harga,
                'volume_bulan' => $volume,
                'keterangan' => $data['keterangan'] ?? null,
            ];

            $pindah = $baris === null
                || $baris->ref_kegiatan_id !== $kegiatan->id
                || $baris->ref_rekening_id !== $rekening->id;

            // Urutan berlaku per (kertas kerja, kegiatan, rekening): baris baru atau yang pindah kelompok
            // mendapat nomor berikutnya di kelompok tujuan; selain itu urutan tetap.
            if ($pindah) {
                $isi['urutan'] = 1 + (int) RkasBaris::query()
                    ->where('kertas_kerja_id', $kertasKerja->id)
                    ->where('ref_kegiatan_id', $kegiatan->id)
                    ->where('ref_rekening_id', $rekening->id)
                    ->when($baris !== null, fn ($q) => $q->whereKeyNot($baris?->id))
                    ->max('urutan');
            }

            if ($baris !== null) {
                $baris->update($isi);

                return $baris;
            }

            return RkasBaris::create($isi + ['kertas_kerja_id' => $kertasKerja->id]);
        });
    }

    public function hapus(RkasBaris $baris): void
    {
        $this->pastikanBisaDiubah($baris->kertasKerja);

        $baris->delete();
    }

    private function pastikanBisaDiubah(RkasKertasKerja $kertasKerja): void
    {
        if (! $kertasKerja->bisaDiubah()) {
            $this->tolak('kertas_kerja', sprintf(PesanRkas::TERKUNCI, $kertasKerja->status->label()));
        }
    }

    /**
     * @param  array<int|string, int|string|null>  $isi
     * @return array<int, int>
     */
    private function volumePerBulan(array $isi): array
    {
        $volume = [];
        foreach (range(1, 12) as $bulan) {
            $volume[$bulan] = (int) ($isi[$bulan] ?? 0);
        }

        return $volume;
    }

    private function ptkHonor(RkasKertasKerja $kertasKerja, RkasRefKegiatan $kegiatan, ?int $ptkId): RkasPtk
    {
        if ($ptkId === null) {
            $this->tolak('ptk_id', PesanRkas::WAJIB);
        }

        $ptk = RkasPtk::query()->where('sekolah_id', $kertasKerja->sekolah_id)->find($ptkId);
        if ($ptk === null) {
            $this->tolak('ptk_id', PesanRkas::PTK_TIDAK_SESUAI);
        }

        // Digit terakhir segmen ketiga kode kegiatan (07.12.0X.) menentukan jenis PTK.
        $segmen = explode('.', rtrim($kegiatan->kode, '.'));
        if (isset($segmen[2]) && JenisPtk::tryFrom((int) substr($segmen[2], -1)) !== $ptk->jenis) {
            $this->tolak('ptk_id', PesanRkas::PTK_TIDAK_SESUAI);
        }

        return $ptk;
    }

    /**
     * Barang wajib dipilih hanya bila rekening punya daftar barang pada tahunnya; selain itu uraian bebas sah.
     */
    private function barang(RkasRefRekening $rekening, ?int $barangId): ?RkasRefBarang
    {
        if ($barangId === null) {
            $adaDaftar = RkasRefBarang::query()->untukRekening($rekening)->belumKedaluwarsa()->exists();

            if ($adaDaftar) {
                $this->tolak('ref_barang_id', PesanRkas::WAJIB);
            }

            return null;
        }

        $barang = RkasRefBarang::query()->untukRekening($rekening)->find($barangId);
        if ($barang === null) {
            $this->tolak('ref_barang_id', PesanRkas::BARANG_BUKAN_REKENING);
        }

        return $barang;
    }

    private function periksaHarga(RkasRefBarang $barang, int $harga): void
    {
        if ($harga < $barang->batas_bawah) {
            $this->tolak('harga', PesanRkas::SSH_BAWAH);
        }
        if ($barang->batas_atas > 0 && $harga > $barang->batas_atas) {
            $this->tolak('harga', PesanRkas::SSH_ATAS);
        }
    }

    private function ganda(RkasKertasKerja $kertasKerja, ?RkasBaris $diubah, int $kegiatanId, int $rekeningId, string $uraian, string $satuan, int $harga): bool
    {
        $kunci = fn (string $teks): string => mb_strtolower(trim($teks));

        return RkasBaris::query()
            ->where('kertas_kerja_id', $kertasKerja->id)
            ->where('ref_kegiatan_id', $kegiatanId)
            ->where('ref_rekening_id', $rekeningId)
            ->where('harga', $harga)
            ->when($diubah !== null, fn ($q) => $q->whereKeyNot($diubah?->id))
            ->get(['id', 'uraian', 'satuan'])
            ->contains(fn (RkasBaris $b): bool => $kunci($b->uraian) === $kunci($uraian) && $kunci($b->satuan) === $kunci($satuan));
    }

    private function tolak(string $kunci, string $pesan): never
    {
        throw ValidationException::withMessages([$kunci => $pesan]);
    }
}
