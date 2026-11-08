<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RkasRefSatuan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RkasRefSatuan>
 */
class RkasRefSatuanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->word(),
        ];
    }
}
