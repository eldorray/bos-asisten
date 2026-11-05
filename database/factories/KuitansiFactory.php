<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Kuitansi;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kuitansi>
 */
class KuitansiFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $urut = fake()->unique()->numberBetween(1, 9999);

        return [
            'sekolah_id' => Sekolah::factory(),
            'tahun' => 2026,
            'bulan' => 1,
            'tahap' => null,
            'nomor_urut' => $urut,
            'nomor' => sprintf('%03d/01/TEST/2026', $urut),
            'untuk_pembayaran' => fake()->sentence(3),
            'sumber_dana' => 'BOS',
            'penerima' => fake()->name(),
            'tanggal_lunas' => '2026-01-15',
            'jumlah' => fake()->numberBetween(1, 100) * 10_000,
        ];
    }
}
