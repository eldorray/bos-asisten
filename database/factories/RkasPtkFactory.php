<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\JenisPtk;
use App\Models\RkasPtk;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RkasPtk>
 */
class RkasPtkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'nama' => fake()->name(),
            'nuptk' => null,
            'jenis' => JenisPtk::Guru,
        ];
    }
}
