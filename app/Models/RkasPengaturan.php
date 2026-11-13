<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PakaiUuid;
use Database\Factories\RkasPengaturanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $sekolah_id
 * @property int $tahun
 * @property bool $swasta
 * @property int $persen_tahap_1
 * @property int $ambang_tahap_1
 * @property int $honor_maks_negeri
 * @property int $honor_maks_swasta
 * @property int $buku_min
 * @property int $sarpras_maks
 * @property list<string> $kode_honor
 * @property string $rekening_honor
 * @property list<string> $kode_buku
 * @property list<string> $kode_sarpras
 * @property list<string> $rekening_sarpras_lain
 * @property int|null $kuota_pergeseran
 * @property int|null $kuota_perubahan
 */
#[Fillable([
    'sekolah_id', 'tahun', 'swasta', 'persen_tahap_1', 'ambang_tahap_1', 'honor_maks_negeri', 'honor_maks_swasta',
    'buku_min', 'sarpras_maks', 'kode_honor', 'rekening_honor', 'kode_buku', 'kode_sarpras', 'rekening_sarpras_lain',
    'kuota_pergeseran', 'kuota_perubahan',
])]
class RkasPengaturan extends Model
{
    /** @use HasFactory<RkasPengaturanFactory> */
    use HasFactory, PakaiUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'swasta' => 'boolean',
            'kode_honor' => 'array',
            'kode_buku' => 'array',
            'kode_sarpras' => 'array',
            'rekening_sarpras_lain' => 'array',
        ];
    }

    /**
     * Nilai awal sesuai juknis (SMP, 2026 ke atas). Bawaan swasta: sekolah pemakai aplikasi ini di bawah yayasan.
     *
     * @return array<string, mixed>
     */
    public static function nilaiAwal(): array
    {
        return [
            'swasta' => true,
            'persen_tahap_1' => 50,
            'ambang_tahap_1' => 25,
            'honor_maks_negeri' => 20,
            'honor_maks_swasta' => 40,
            'buku_min' => 10,
            'sarpras_maks' => 20,
            'kode_honor' => ['07.12.01.', '07.12.02.', '07.12.03.', '07.12.04.'],
            'rekening_honor' => '5.1.02.02.01.0013',
            'kode_buku' => ['03.02.02.', '05.02.02.', '05.02.03.', '05.02.04.', '05.02.05.'],
            'kode_sarpras' => ['05.08.01.', '05.08.03.', '05.08.05.', '05.08.06.', '05.08.08.', '05.08.09.', '05.08.10.', '05.08.12.'],
            'rekening_sarpras_lain' => ['5.2.02.05.02.0001'],
            'kuota_pergeseran' => null,
            'kuota_perubahan' => null,
        ];
    }

    public static function untuk(Sekolah $sekolah, int $tahun): self
    {
        return static::firstOrCreate(
            ['sekolah_id' => $sekolah->id, 'tahun' => $tahun],
            static::nilaiAwal(),
        );
    }

    /**
     * @return BelongsTo<Sekolah, $this>
     */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }
}
