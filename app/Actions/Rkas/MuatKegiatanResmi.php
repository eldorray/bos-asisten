<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

use App\Enums\SumberDanaRkas;
use App\Models\RkasRefKegiatan;
use Illuminate\Support\Facades\DB;

class MuatKegiatanResmi
{
    private const BERKAS = 'database/data/kegiatan-bos-arkas.json';

    /**
     * Muat kode referensi kegiatan BOS dari tabel resmi ARKAS 4 (program, komponen, kegiatan).
     * Kode yang sudah ada dilewati, jadi hasil edit manual tidak tertimpa dan aman diulang.
     *
     * @return int jumlah kode yang baru dibuat
     */
    public function jalankan(int $tahun): int
    {
        /** @var array{program: array<string, string>, komponen: array<string, string>, kegiatan: list<array{0: string, 1: string}>} $data */
        $data = json_decode((string) file_get_contents(base_path(self::BERKAS)), true, flags: JSON_THROW_ON_ERROR);

        $baris = [];
        foreach ($data['program'] as $kode => $uraian) {
            $baris[] = ["{$kode}.", null, $uraian, 1];
        }
        foreach ($data['komponen'] as $kode => $uraian) {
            $baris[] = ["{$kode}.", substr($kode, 0, 2).'.', $uraian, 2];
        }
        foreach ($data['kegiatan'] as [$kode, $uraian]) {
            $baris[] = ["{$kode}.", substr($kode, 0, 5).'.', $uraian, 3];
        }

        return DB::transaction(function () use ($tahun, $baris): int {
            $sudah = RkasRefKegiatan::query()
                ->where('tahun', $tahun)
                ->where('sumber_dana', SumberDanaRkas::BospReguler->value)
                ->pluck('kode')
                ->flip();

            $baru = 0;
            foreach ($baris as [$kode, $induk, $uraian, $level]) {
                if (isset($sudah[$kode])) {
                    continue;
                }
                RkasRefKegiatan::create([
                    'tahun' => $tahun,
                    'kode' => $kode,
                    'kode_induk' => $induk,
                    'uraian' => $uraian,
                    'level' => $level,
                    'sumber_dana' => SumberDanaRkas::BospReguler->value,
                ]);
                $baru++;
            }

            return $baru;
        });
    }
}
