<?php

declare(strict_types=1);

namespace App\Impor;

use App\Models\JenisKegiatan;
use Illuminate\Support\Collection;

/**
 * Menyarankan jenis kegiatan dari kata kunci pada uraian. Hanya saran: pemilik bisa mengubahnya.
 */
class PenyaranJenis
{
    /** @var Collection<int, JenisKegiatan>|null */
    private ?Collection $jenis = null;

    private ?JenisKegiatan $cadangan = null;

    public function untuk(string $uraian): JenisKegiatan
    {
        $this->jenis ??= JenisKegiatan::where('cadangan', false)->orderBy('urutan')->get();

        foreach ($this->jenis as $jenis) {
            foreach ($jenis->kata_kunci as $kata) {
                if ($kata !== '' && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($kata, '/').'(?![\p{L}\p{N}])/iu', $uraian) === 1) {
                    return $jenis;
                }
            }
        }

        return $this->cadangan ??= JenisKegiatan::where('cadangan', true)->firstOrFail();
    }
}
