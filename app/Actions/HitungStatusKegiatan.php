<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Kegiatan;
use App\Models\Kuitansi;

class HitungStatusKegiatan
{
    /**
     * Relasi yang harus dimuat sebelum memanggil untuk().
     */
    public const RELASI = ['jenisKegiatan.jenisBerkas', 'fotos', 'acara.fotos', 'kuitansi.kegiatans'];

    public static function untuk(Kegiatan $kegiatan): StatusKegiatan
    {
        $berkas = self::berkas($kegiatan);
        $kurang = count(array_filter($berkas, fn (array $b): bool => ! $b['ada']));
        $rapel = self::kuitansiRapel($kegiatan);

        if ($kegiatan->status_manual !== null) {
            $status = $kegiatan->status_manual->value;
        } elseif ($rapel !== null) {
            $status = 'dirapel';
        } elseif ($kurang === 0) {
            $status = 'selesai';
        } elseif ($kurang === count($berkas)) {
            $status = 'belum';
        } else {
            $status = 'kurang';
        }

        return new StatusKegiatan($status, $berkas, $kurang, $rapel, self::alasanPeriksa($kegiatan));
    }

    /**
     * @return list<array{nama: string, ada: bool, jumlahFoto: int}>
     */
    private static function berkas(Kegiatan $kegiatan): array
    {
        $daftar = [['nama' => 'Kuitansi', 'ada' => $kegiatan->kuitansi->isNotEmpty(), 'jumlahFoto' => 0]];
        $fotos = $kegiatan->pemilikBerkas()->fotos;

        foreach ($kegiatan->jenisKegiatan->jenisBerkas as $jenisBerkas) {
            $jumlah = $fotos->where('jenis_berkas_id', $jenisBerkas->id)->count();
            $daftar[] = ['nama' => $jenisBerkas->nama, 'ada' => $jumlah > 0, 'jumlahFoto' => $jumlah];
        }

        return $daftar;
    }

    private static function kuitansiRapel(Kegiatan $kegiatan): ?Kuitansi
    {
        $bulan = $kegiatan->bulanKerja();

        if ($bulan === null) {
            return null;
        }

        $kuitansi = $kegiatan->kuitansi->first();

        return $kuitansi !== null && $kuitansi->bulan > $bulan ? $kuitansi : null;
    }

    /**
     * Status hanya melihat kelengkapan berkas; hal yang butuh keputusan manusia dicatat terpisah
     * karena kuitansi yang mungkin sudah ditandatangani tidak boleh diubah otomatis.
     */
    private static function alasanPeriksa(Kegiatan $kegiatan): ?string
    {
        if ($kegiatan->status_manual !== null) {
            return null;
        }

        $kuitansi = $kegiatan->kuitansi->first();

        if ($kegiatan->hilang_dari_anggaran) {
            return $kuitansi !== null
                ? 'Tidak ada lagi di anggaran, tetapi punya kuitansi. Cek BKU di ARKAS.'
                : 'Tidak ada lagi di anggaran. Hapus kalau memang dibatalkan.';
        }

        if ($kuitansi === null) {
            return null;
        }

        $anggaran = (int) $kuitansi->kegiatans->sum('jumlah');

        return $anggaran === $kuitansi->jumlah
            ? null
            : sprintf('Kuitansi %s, anggaran %s. Periksa sebelum ditandatangani.', self::rupiah($kuitansi->jumlah), self::rupiah($anggaran));
    }

    private static function rupiah(int $nilai): string
    {
        return 'Rp '.number_format($nilai, 0, ',', '.');
    }
}
