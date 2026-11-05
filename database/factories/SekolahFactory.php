<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SistemAnggaran;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sekolah>
 */
class SekolahFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => 'SMP '.fake()->unique()->lastName(),
            'sebutan' => 'Sekolah',
            'sistem' => SistemAnggaran::Arkas,
            'identitas' => (string) fake()->unique()->numerify('########'),
            'desa_kecamatan' => 'Kec. '.fake()->citySuffix(),
            'kabupaten' => 'Kota Tangerang',
            'provinsi' => 'Banten',
            'kode_bukti' => strtoupper(fake()->unique()->lexify('????')),
            'nama_kepala' => fake()->name(),
            'nama_bendahara' => fake()->name(),
            'nomor_awal' => [],
        ];
    }

    public function erkam(): static
    {
        return $this->state(fn () => [
            'sebutan' => 'Madrasah',
            'sistem' => SistemAnggaran::Erkam,
        ]);
    }
}
