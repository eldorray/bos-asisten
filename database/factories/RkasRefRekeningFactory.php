<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RkasRefRekening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RkasRefRekening>
 */
class RkasRefRekeningFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tahun' => 2027,
            'kode' => fake()->unique()->numerify('5.1.02.01.01.####'),
            'nama' => fake()->unique()->words(3, true),
            'kedaluwarsa' => null,
        ];
    }
}
