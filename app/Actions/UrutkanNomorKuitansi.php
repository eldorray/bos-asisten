<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Kuitansi;
use App\Models\Sekolah;
use Illuminate\Support\Facades\DB;

class UrutkanNomorKuitansi
{
    /**
     * Beri nomor ulang semua kuitansi satu sekolah dan tahun, urut bulan, tanggal lunas, lalu waktu dibuat.
     * Hanya angka urut di depan nomor yang berganti; sisa nomor ("/T1/MIDH/2026") tetap.
     * Mengembalikan jumlah kuitansi yang nomornya berubah.
     */
    public function jalankan(Sekolah $sekolah, int $tahun): int
    {
        return DB::transaction(function () use ($sekolah, $tahun): int {
            $daftar = Kuitansi::query()
                ->where('sekolah_id', $sekolah->id)
                ->where('tahun', $tahun)
                ->orderBy('bulan')
                ->orderBy('tanggal_lunas')
                ->orderBy('id')
                ->get();

            $urut = (int) ($sekolah->nomor_awal[$tahun] ?? 1);
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
        });
    }
}
