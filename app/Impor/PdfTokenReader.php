<?php

declare(strict_types=1);

namespace App\Impor;

use Smalot\PdfParser\Parser;
use Throwable;

class PdfTokenReader
{
    /**
     * Membaca setiap potongan teks beserta posisinya (halaman mulai dari 1).
     *
     * @return list<Token>
     */
    public function baca(string $path): array
    {
        try {
            $dokumen = (new Parser)->parseFile($path);
            $token = [];

            foreach (array_values($dokumen->getPages()) as $indeks => $halaman) {
                foreach ($halaman->getDataTm() as $potongan) {
                    $teks = trim((string) $potongan[1]);

                    if ($teks === '') {
                        continue;
                    }

                    $token[] = new Token(
                        $indeks + 1,
                        (float) $potongan[0][4],
                        (float) $potongan[0][5],
                        $teks,
                    );
                }
            }
        } catch (Throwable) {
            throw new ImporDitolak('File tidak bisa dibaca sebagai PDF. Gunakan PDF asli dari aplikasi.');
        }

        if ($token === []) {
            throw new ImporDitolak('PDF tidak berisi teks. Gunakan PDF asli dari aplikasi, bukan hasil pindai.');
        }

        return $token;
    }
}
