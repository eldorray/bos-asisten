<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PakaiUuid;
use Database\Factories\KuitansiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $sekolah_id
 * @property int $tahun
 * @property int $bulan
 * @property int|null $tahap
 * @property int $nomor_urut
 * @property string $nomor
 * @property string $untuk_pembayaran
 * @property string $sumber_dana
 * @property string $penerima
 * @property Carbon $tanggal_lunas
 * @property int $jumlah
 */
#[Fillable([
    'sekolah_id', 'tahun', 'bulan', 'tahap', 'nomor_urut', 'nomor', 'untuk_pembayaran',
    'sumber_dana', 'penerima', 'tanggal_lunas', 'jumlah',
])]
class Kuitansi extends Model
{
    /** @use HasFactory<KuitansiFactory> */
    use HasFactory, PakaiUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_lunas' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Sekolah, $this>
     */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    /**
     * @return BelongsToMany<Kegiatan, $this>
     */
    public function kegiatans(): BelongsToMany
    {
        return $this->belongsToMany(Kegiatan::class, 'kegiatan_kuitansi');
    }
}
