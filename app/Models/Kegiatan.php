<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusManual;
use Database\Factories\KegiatanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $sekolah_id
 * @property int $tahun
 * @property int|null $bulan
 * @property int|null $tahap
 * @property int $periode_impor
 * @property string $kunci_impor
 * @property string $kode
 * @property string|null $kode_program
 * @property string $uraian
 * @property string $nama
 * @property string $koefisien
 * @property int $harga
 * @property int $pajak
 * @property int $jumlah
 * @property int $jenis_kegiatan_id
 * @property bool $jenis_dikonfirmasi
 * @property int|null $acara_id
 * @property StatusManual|null $status_manual
 * @property string|null $catatan_status
 * @property bool $hilang_dari_anggaran
 */
#[Fillable([
    'sekolah_id', 'tahun', 'bulan', 'tahap', 'periode_impor', 'kunci_impor', 'kode', 'kode_program',
    'uraian', 'nama', 'koefisien', 'harga', 'pajak', 'jumlah', 'jenis_kegiatan_id', 'jenis_dikonfirmasi',
    'acara_id', 'status_manual', 'catatan_status', 'hilang_dari_anggaran',
])]
class Kegiatan extends Model
{
    /** @use HasFactory<KegiatanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_manual' => StatusManual::class,
            'jenis_dikonfirmasi' => 'boolean',
            'hilang_dari_anggaran' => 'boolean',
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
     * @return BelongsTo<JenisKegiatan, $this>
     */
    public function jenisKegiatan(): BelongsTo
    {
        return $this->belongsTo(JenisKegiatan::class);
    }

    /**
     * @return BelongsTo<Acara, $this>
     */
    public function acara(): BelongsTo
    {
        return $this->belongsTo(Acara::class);
    }

    /**
     * @return BelongsToMany<Kuitansi, $this>
     */
    public function kuitansi(): BelongsToMany
    {
        return $this->belongsToMany(Kuitansi::class, 'kegiatan_kuitansi');
    }

    /**
     * @return MorphMany<Foto, $this>
     */
    public function fotos(): MorphMany
    {
        return $this->morphMany(Foto::class, 'pemilik');
    }

    /**
     * Pemilik berkas bersama: acaranya kalau ada, selain itu kegiatan ini sendiri.
     */
    public function pemilikBerkas(): Kegiatan|Acara
    {
        return $this->acara ?? $this;
    }
}
