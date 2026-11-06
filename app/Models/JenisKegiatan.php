<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\JenisKegiatanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nama
 * @property array<int, string> $kata_kunci
 * @property int $urutan
 * @property bool $cadangan
 */
#[Fillable(['nama', 'kata_kunci', 'urutan', 'cadangan'])]
class JenisKegiatan extends Model
{
    /** @use HasFactory<JenisKegiatanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kata_kunci' => 'array',
            'cadangan' => 'boolean',
        ];
    }

    /**
     * @return HasMany<JenisBerkas, $this>
     */
    public function jenisBerkas(): HasMany
    {
        return $this->hasMany(JenisBerkas::class)->orderBy('urutan');
    }
}
