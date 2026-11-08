<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RkasRefBarang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RkasRefBarang>
 */
class RkasRefBarangFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tahun' => 2027,
            'kode_rekening' => null,
            'nama' => fake()->unique()->words(3, true),
            'satuan' => 'Buah',
            'harga' => 10_000,
            'batas_bawah' => 0,
            'batas_atas' => 0,
            'kedaluwarsa' => null,
        ];
    }
}
