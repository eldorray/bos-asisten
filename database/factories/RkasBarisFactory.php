<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;
use App\Models\RkasRefKegiatan;
use App\Models\RkasRefRekening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RkasBaris>
 */
class RkasBarisFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kertas_kerja_id' => RkasKertasKerja::factory(),
            'ref_kegiatan_id' => RkasRefKegiatan::factory(),
            'ref_rekening_id' => RkasRefRekening::factory(),
            'ref_barang_id' => null,
            'ptk_id' => null,
            'asal_baris_id' => null,
            'urutan' => 1,
            'uraian' => fake()->sentence(3),
            'satuan' => 'Buah',
            'harga' => 10_000,
            'volume_bulan' => array_replace(array_fill_keys(range(1, 12), 0), [1 => 1]),
        ];
    }
}
