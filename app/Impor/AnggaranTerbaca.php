<?php

declare(strict_types=1);

namespace App\Impor;

use App\Enums\SistemAnggaran;

final readonly class AnggaranTerbaca
{
    /**
     * @param  list<BarisAnggaran>  $baris
     */
    public function __construct(
        public SistemAnggaran $sistem,
        public string $identitas,
        public int $tahun,
        public ?int $bulan,
        public string $sumberDana,
        public array $baris,
        public int $totalDokumen,
    ) {}
}
