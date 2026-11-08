<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RkasPemberiDanaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $kertas_kerja_id
 * @property string $nama
 * @property int $jumlah
 */
#[Fillable(['kertas_kerja_id', 'nama', 'jumlah'])]
class RkasPemberiDana extends Model
{
    /** @use HasFactory<RkasPemberiDanaFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<RkasKertasKerja, $this>
     */
    public function kertasKerja(): BelongsTo
    {
        return $this->belongsTo(RkasKertasKerja::class, 'kertas_kerja_id');
    }
}
