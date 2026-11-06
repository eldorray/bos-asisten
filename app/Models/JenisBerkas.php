<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TataLetakBerkas;
use Database\Factories\JenisBerkasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $jenis_kegiatan_id
 * @property string $nama
 * @property int $urutan
 * @property TataLetakBerkas $tata_letak
 */
#[Fillable(['jenis_kegiatan_id', 'nama', 'urutan', 'tata_letak'])]
class JenisBerkas extends Model
{
    /** @use HasFactory<JenisBerkasFactory> */
    use HasFactory;

    protected $table = 'jenis_berkas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tata_letak' => TataLetakBerkas::class,
        ];
    }

    /**
     * @return BelongsTo<JenisKegiatan, $this>
     */
    public function jenisKegiatan(): BelongsTo
    {
        return $this->belongsTo(JenisKegiatan::class);
    }
}
