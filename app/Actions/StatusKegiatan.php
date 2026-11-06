<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Kuitansi;

/**
 * Hasil hitung status satu kegiatan.
 *
 * @phpstan-type Berkas array{nama: string, ada: bool, jumlahFoto: int}
 */
final readonly class StatusKegiatan
{
    /**
     * @param  string  $status  selesai|kurang|belum|dirapel|tidak_dilaksanakan|di_luar_aplikasi
     * @param  list<Berkas>  $berkas
     */
    public function __construct(
        public string $status,
        public array $berkas,
        public int $kurang,
        public ?Kuitansi $kuitansiRapel,
        public ?string $perluDiperiksa = null,
    ) {}

    /**
     * Hanya kegiatan yang masih harus dikerjakan yang dianggap menunggak.
     */
    public function menunggak(): bool
    {
        return $this->status === 'kurang' || $this->status === 'belum';
    }
}
