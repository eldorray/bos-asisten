<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SistemAnggaran;
use App\Models\Kegiatan;

/**
 * Satu aturan untuk "kegiatan ARKAS ini boleh dilaksanakan di bulan lain atau tidak".
 * ARKAS mengizinkannya tanpa pergeseran, termasuk lintas tahap, selama belum masuk BKU;
 * di aplikasi ini kuitansi diperlakukan setara dengan entri BKU.
 */
class TundaKegiatan
{
    private const BULAN_TERAKHIR = 12;

    public static function alasanTolak(Kegiatan $kegiatan): ?string
    {
        $kegiatan->loadMissing('sekolah');

        return match (true) {
            $kegiatan->sekolah->sistem === SistemAnggaran::Erkam => 'Kegiatan ERKAM dipindah lewat pilihan Bulan kegiatan.',
            $kegiatan->bulan === null => 'Tentukan bulan kegiatan dulu.',
            $kegiatan->kuitansi()->exists() => 'Hapus kuitansinya dulu sebelum mengubah bulan pelaksanaan.',
            $kegiatan->acara_id !== null => 'Pisahkan acaranya dulu sebelum menunda kegiatan ini.',
            $kegiatan->status_manual !== null => 'Kegiatan yang sudah ditandai manual tidak bisa ditunda.',
            $kegiatan->hilang_dari_anggaran => 'Kegiatan yang tidak ada lagi di anggaran tidak bisa ditunda.',
            default => null,
        };
    }

    /**
     * @return list<int>
     */
    public static function pilihan(Kegiatan $kegiatan): array
    {
        if (self::alasanTolak($kegiatan) !== null || $kegiatan->bulan === self::BULAN_TERAKHIR) {
            return [];
        }

        return range((int) $kegiatan->bulan + 1, self::BULAN_TERAKHIR);
    }

    /**
     * Pesan galat untuk nilai yang diminta, atau null kalau boleh. Null berarti batal menunda.
     */
    public static function pesan(Kegiatan $kegiatan, ?int $bulan): ?string
    {
        if ($bulan === $kegiatan->bulan_pelaksanaan) {
            return null;
        }

        $alasan = self::alasanTolak($kegiatan);

        if ($alasan !== null) {
            return $alasan;
        }

        return $bulan === null || in_array($bulan, self::pilihan($kegiatan), true)
            ? null
            : 'Bulan pelaksanaan harus setelah bulan anggaran, di tahun yang sama.';
    }
}
