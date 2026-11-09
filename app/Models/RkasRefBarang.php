<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PakaiUuid;
use Database\Factories\RkasRefBarangFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
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
    use HasFactory, PakaiUuid;

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
     * Barang yang belum kedaluwarsa (kedaluwarsa kosong atau setelah hari ini).
     *
     * @param  Builder<RkasRefBarang>  $query
     * @return Builder<RkasRefBarang>
     */
    public function scopeBelumKedaluwarsa(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('kedaluwarsa')->orWhereDate('kedaluwarsa', '>', now()->toDateString()));
    }

    /**
     * Daftar barang yang berlaku untuk sebuah rekening pada tahunnya: barang dengan kode rekening yang sama,
     * dan untuk rekening 5.2 tanpa daftar sendiri, barang tanpa kode rekening.
     *
     * @param  Builder<RkasRefBarang>  $query
     * @return Builder<RkasRefBarang>
     */
    public function scopeUntukRekening(Builder $query, RkasRefRekening $rekening): Builder
    {
        $punyaSendiri = static::query()
            ->where('tahun', $rekening->tahun)
            ->where('kode_rekening', $rekening->kode)
            ->belumKedaluwarsa()
            ->exists();

        return $query
            ->where('tahun', $rekening->tahun)
            ->where(function (Builder $q) use ($rekening, $punyaSendiri): void {
                $q->where('kode_rekening', $rekening->kode);

                if (! $punyaSendiri && str_starts_with($rekening->kode, '5.2')) {
                    $q->orWhereNull('kode_rekening');
                }
            });
    }
}
