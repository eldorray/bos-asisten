<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kegiatan>
 */
class KegiatanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $harga = fake()->numberBetween(1, 100) * 10_000;
        $nama = fake()->unique()->words(3, true);

        return [
            'sekolah_id' => Sekolah::factory(),
            'tahun' => 2026,
            'bulan' => fake()->numberBetween(1, 12),
            'tahap' => null,
            'periode_impor' => 1,
            'kunci_impor' => fake()->unique()->bothify('??-####'),
            'kode' => fake()->numerify('5.1.02.01.01.####'),
            'kode_program' => null,
            'uraian' => $nama,
            'nama' => $nama,
            'koefisien' => '1 Paket',
            'harga' => $harga,
            'pajak' => 0,
            'jumlah' => $harga,
            'jenis_kegiatan_id' => JenisKegiatan::factory(),
            'jenis_dikonfirmasi' => false,
            'acara_id' => null,
            'status_manual' => null,
            'catatan_status' => null,
            'hilang_dari_anggaran' => false,
        ];
    }
}
