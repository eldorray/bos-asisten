<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RkasPengaturan;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RkasPengaturan>
 */
class RkasPengaturanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'tahun' => 2027,
            ...RkasPengaturan::nilaiAwal(),
        ];
    }
}
