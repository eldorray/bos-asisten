<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\JenisKegiatan;
use Illuminate\Support\Facades\DB;

class SimpanJenisKegiatan
{
    /**
     * Simpan jenis kegiatan beserta daftar berkas berurutan. Berkas tanpa id dibuat baru,
     * berkas milik jenis ini yang tidak ada di daftar dihapus (validasi sudah menjamin tanpa foto).
     *
     * @param  array{nama: string, kata_kunci: list<string>, berkas: list<array{id?: int|null, nama: string, tata_letak: string}>}  $data
     */
    public function jalankan(JenisKegiatan $jenis, array $data): JenisKegiatan
    {
        return DB::transaction(function () use ($jenis, $data): JenisKegiatan {
            if (! $jenis->exists) {
                $jenis->urutan = (int) JenisKegiatan::max('urutan') + 1;
            }

            $jenis->fill(['nama' => $data['nama'], 'kata_kunci' => $data['kata_kunci']])->save();

            $dikirim = array_filter(array_map(fn (array $b): ?int => isset($b['id']) ? (int) $b['id'] : null, $data['berkas']));
            $jenis->jenisBerkas()->whereNotIn('id', $dikirim)->delete();

            foreach ($data['berkas'] as $i => $berkas) {
                $atribut = ['nama' => $berkas['nama'], 'tata_letak' => $berkas['tata_letak'], 'urutan' => $i + 1];

                if (isset($berkas['id'])) {
                    $jenis->jenisBerkas()->whereKey($berkas['id'])->firstOrFail()->update($atribut);
                } else {
                    $jenis->jenisBerkas()->create($atribut);
                }
            }

            return $jenis;
        });
    }
}
