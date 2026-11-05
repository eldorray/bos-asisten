<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ImporAnggaranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sekolah_id
 * @property int $tahun
 * @property int $periode
 * @property int $total
 * @property string $nama_file
 */
#[Fillable(['sekolah_id', 'tahun', 'periode', 'total', 'nama_file'])]
class ImporAnggaran extends Model
{
    /** @use HasFactory<ImporAnggaranFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Sekolah, $this>
     */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }
}
