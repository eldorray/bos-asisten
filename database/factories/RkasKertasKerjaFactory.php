<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\JenisVersiRkas;
use App\Enums\StatusKertasKerja;
use App\Enums\SumberDanaRkas;
use App\Models\RkasKertasKerja;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RkasKertasKerja>
 */
class RkasKertasKerjaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'tahun' => 2027,
            'sumber_dana' => SumberDanaRkas::BospReguler,
            'pagu' => 100_000_000,
            'jenis_versi' => JenisVersiRkas::Awal,
            'nomor_pergeseran' => 0,
            'nomor_perubahan' => 0,
            'induk_id' => null,
            'tanggal_revisi' => null,
            'status' => StatusKertasKerja::Draf,
        ];
    }
}
