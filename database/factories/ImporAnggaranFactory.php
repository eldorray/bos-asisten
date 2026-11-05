<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ImporAnggaran;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImporAnggaran>
 */
class ImporAnggaranFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'tahun' => 2026,
            'periode' => fake()->numberBetween(1, 12),
            'total' => fake()->numberBetween(1, 100) * 100_000,
            'nama_file' => 'anggaran.pdf',
        ];
    }
}
