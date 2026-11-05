<?php

declare(strict_types=1);

namespace App\Impor;

final readonly class BarisAnggaran
{
    public function __construct(
        public ?string $kode,
        public ?string $kodeProgram,
        public string $uraian,
        public string $koefisien,
        public int $harga,
        public int $pajak,
        public int $jumlah,
        public ?int $tahap,
    ) {}
}
