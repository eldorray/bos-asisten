<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

final readonly class PerbandinganBulan
{
    /**
     * @param  list<array{kode_rekening: string, uraian: string, jumlah: int}>  $hanyaDiDraf
     * @param  list<array{kode: string, uraian: string, jumlah: int, kode_program: string|null}>  $hanyaDiImpor
     * @param  list<array{kode_rekening: string, uraian: string, draf: int, impor: int}>  $berbeda
     */
    public function __construct(
        public bool $sudahDiimpor,
        public int $totalDraf,
        public int $totalImpor,
        public array $hanyaDiDraf,
        public array $hanyaDiImpor,
        public array $berbeda,
    ) {}

    /**
     * @return array{sudahDiimpor: bool, totalDraf: int, totalImpor: int, hanyaDiDraf: list<array{kode_rekening: string, uraian: string, jumlah: int}>, hanyaDiImpor: list<array{kode: string, uraian: string, jumlah: int, kode_program: string|null}>, berbeda: list<array{kode_rekening: string, uraian: string, draf: int, impor: int}>}
     */
    public function props(): array
    {
        return [
            'sudahDiimpor' => $this->sudahDiimpor,
            'totalDraf' => $this->totalDraf,
            'totalImpor' => $this->totalImpor,
            'hanyaDiDraf' => $this->hanyaDiDraf,
            'hanyaDiImpor' => $this->hanyaDiImpor,
            'berbeda' => $this->berbeda,
        ];
    }
}
