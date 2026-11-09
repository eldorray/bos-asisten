<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Kuitansi;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UrutkanNomorKuitansi
{
    /**
     * Beri nomor ulang semua kuitansi satu sekolah dan tahun, tiap tahap mulai lagi dari awal.
     * Mengembalikan jumlah kuitansi yang nomornya berubah.
     */
    public function jalankan(Sekolah $sekolah, int $tahun): int
    {
        return DB::transaction(function () use ($sekolah, $tahun): int {
            $tahaps = Kuitansi::query()->where('sekolah_id', $sekolah->id)->where('tahun', $tahun)
                ->distinct()->pluck('tahap');

            return (int) $tahaps->sum(fn (mixed $tahap): int => $this->kelompok($sekolah, $tahun, $tahap === null ? null : (int) $tahap));
        });
    }

    /**
     * Urut bulan, tanggal lunas, lalu waktu dibuat. Hanya angka urut di depan nomor yang berganti;
     * sisa nomor ("/T2/MIDH/2026") tetap. Panggil di dalam transaksi.
     */
    public function kelompok(Sekolah $sekolah, int $tahun, ?int $tahap): int
    {
        $daftar = self::query($sekolah->id, $tahun, $tahap)
            ->orderBy('bulan')
            ->orderBy('tanggal_lunas')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $urut = self::nomorAwal($sekolah, $tahun, $tahap);
        $baru = [];

        foreach ($daftar as $kuitansi) {
            $baru[$kuitansi->id] = [$urut, preg_replace('/^\d+/', sprintf('%03d', $urut), $kuitansi->nomor, 1)];
            $urut++;
        }

        $berubah = $daftar->filter(fn (Kuitansi $k): bool => $baru[$k->id][1] !== $k->nomor);

        // Nomor harus unik per sekolah dan tahun; kosongkan dulu supaya penukaran nomor tidak bentrok.
        foreach ($berubah as $kuitansi) {
            $kuitansi->update(['nomor' => "sementara-{$kuitansi->id}"]);
        }

        foreach ($berubah as $kuitansi) {
            [$nomorUrut, $nomor] = $baru[$kuitansi->id];
            $kuitansi->update(['nomor_urut' => $nomorUrut, 'nomor' => $nomor]);
        }

        return $berubah->count();
    }

    /**
     * Kuitansi satu kelompok penomoran: per tahap bila ada tahap (e-RKAM), per tahun bila tidak (ARKAS).
     *
     * @return Builder<Kuitansi>
     */
    public static function query(int $sekolahId, int $tahun, ?int $tahap): Builder
    {
        return Kuitansi::query()->where('sekolah_id', $sekolahId)->where('tahun', $tahun)
            ->when($tahap === null, fn (Builder $q) => $q->whereNull('tahap'), fn (Builder $q) => $q->where('tahap', $tahap));
    }

    /**
     * Nomor awal tahun hanya berlaku untuk kelompok pertama; tahap berikutnya selalu mulai dari 1.
     */
    public static function nomorAwal(Sekolah $sekolah, int $tahun, ?int $tahap): int
    {
        return $tahap === null || $tahap === 1 ? (int) ($sekolah->nomor_awal[$tahun] ?? 1) : 1;
    }
}
