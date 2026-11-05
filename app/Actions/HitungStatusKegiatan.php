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
    public const RELASI = ['jenisKegiatan.jenisBerkas', 'fotos', 'acara.fotos', 'kuitansi'];

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

        return new StatusKegiatan($status, $berkas, $kurang, $rapel);
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
        if ($kegiatan->bulan === null) {
            return null;
        }

        $kuitansi = $kegiatan->kuitansi->first();

        return $kuitansi !== null && $kuitansi->bulan > $kegiatan->bulan ? $kuitansi : null;
    }
}
