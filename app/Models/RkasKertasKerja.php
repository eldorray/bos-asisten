<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JenisVersiRkas;
use App\Enums\StatusKertasKerja;
use App\Enums\SumberDanaRkas;
use App\Models\Concerns\PakaiUuid;
use Database\Factories\RkasKertasKerjaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $sekolah_id
 * @property int $tahun
 * @property SumberDanaRkas $sumber_dana
 * @property int $pagu
 * @property JenisVersiRkas $jenis_versi
 * @property int $nomor_pergeseran
 * @property int $nomor_perubahan
 * @property int|null $induk_id
 * @property Carbon|null $tanggal_revisi
 * @property StatusKertasKerja $status
 */
#[Fillable([
    'sekolah_id', 'tahun', 'sumber_dana', 'pagu', 'jenis_versi', 'nomor_pergeseran', 'nomor_perubahan',
    'induk_id', 'tanggal_revisi', 'status',
])]
class RkasKertasKerja extends Model
{
    /** @use HasFactory<RkasKertasKerjaFactory> */
    use HasFactory, PakaiUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sumber_dana' => SumberDanaRkas::class,
            'jenis_versi' => JenisVersiRkas::class,
            'status' => StatusKertasKerja::class,
            'tanggal_revisi' => 'date',
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
     * @return HasMany<RkasBaris, $this>
     */
    public function baris(): HasMany
    {
        return $this->hasMany(RkasBaris::class, 'kertas_kerja_id');
    }

    /**
     * @return HasMany<RkasPemberiDana, $this>
     */
    public function pemberiDanas(): HasMany
    {
        return $this->hasMany(RkasPemberiDana::class, 'kertas_kerja_id');
    }

    /**
     * @return BelongsTo<RkasKertasKerja, $this>
     */
    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'induk_id');
    }

    /**
     * Seluruh versi dalam rantai yang sama (induk dan turunan), dari yang paling awal.
     *
     * @return Collection<int, RkasKertasKerja>
     */
    public function rantai(): Collection
    {
        $akar = $this;
        while ($akar->induk_id !== null) {
            $akar = self::query()->findOrFail($akar->induk_id);
        }

        $id = [$akar->id];
        $tingkat = [$akar->id];
        while ($tingkat !== []) {
            $tingkat = self::query()->whereIn('induk_id', $tingkat)->pluck('id')->all();
            $id = [...$id, ...$tingkat];
        }

        return self::query()->whereIn('id', $id)->orderBy('id')->get();
    }

    public function bisaDiubah(): bool
    {
        return $this->status === StatusKertasKerja::Draf;
    }

    /**
     * Nama versi seperti di ARKAS: "Awal", "Pergeseran 1", "Perubahan 1 / Pergeseran 2".
     */
    public function labelVersi(): string
    {
        return match ($this->jenis_versi) {
            JenisVersiRkas::Awal => 'Awal',
            JenisVersiRkas::Pergeseran => 'Pergeseran '.$this->nomor_pergeseran,
            JenisVersiRkas::Perubahan => 'Perubahan '.$this->nomor_perubahan
                .($this->nomor_pergeseran > 0 ? ' / Pergeseran '.$this->nomor_pergeseran : ''),
        };
    }

    public function label(): string
    {
        return $this->status === StatusKertasKerja::Disahkan ? 'RKAS' : 'Kertas Kerja';
    }
}
