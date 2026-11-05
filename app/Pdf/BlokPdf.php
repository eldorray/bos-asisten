<?php

declare(strict_types=1);

namespace App\Pdf;

use App\Models\Kuitansi;

/**
 * Kuitansi sebuah kegiatan atau acara beserta lampirannya.
 */
final readonly class BlokPdf
{
    /**
     * @param  list<?Kuitansi>  $kuitansi  null berarti kuitansi belum dibuat.
     * @param  list<LampiranPdf>  $lampiran
     */
    public function __construct(
        public array $kuitansi,
        public array $lampiran,
    ) {}
}
