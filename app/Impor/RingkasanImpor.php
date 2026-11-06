<?php

declare(strict_types=1);

namespace App\Impor;

use App\Models\Sekolah;

final readonly class RingkasanImpor
{
    /**
     * @param  array{baru: list<array{nama: string, jumlah: int}>, berubah: list<array{nama: string, koefisienLama: string, koefisienBaru: string, jumlahLama: int, jumlahBaru: int, adaKuitansi: bool}>, hilang: list<array{nama: string, jumlah: int, disimpan: bool}>}  $rincian
     */
    public function __construct(
        public Sekolah $sekolah,
        public ?int $bulan,
        public int $tahun,
        public int $jumlahKegiatan,
        public int $total,
        public int $baru,
        public int $berubah,
        public int $hilang,
        public array $rincian,
    ) {}
}
