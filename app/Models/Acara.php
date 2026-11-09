<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PakaiUuid;
use Database\Factories\AcaraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string $uuid
 * @property int $sekolah_id
 * @property int $tahun
 * @property int $bulan
 * @property string $nama
 * @property int $jenis_kegiatan_id
 */
#[Fillable(['sekolah_id', 'tahun', 'bulan', 'nama', 'jenis_kegiatan_id'])]
class Acara extends Model
{
    /** @use HasFactory<AcaraFactory> */
    use HasFactory, PakaiUuid;

    /**
     * @return BelongsTo<Sekolah, $this>
     */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    /**
     * @return BelongsTo<JenisKegiatan, $this>
     */
    public function jenisKegiatan(): BelongsTo
    {
        return $this->belongsTo(JenisKegiatan::class);
    }

    /**
     * @return HasMany<Kegiatan, $this>
     */
    public function kegiatans(): HasMany
    {
        return $this->hasMany(Kegiatan::class);
    }

    /**
     * @return MorphMany<Foto, $this>
     */
    public function fotos(): MorphMany
    {
        return $this->morphMany(Foto::class, 'pemilik');
    }
}
