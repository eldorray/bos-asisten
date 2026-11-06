<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TataLetakBerkas;
use App\Models\JenisBerkas;
use App\Models\JenisKegiatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JenisBerkas>
 */
class JenisBerkasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jenis_kegiatan_id' => JenisKegiatan::factory(),
            'nama' => fake()->unique()->words(2, true),
            'urutan' => fake()->numberBetween(1, 10),
            'tata_letak' => TataLetakBerkas::Penuh,
        ];
    }
}
