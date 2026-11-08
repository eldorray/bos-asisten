<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RkasKertasKerja;
use App\Models\RkasPemberiDana;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RkasPemberiDana>
 */
class RkasPemberiDanaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kertas_kerja_id' => RkasKertasKerja::factory(),
            'nama' => fake()->company(),
            'jumlah' => 10_000_000,
        ];
    }
}
