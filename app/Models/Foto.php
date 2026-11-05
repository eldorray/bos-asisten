<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $pemilik_type
 * @property int $pemilik_id
 * @property int $jenis_berkas_id
 * @property string $path
 * @property int $lebar
 * @property int $tinggi
 * @property int $urutan
 */
#[Fillable(['pemilik_type', 'pemilik_id', 'jenis_berkas_id', 'path', 'lebar', 'tinggi', 'urutan'])]
class Foto extends Model
{
    /** @use HasFactory<FotoFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleted(function (Foto $foto): void {
            Storage::disk('local')->delete($foto->path);
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function pemilik(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<JenisBerkas, $this>
     */
    public function jenisBerkas(): BelongsTo
    {
        return $this->belongsTo(JenisBerkas::class);
    }
}
