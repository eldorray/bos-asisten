<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Foto;
use App\Models\JenisBerkas;
use App\Models\Kegiatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Foto>
 */
class FotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pemilik_type' => Kegiatan::class,
            'pemilik_id' => Kegiatan::factory(),
            'jenis_berkas_id' => JenisBerkas::factory(),
            'path' => 'foto/'.fake()->uuid().'.jpg',
            'lebar' => 1600,
            'tinggi' => 1200,
            'urutan' => 1,
        ];
    }
}
