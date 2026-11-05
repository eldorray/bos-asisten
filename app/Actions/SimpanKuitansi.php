<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Kegiatan;
use App\Models\Kuitansi;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SimpanKuitansi
{
    /**
     * @param  list<int>  $kegiatanIds  Baris pertama adalah kegiatan yang sedang dibuka, sisanya rapel.
     * @param  array{nomor: string, untuk_pembayaran: string, penerima: string, tanggal_lunas: string, jumlah: int}  $data
     */
    public function buat(array $kegiatanIds, array $data): Kuitansi
    {
        $ids = array_values(array_unique($kegiatanIds));
        $kolom = count($ids) > 1 ? 'kegiatan_rapel_ids' : 'kegiatan';

        try {
            return DB::transaction(function () use ($ids, $data, $kolom): Kuitansi {
                $kegiatans = Kegiatan::query()->with('sekolah')->whereIn('id', $ids)->get();
                $this->pastikanSah($kegiatans, $ids, $kolom);

                $acuan = $kegiatans->firstOrFail('id', $ids[0]);
                $sekolah = $acuan->sekolah;

                $kuitansi = Kuitansi::create([
                    'sekolah_id' => $sekolah->id,
                    'tahun' => $acuan->tahun,
                    'bulan' => (int) $kegiatans->max('bulan'),
                    'tahap' => $acuan->tahap,
                    'nomor_urut' => $this->nomorUrut($data['nomor']),
                    'nomor' => $data['nomor'],
                    'untuk_pembayaran' => $data['untuk_pembayaran'],
                    'sumber_dana' => NilaiAwalKuitansi::sumberDana($acuan),
                    'penerima' => $data['penerima'],
                    'tanggal_lunas' => $data['tanggal_lunas'],
                    'jumlah' => $data['jumlah'],
                ]);
                $kuitansi->kegiatans()->attach($kegiatans->modelKeys());

                return $kuitansi;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->nomorSudahDipakai($kolom, $ids, $data['nomor']);
        }
    }

    /**
     * Hanya isian yang boleh berubah. Cakupan kegiatan tetap.
     *
     * @param  array{nomor: string, untuk_pembayaran: string, penerima: string, tanggal_lunas: string, jumlah: int}  $data
     */
    public function ubah(Kuitansi $kuitansi, array $data): Kuitansi
    {
        try {
            $kuitansi->update([
                'nomor' => $data['nomor'],
                'nomor_urut' => $this->nomorUrut($data['nomor']),
                'untuk_pembayaran' => $data['untuk_pembayaran'],
                'penerima' => $data['penerima'],
                'tanggal_lunas' => $data['tanggal_lunas'],
                'jumlah' => $data['jumlah'],
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nomor' => 'Nomor ini sudah dipakai kuitansi lain.']);
        }

        return $kuitansi;
    }

    public function hapus(Kuitansi $kuitansi): void
    {
        DB::transaction(function () use ($kuitansi): void {
            $kuitansi->kegiatans()->detach();
            $kuitansi->delete();
        });
    }

    /**
     * @param  Collection<int, Kegiatan>  $kegiatans
     * @param  list<int>  $ids
     */
    private function pastikanSah(Collection $kegiatans, array $ids, string $kolom): void
    {
        if ($kegiatans->count() !== count($ids)) {
            throw $this->galat($kolom, 'Ada kegiatan yang tidak ditemukan.');
        }

        $acuan = $kegiatans->firstOrFail('id', $ids[0]);

        if ($acuan->bulan === null) {
            throw $this->galat($kolom, 'Tentukan bulan kegiatan dulu sebelum membuat kuitansi.');
        }

        if (($alasan = KelayakanKuitansi::alasanTolakAcuan($acuan)) !== null) {
            throw $this->galat($kolom, $alasan);
        }

        if ($kegiatans->contains(fn (Kegiatan $k): bool => $k->kuitansi()->exists())) {
            throw $this->galat($kolom, 'Kegiatan ini sudah punya kuitansi.');
        }

        foreach ($kegiatans->where('id', '!=', $acuan->id) as $lain) {
            if (($alasan = KelayakanKuitansi::alasanTolakRapel($acuan, $lain)) !== null) {
                throw $this->galat($kolom, $alasan);
            }
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function nomorSudahDipakai(string $kolom, array $ids, string $nomor): ValidationException
    {
        $acuan = Kegiatan::query()->find($ids[0]);
        $terpakai = $acuan !== null && Kuitansi::query()
            ->where('sekolah_id', $acuan->sekolah_id)->where('tahun', $acuan->tahun)->where('nomor', $nomor)
            ->exists();

        return $terpakai
            ? $this->galat('nomor', 'Nomor ini sudah dipakai kuitansi lain.')
            : $this->galat($kolom, 'Kegiatan ini sudah punya kuitansi.');
    }

    private function galat(string $kolom, string $pesan): ValidationException
    {
        return ValidationException::withMessages([$kolom => $pesan]);
    }

    private function nomorUrut(string $nomor): int
    {
        if (preg_match('/^\d{1,9}(?!\d)/', $nomor, $m) !== 1) {
            throw $this->galat('nomor', 'Nomor bukti harus diawali angka urut (maksimal 9 digit).');
        }

        return (int) $m[0];
    }
}
