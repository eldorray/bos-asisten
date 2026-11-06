<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JenisKegiatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JenisKegiatan>
 */
class JenisKegiatanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->words(2, true),
            'kata_kunci' => [],
            'urutan' => fake()->numberBetween(1, 20),
            'cadangan' => false,
        ];
    }
}
