<?php

declare(strict_types=1);

namespace App\Pdf;

/**
 * Posisi satu isi di lembar, dalam mm dari tepi kiri atas.
 */
final readonly class Penempatan
{
    /**
     * @param  string  $jenis  kuitansi|foto
     * @param  mixed  $isi  ?Kuitansi untuk jenis kuitansi, LampiranPdf untuk jenis foto.
     */
    public function __construct(
        public string $jenis,
        public float $x,
        public float $y,
        public float $lebar,
        public float $tinggi,
        public mixed $isi,
    ) {}
}
