<?php

declare(strict_types=1);

namespace App\Impor;

use App\Enums\SistemAnggaran;

/**
 * Membaca kertas kerja per bulan dari ARKAS berdasarkan posisi teks di halaman.
 */
class ArkasParser
{
    private const JARAK_URAIAN_MAKS = 20.0;

    private const BULAN = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
    ];

    /**
     * @param  list<Token>  $token
     */
    public function parse(array $token): AnggaranTerbaca
    {
        $total = $this->totalDokumen($token);
        $npsn = $this->nilaiKepala($token, 'NPSN');
        $sumberDana = $this->nilaiKepala($token, 'Sumber Dana');
        [$bulan, $tahun] = $this->bulanDanTahun($this->nilaiKepala($token, 'Bulan'));

        $baris = $this->bacaBaris($token);

        if ($baris === []) {
            throw new ImporDitolak('Tidak ada baris kegiatan berkode rekening di PDF ini.');
        }

        $terbaca = array_sum(array_map(fn (BarisAnggaran $b) => $b->jumlah, $baris));

        if ($terbaca !== $total) {
            throw new ImporDitolak(sprintf(
                'Total tidak cocok: dokumen %s, terbaca %s. Impor dibatalkan.',
                BacaToken::rupiah($total),
                BacaToken::rupiah($terbaca),
            ));
        }

        return new AnggaranTerbaca(SistemAnggaran::Arkas, $npsn, $tahun, $bulan, $sumberDana, $baris, $total);
    }

    /**
     * @param  list<Token>  $token
     */
    private function totalDokumen(array $token): int
    {
        $terakhir = null;

        foreach ($token as $t) {
            if ($t->teks !== 'Jumlah' || $t->x >= 600) {
                continue;
            }

            if ($terakhir === null || $t->halaman > $terakhir->halaman
                || ($t->halaman === $terakhir->halaman && $t->y < $terakhir->y)) {
                $terakhir = $t;
            }
        }

        if ($terakhir === null) {
            throw new ImporDitolak('Baris "Jumlah" tidak ditemukan. Pastikan ini PDF kertas kerja per bulan dari ARKAS.');
        }

        foreach ($token as $nilai) {
            if (BacaToken::sebaris($nilai, $terakhir) && $nilai->x > 780) {
                return BacaToken::angka($nilai->teks, 'Jumlah total dokumen');
            }
        }

        throw new ImporDitolak('Nilai pada baris "Jumlah" tidak terbaca.');
    }

    /**
     * @param  list<Token>  $token
     */
    private function nilaiKepala(array $token, string $label): string
    {
        foreach ($token as $t) {
            if ($t->x >= 100 || $t->teks !== $label) {
                continue;
            }

            foreach ($token as $nilai) {
                if (BacaToken::sebaris($nilai, $t) && abs($nilai->x - 135) <= 5) {
                    return $nilai->teks;
                }
            }
        }

        throw new ImporDitolak("Kepala dokumen tidak memuat \"{$label}\". Pastikan ini PDF kertas kerja per bulan dari ARKAS.");
    }

    /**
     * @return array{int, int}
     */
    private function bulanDanTahun(string $teks): array
    {
        if (preg_match('/^(\p{L}+)\s+(\d{4})$/u', $teks, $m) !== 1 || ! isset(self::BULAN[mb_strtolower($m[1])])) {
            throw new ImporDitolak("Bulan \"{$teks}\" pada kepala dokumen tidak dikenali.");
        }

        return [self::BULAN[mb_strtolower($m[1])], (int) $m[2]];
    }

    /**
     * @param  list<Token>  $token
     * @return list<BarisAnggaran>
     */
    private function bacaBaris(array $token): array
    {
        $jangkar = array_values(array_filter(
            $token,
            fn (Token $t) => $t->x < 60 && preg_match('/^\d+\.$/', $t->teks) === 1,
        ));

        $barisJumlah = array_values(array_filter(
            $token,
            fn (Token $t) => $t->teks === 'Jumlah' && $t->x < 600,
        ));

        /** @var array<int, list<Token>> $uraianPerJangkar */
        $uraianPerJangkar = [];

        foreach ($token as $t) {
            if ($t->x < 205 || $t->x >= 565 || ($t->teks === 'Uraian' && $t->x > 300)) {
                continue;
            }

            foreach ($barisJumlah as $j) {
                if (BacaToken::sebaris($t, $j)) {
                    continue 2;
                }
            }

            $terdekat = null;
            $selisih = self::JARAK_URAIAN_MAKS;

            foreach ($jangkar as $i => $j) {
                $d = abs($j->y - $t->y);

                if ($j->halaman === $t->halaman && $d < $selisih) {
                    $selisih = $d;
                    $terdekat = $i;
                }
            }

            if ($terdekat !== null) {
                $uraianPerJangkar[$terdekat][] = $t;
            }
        }

        $hasil = [];

        foreach ($jangkar as $i => $j) {
            $kolom = array_values(array_filter($token, fn (Token $t) => BacaToken::sebaris($t, $j)));
            $kode = $this->kolom($kolom, 0, 160, fn (Token $t) => preg_match('/^\d(\.\d+){5}$/', $t->teks) === 1);

            $nomor = rtrim($j->teks, '.');

            if ($kode === null) {
                if ($this->kolom($kolom, 60, 160) !== null) {
                    throw new ImporDitolak("Kode rekening pada baris {$nomor} tidak dikenali.");
                }

                continue;
            }

            $jumlah = $this->kolom($kolom, 780, PHP_FLOAT_MAX);

            if ($jumlah === null) {
                throw new ImporDitolak("Jumlah pada baris {$nomor} tidak terbaca.");
            }

            $program = $this->kolom($kolom, 160, 205, fn () => true, true);
            $volume = $this->kolom($kolom, 571, 622);
            $satuan = $this->kolom($kolom, 622, 690);
            $tarif = $this->kolom($kolom, 690, 780);

            $hasil[] = new BarisAnggaran(
                $kode,
                $program !== null ? rtrim(str_replace(' ', '', $program), '.') : null,
                $this->susunUraian($uraianPerJangkar[$i] ?? []),
                trim(($volume ?? '').' '.($satuan ?? '')),
                $tarif !== null ? BacaToken::angka($tarif, "Tarif pada baris {$nomor}") : 0,
                0,
                BacaToken::angka($jumlah, "Jumlah pada baris {$nomor}"),
                null,
            );
        }

        return $hasil;
    }

    /**
     * @param  list<Token>  $kolom
     * @param  (callable(Token): bool)|null  $saring
     */
    private function kolom(array $kolom, float $dari, float $sampai, ?callable $saring = null, bool $gabung = false): ?string
    {
        $terpilih = array_values(array_filter(
            $kolom,
            fn (Token $t) => $t->x >= $dari && $t->x < $sampai && ($saring === null || $saring($t)),
        ));

        if ($terpilih === []) {
            return null;
        }

        usort($terpilih, fn (Token $a, Token $b) => $a->x <=> $b->x);

        return implode($gabung ? '' : ' ', array_map(fn (Token $t) => $t->teks, $terpilih));
    }

    /**
     * @param  list<Token>  $potongan
     */
    private function susunUraian(array $potongan): string
    {
        usort($potongan, fn (Token $a, Token $b) => abs($a->y - $b->y) <= BacaToken::TOLERANSI_BARIS
            ? $a->x <=> $b->x
            : $b->y <=> $a->y);

        return trim(implode(' ', array_map(fn (Token $t) => $t->teks, $potongan)));
    }
}
