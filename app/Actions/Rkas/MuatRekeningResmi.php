<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

use App\Models\RkasRefRekening;
use App\Models\RkasRefSatuan;
use Illuminate\Support\Facades\DB;

class MuatRekeningResmi
{
    private const BERKAS = 'database/data/rekening-belanja-bosp.json';

    /**
     * Muat rekening belanja BOSP yang lazim di kertas kerja ARKAS beserta satuan umum.
     * Yang sudah ada dilewati, jadi hasil edit manual tidak tertimpa dan aman diulang.
     *
     * @return array{rekening: int, satuan: int} jumlah yang baru dibuat
     */
    public function jalankan(int $tahun): array
    {
        /** @var array{rekening: list<array{0: string, 1: string}>, satuan: list<string>} $data */
        $data = json_decode((string) file_get_contents(base_path(self::BERKAS)), true, flags: JSON_THROW_ON_ERROR);

        return DB::transaction(function () use ($tahun, $data): array {
            $kodeAda = RkasRefRekening::query()->where('tahun', $tahun)->pluck('kode')->flip();
            $satuanAda = RkasRefSatuan::query()->pluck('nama')->flip();
            $baru = ['rekening' => 0, 'satuan' => 0];

            foreach ($data['rekening'] as [$kode, $nama]) {
                if (! isset($kodeAda[$kode])) {
                    RkasRefRekening::create(['tahun' => $tahun, 'kode' => $kode, 'nama' => $nama]);
                    $baru['rekening']++;
                }
            }

            foreach ($data['satuan'] as $nama) {
                if (! isset($satuanAda[$nama])) {
                    RkasRefSatuan::create(['nama' => $nama]);
                    $baru['satuan']++;
                }
            }

            return $baru;
        });
    }
}
