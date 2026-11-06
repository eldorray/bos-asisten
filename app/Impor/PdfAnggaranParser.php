<?php

declare(strict_types=1);

namespace App\Impor;

/**
 * Memilih parser ARKAS atau ERKAM dari judul dokumen.
 */
class PdfAnggaranParser
{
    public function parse(string $path): AnggaranTerbaca
    {
        return $this->parseToken((new PdfTokenReader)->baca($path));
    }

    /**
     * @param  list<Token>  $token
     */
    public function parseToken(array $token): AnggaranTerbaca
    {
        foreach ($token as $t) {
            if ($t->halaman !== 1) {
                continue;
            }

            if (str_contains($t->teks, 'RINCIAN KERTAS KERJA PERBULAN')) {
                return (new ArkasParser)->parse($token);
            }

            if (str_contains($t->teks, 'RENCANA KEGIATAN DAN ANGGARAN MADRASAH')) {
                return (new ErkamParser)->parse($token);
            }
        }

        throw new ImporDitolak('PDF ini bukan kertas kerja ARKAS atau RKAM ERKAM. Gunakan PDF asli dari aplikasi.');
    }
}
