<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RkasBarisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $kertas_kerja_id
 * @property int $ref_kegiatan_id
 * @property int $ref_rekening_id
 * @property int|null $ref_barang_id
 * @property int|null $ptk_id
 * @property int|null $asal_baris_id
 * @property int $urutan
 * @property string $uraian
 * @property string $satuan
 * @property int $harga
 * @property array<int|string, int> $volume_bulan
 * @property string|null $keterangan
 */
#[Fillable([
    'kertas_kerja_id', 'ref_kegiatan_id', 'ref_rekening_id', 'ref_barang_id', 'ptk_id', 'asal_baris_id',
    'urutan', 'uraian', 'satuan', 'harga', 'volume_bulan', 'keterangan',
])]
class RkasBaris extends Model
{
    /** @use HasFactory<RkasBarisFactory> */
    use HasFactory;

    protected $table = 'rkas_baris';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'volume_bulan' => 'array',
        ];
    }

    /**
     * @return BelongsTo<RkasKertasKerja, $this>
     */
    public function kertasKerja(): BelongsTo
    {
        return $this->belongsTo(RkasKertasKerja::class, 'kertas_kerja_id');
    }

    /**
     * @return BelongsTo<RkasRefKegiatan, $this>
     */
    public function refKegiatan(): BelongsTo
    {
        return $this->belongsTo(RkasRefKegiatan::class, 'ref_kegiatan_id');
    }

    /**
     * @return BelongsTo<RkasRefRekening, $this>
     */
    public function refRekening(): BelongsTo
    {
        return $this->belongsTo(RkasRefRekening::class, 'ref_rekening_id');
    }

    /**
     * @return BelongsTo<RkasRefBarang, $this>
     */
    public function refBarang(): BelongsTo
    {
        return $this->belongsTo(RkasRefBarang::class, 'ref_barang_id');
    }

    /**
     * @return BelongsTo<RkasPtk, $this>
     */
    public function ptk(): BelongsTo
    {
        return $this->belongsTo(RkasPtk::class, 'ptk_id');
    }

    /**
     * @return BelongsTo<RkasBaris, $this>
     */
    public function asalBaris(): BelongsTo
    {
        return $this->belongsTo(self::class, 'asal_baris_id');
    }

    public function volume(): int
    {
        return (int) array_sum($this->volume_bulan);
    }

    public function jumlah(): int
    {
        return $this->volume() * $this->harga;
    }

    public function jumlahBulan(int $bulan): int
    {
        return (int) ($this->volume_bulan[$bulan] ?? 0) * $this->harga;
    }
}
