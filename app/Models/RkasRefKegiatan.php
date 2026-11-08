<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SumberDanaRkas;
use Database\Factories\RkasRefKegiatanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tahun
 * @property string $kode
 * @property string|null $kode_induk
 * @property string $uraian
 * @property int $level
 * @property string $sumber_dana
 * @property Carbon|null $kedaluwarsa
 */
#[Fillable(['tahun', 'kode', 'kode_induk', 'uraian', 'level', 'sumber_dana', 'kedaluwarsa'])]
class RkasRefKegiatan extends Model
{
    /** @use HasFactory<RkasRefKegiatanFactory> */
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

    /**
     * Kegiatan level 3 yang belum kedaluwarsa. Tanpa baris khusus untuk sumber dana itu,
     * dipakai daftar BOSP Reguler.
     *
     * @param  Builder<RkasRefKegiatan>  $query
     * @return Builder<RkasRefKegiatan>
     */
    public function scopeTersediaUntuk(Builder $query, int $tahun, SumberDanaRkas $sumber): Builder
    {
        $punyaSendiri = static::query()
            ->where('tahun', $tahun)
            ->where('sumber_dana', $sumber->value)
            ->exists();

        return $query
            ->where('tahun', $tahun)
            ->where('sumber_dana', $punyaSendiri ? $sumber->value : SumberDanaRkas::BospReguler->value)
            ->where('level', 3)
            ->where(fn (Builder $q) => $q->whereNull('kedaluwarsa')->orWhereDate('kedaluwarsa', '>', now()->toDateString()));
    }
}
