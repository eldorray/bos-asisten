<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Acara;
use App\Models\Foto;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;

class HapusJenisKegiatan
{
    /**
     * Hapus jenis kegiatan beserta berkasnya. Mengembalikan alasan penolakan, atau null bila terhapus.
     */
    public function jalankan(JenisKegiatan $jenis): ?string
    {
        if ($jenis->cadangan) {
            return 'Jenis kegiatan cadangan dipakai untuk kegiatan yang belum dikenali dan tidak boleh dihapus.';
        }

        if (Kegiatan::where('jenis_kegiatan_id', $jenis->id)->exists() || Acara::where('jenis_kegiatan_id', $jenis->id)->exists()) {
            return 'Jenis kegiatan ini masih dipakai kegiatan atau acara. Pindahkan dulu kegiatannya ke jenis lain.';
        }

        if (Foto::whereIn('jenis_berkas_id', $jenis->jenisBerkas()->pluck('id'))->exists()) {
            return 'Jenis kegiatan ini punya berkas yang sudah berisi foto dan tidak bisa dihapus.';
        }

        $jenis->delete();

        return null;
    }
}
