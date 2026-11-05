<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SistemAnggaran;
use Database\Factories\SekolahFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nama
 * @property string $sebutan
 * @property SistemAnggaran $sistem
 * @property string $identitas
 * @property string $desa_kecamatan
 * @property string $kabupaten
 * @property string $provinsi
 * @property string $kode_bukti
 * @property string $nama_kepala
 * @property string $nama_bendahara
 * @property array<string, int> $nomor_awal
 */
#[Fillable([
    'nama', 'sebutan', 'sistem', 'identitas', 'desa_kecamatan', 'kabupaten', 'provinsi',
    'kode_bukti', 'nama_kepala', 'nama_bendahara', 'nomor_awal',
])]
class Sekolah extends Model
{
    /** @use HasFactory<SekolahFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sistem' => SistemAnggaran::class,
            'nomor_awal' => 'array',
        ];
    }

    /**
     * @return HasMany<Kegiatan, $this>
     */
    public function kegiatans(): HasMany
    {
        return $this->hasMany(Kegiatan::class);
    }

    /**
     * @return HasMany<ImporAnggaran, $this>
     */
    public function imporAnggarans(): HasMany
    {
        return $this->hasMany(ImporAnggaran::class);
    }
}
