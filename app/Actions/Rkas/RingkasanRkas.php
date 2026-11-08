<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

final readonly class RingkasanRkas
{
    /**
     * @param  array<int, int>  $perBulan
     * @param  list<array{tahap: int, alokasi: int, dianggarkan: int, selisih: int}>  $tahap
     * @param  array{operasional: int, alat_mesin: int, aset: int}  $jenisBelanja
     * @param  array{honor: int, barang: int, jasa: int, pemeliharaan: int, perjalanan: int, peralatan_mesin: int, aset_lain: int}  $rincianBelanja
     * @param  list<array{nama: string, persen: float, batas: int, pesan: ?string}>  $juknis
     * @param  list<string>  $peringatan
     * @param  array<int, list<string>>  $masalahPerBaris
     */
    public function __construct(
        public int $pagu,
        public int $dianggarkan,
        public int $sisa,
        public array $perBulan,
        public array $tahap,
        public array $jenisBelanja,
        public array $rincianBelanja,
        public array $juknis,
        public array $peringatan,
        public array $masalahPerBaris,
        public int $jumlahBarisBermasalah,
    ) {}

    /**
     * Props Inertia ringkasan (tanpa pagu, yang sudah ada di kertas kerja, per bulan, dan pesan per baris).
     *
     * @return array<string, mixed>
     */
    public function props(): array
    {
        return [
            'dianggarkan' => $this->dianggarkan,
            'sisa' => $this->sisa,
            'tahap' => $this->tahap,
            'jenis_belanja' => $this->jenisBelanja,
            'rincian_belanja' => $this->rincianBelanja,
            'juknis' => $this->juknis,
            'peringatan' => $this->peringatan,
            'jumlah_baris_bermasalah' => $this->jumlahBarisBermasalah,
        ];
    }
}
