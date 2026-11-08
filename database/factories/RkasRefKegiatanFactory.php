<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RkasRefKegiatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RkasRefKegiatan>
 */
class RkasRefKegiatanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tahun' => 2027,
            'kode' => fake()->unique()->numerify('07.##.##.'),
            'kode_induk' => null,
            'uraian' => fake()->unique()->sentence(3),
            'level' => 3,
            'sumber_dana' => 'bosp_reguler',
            'kedaluwarsa' => null,
        ];
    }
}
