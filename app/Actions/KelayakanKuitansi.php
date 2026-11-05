<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Kegiatan;
use Illuminate\Database\Eloquent\Collection;

/**
 * Satu aturan untuk "baris mana yang boleh dirapel bersama kegiatan ini", dipakai
 * daftar kandidat di layar dan pemeriksaan saat menyimpan.
 */
class KelayakanKuitansi
{
    /**
     * Alasan baris $lain tidak boleh dirapel bersama $acuan, atau null kalau boleh.
     */
    public static function alasanTolakRapel(Kegiatan $acuan, Kegiatan $lain): ?string
    {
        $sama = $lain->sekolah_id === $acuan->sekolah_id
            && $lain->tahun === $acuan->tahun
            && $lain->tahap === $acuan->tahap
            && $lain->kode === $acuan->kode
            && $lain->kode_program === $acuan->kode_program
            && $lain->uraian === $acuan->uraian;

        return match (true) {
            ! $sama => 'Rapel hanya untuk kegiatan yang sama: sekolah, tahun, tahap, kode, kode program, dan uraiannya harus sama.',
            $lain->bulan === null => 'Tentukan bulan kegiatan dulu sebelum membuat kuitansi.',
            $lain->acara_id !== null => 'Anggota acara tidak bisa dirapel.',
            $lain->status_manual !== null => 'Kegiatan yang sudah ditandai manual tidak bisa dirapel.',
            $lain->hilang_dari_anggaran => 'Kegiatan yang tidak ada lagi di anggaran tidak bisa dirapel.',
            default => null,
        };
    }

    public static function alasanTolakAcuan(Kegiatan $acuan): ?string
    {
        return $acuan->status_manual !== null
            ? 'Kegiatan yang sudah ditandai manual tidak dibuatkan kuitansi.'
            : null;
    }

    /**
     * @return Collection<int, Kegiatan>
     */
    public static function kandidatRapel(Kegiatan $acuan): Collection
    {
        if ($acuan->bulan === null || $acuan->acara_id !== null || $acuan->status_manual !== null) {
            return new Collection;
        }

        return Kegiatan::query()
            ->where('sekolah_id', $acuan->sekolah_id)
            ->where('tahun', $acuan->tahun)
            ->where('kode', $acuan->kode)
            ->where('uraian', $acuan->uraian)
            ->whereKeyNot($acuan->id)
            ->whereDoesntHave('kuitansi')
            ->orderBy('bulan')
            ->get()
            ->filter(fn (Kegiatan $k): bool => self::alasanTolakRapel($acuan, $k) === null)
            ->values();
    }
}
