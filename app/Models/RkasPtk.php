<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JenisPtk;
use App\Models\Concerns\PakaiUuid;
use Database\Factories\RkasPtkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $sekolah_id
 * @property string $nama
 * @property string|null $nuptk
 * @property JenisPtk $jenis
 */
#[Fillable(['sekolah_id', 'nama', 'nuptk', 'jenis'])]
class RkasPtk extends Model
{
    /** @use HasFactory<RkasPtkFactory> */
    use HasFactory, PakaiUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis' => JenisPtk::class,
        ];
    }

    /**
     * @return BelongsTo<Sekolah, $this>
     */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }
}
