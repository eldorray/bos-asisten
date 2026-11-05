<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Acara;
use App\Models\JenisKegiatan;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Acara>
 */
class AcaraFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'tahun' => 2026,
            'bulan' => fake()->numberBetween(1, 12),
            'nama' => 'Rapat '.fake()->word(),
            'jenis_kegiatan_id' => JenisKegiatan::factory(),
        ];
    }
}
