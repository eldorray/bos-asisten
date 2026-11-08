<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RkasRefBarangFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tahun
 * @property string|null $kode_rekening
 * @property string $nama
 * @property string $satuan
 * @property int $harga
 * @property int $batas_bawah
 * @property int $batas_atas
 * @property Carbon|null $kedaluwarsa
 */
#[Fillable(['tahun', 'kode_rekening', 'nama', 'satuan', 'harga', 'batas_bawah', 'batas_atas', 'kedaluwarsa'])]
class RkasRefBarang extends Model
{
    /** @use HasFactory<RkasRefBarangFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kedaluwarsa' => 'date',
        ];
    }
}
