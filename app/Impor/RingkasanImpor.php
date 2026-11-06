<?php

declare(strict_types=1);

namespace App\Impor;

use App\Models\Sekolah;

final readonly class RingkasanImpor
{
    public function __construct(
        public Sekolah $sekolah,
        public ?int $bulan,
        public int $tahun,
        public int $jumlahKegiatan,
        public int $total,
        public int $baru,
        public int $berubah,
        public int $hilang,
    ) {}
}
