<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SistemAnggaran;
use App\Models\Kegiatan;
use App\Models\Sekolah;

class SaranAcara
{
    /**
     * Kelompok baris rapat yang mungkin satu acara: jenis, bulan, dan kode program
     * (ARKAS) atau kode (ERKAM) yang sama, minimal dua baris. Hanya saran; tidak ada
     * yang digabung otomatis.
     *
     * @return list<list<Kegiatan>>
     */
    public static function untukBulan(Sekolah $sekolah, int $tahun, int $bulan): array
    {
        $kolom = $sekolah->sistem === SistemAnggaran::Arkas ? 'kode_program' : 'kode';

        $kelompok = Kegiatan::query()
            ->where('sekolah_id', $sekolah->id)
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->whereNull('bulan_pelaksanaan')
            ->whereNull('acara_id')
            ->whereNull('status_manual')
            ->where('hilang_dari_anggaran', false)
            ->whereNotNull($kolom)
            ->whereDoesntHave('kuitansi', fn ($q) => $q->where('bulan', '>', $bulan))
            ->whereHas('jenisKegiatan', fn ($q) => $q->where('nama', 'like', '%Rapat%'))
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Kegiatan $k): string => $k->jenis_kegiatan_id.'|'.$k->getAttribute($kolom));

        $saran = [];

        foreach ($kelompok as $baris) {
            if ($baris->count() >= 2) {
                $saran[] = array_values($baris->all());
            }
        }

        return $saran;
    }
}
