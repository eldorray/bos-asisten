<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

use App\Models\RkasBaris;
use App\Models\RkasPtk;
use App\Models\RkasRefBarang;
use App\Models\RkasRefKegiatan;
use App\Models\RkasRefRekening;
use Illuminate\Database\Eloquent\Model;

class HapusReferensi
{
    public const PUNYA_TURUNAN = 'Kegiatan ini masih punya turunan. Hapus turunannya dulu.';

    /**
     * Hapus satu referensi. Mengembalikan alasan penolakan, atau null bila terhapus.
     */
    public function jalankan(Model $ref): ?string
    {
        $kolom = match (true) {
            $ref instanceof RkasRefKegiatan => 'ref_kegiatan_id',
            $ref instanceof RkasRefRekening => 'ref_rekening_id',
            $ref instanceof RkasRefBarang => 'ref_barang_id',
            $ref instanceof RkasPtk => 'ptk_id',
            default => null,
        };

        if ($kolom !== null) {
            $dipakai = RkasBaris::where($kolom, $ref->getKey())->count();

            if ($dipakai > 0) {
                return sprintf(PesanRkas::REFERENSI_DIPAKAI, $dipakai);
            }
        }

        if ($ref instanceof RkasRefKegiatan
            && RkasRefKegiatan::where('tahun', $ref->tahun)->where('kode_induk', $ref->kode)->exists()) {
            return self::PUNYA_TURUNAN;
        }

        $ref->delete();

        return null;
    }
}
