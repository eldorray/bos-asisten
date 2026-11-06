<?php

declare(strict_types=1);

namespace App\Impor;

use App\Enums\SistemAnggaran;

/**
 * Membaca RKAM dari ERKAM berdasarkan posisi teks di halaman.
 */
class ErkamParser
{
    private const SUMBER_DANA = 'APBN - BOS';

    private const X_BLOK = 96.3;

    private const X_URAIAN = 177.9;

    private const X_KOEFISIEN = 458.7;

    private const TOLERANSI_X = 3.0;

    /**
     * @param  list<Token>  $token
     */
    public function parse(array $token): AnggaranTerbaca
    {
        $npsn = $this->identitas($token);
        $tahun = $this->tahun($token);
        $baris = [];
        $total = $this->bacaBlok($this->barisTabel($token), $baris);

        if ($baris === []) {
            throw new ImporDitolak('Tidak ada baris kegiatan di PDF RKAM ini.');
        }

        return new AnggaranTerbaca(SistemAnggaran::Erkam, $npsn, $tahun, null, self::SUMBER_DANA, $baris, $total);
    }

    /**
     * @param  list<Token>  $token
     */
    private function identitas(array $token): string
    {
        foreach ($token as $t) {
            if ($t->teks !== 'NSM' || $t->x >= 100) {
                continue;
            }

            foreach ($token as $nilai) {
                if (BacaToken::sebaris($nilai, $t) && $nilai->x > 150 && preg_match('/^:\s*(\d+)$/', $nilai->teks, $m) === 1) {
                    return $m[1];
                }
            }
        }

        throw new ImporDitolak('Kepala dokumen tidak memuat "NSM". Pastikan ini PDF RKAM dari ERKAM.');
    }

    /**
     * @param  list<Token>  $token
     */
    private function tahun(array $token): int
    {
        foreach ($token as $t) {
            if (preg_match('/^TAHUN ANGGARAN (\d{4})$/', $t->teks, $m) === 1) {
                return (int) $m[1];
            }
        }

        throw new ImporDitolak('Kepala dokumen tidak memuat "TAHUN ANGGARAN". Pastikan ini PDF RKAM dari ERKAM.');
    }

    /**
     * Baris tabel tanpa kepala halaman, kepala tabel berulang, dan baris nomor kolom.
     *
     * @param  list<Token>  $token
     * @return list<list<Token>>
     */
    private function barisTabel(array $token): array
    {
        $semua = BacaToken::barisBaris($token);

        /** @var array<int, float> $batas */
        $batas = [];

        foreach ($semua as $baris) {
            if (array_map(fn (Token $t) => $t->teks, $baris) === array_map('strval', range(1, 10))) {
                $batas[$baris[0]->halaman] = $baris[0]->y;
            }
        }

        return array_values(array_filter(
            $semua,
            fn (array $baris) => ! isset($batas[$baris[0]->halaman]) || $baris[0]->y < $batas[$baris[0]->halaman] - BacaToken::TOLERANSI_BARIS,
        ));
    }

    /**
     * @param  list<list<Token>>  $barisBaris
     * @param  list<BarisAnggaran>  $hasil
     */
    private function bacaBlok(array $barisBaris, array &$hasil): int
    {
        /** @var array{tahap: ?int, jumlah: int}|null $blok */
        $blok = null;
        /** @var array{nomor: string, kode: string, koefisien: string, harga: int, pajak: int, jumlah: int, uraian: ?string}|null $aktif */
        $aktif = null;
        $kode = null;
        $total = 0;

        $tutupKegiatan = function () use (&$aktif, &$blok, &$hasil): void {
            if ($aktif === null || $blok === null) {
                return;
            }

            $uraian = trim((string) preg_replace('/ \| -$/', '', (string) $aktif['uraian']));

            if ($aktif['uraian'] === null || $uraian === '') {
                throw new ImporDitolak("Uraian pada baris {$aktif['nomor']} tidak ditemukan.");
            }

            $hasil[] = new BarisAnggaran($aktif['kode'], null, $uraian, $aktif['koefisien'], $aktif['harga'], $aktif['pajak'], $aktif['jumlah'], $blok['tahap']);
            $blok['jumlah'] += $aktif['jumlah'];
            $aktif = null;
        };

        foreach ($barisBaris as $baris) {
            $halaman = $baris[0]->halaman;
            $nomor = $this->nomor($baris);
            $label = $nomor !== null ? "baris {$nomor}" : "halaman {$halaman}, \"{$baris[0]->teks}\"";
            $penanda = $this->tokenDi($baris, self::X_BLOK);
            $uraian = $this->tokenDi($baris, self::X_URAIAN);

            if ($penanda !== null) {
                if ($penanda->teks === 'Sub Total') {
                    $tutupKegiatan();

                    if ($blok === null || $blok['tahap'] === null) {
                        throw new ImporDitolak("Sub Total di halaman {$halaman} tidak berada di dalam blok yang dikenal.");
                    }

                    $total += $this->periksaSubTotal($baris, $blok['tahap'], $blok['jumlah']);
                    $blok = null;
                    $kode = null;

                    continue;
                }

                if (preg_match('/^Tahap (\d+)$/', $penanda->teks, $m) === 1) {
                    if ($blok === null || $blok['tahap'] !== null) {
                        throw new ImporDitolak("Tahap di halaman {$halaman} tidak berada tepat di bawah sumber dana.");
                    }

                    $blok['tahap'] = (int) $m[1];

                    continue;
                }

                if ($nomor === null) {
                    throw new ImporDitolak("Baris tidak terbaca pada {$label}.");
                }

                if ($blok !== null) {
                    throw new ImporDitolak("Sub Total belum ditemukan sebelum {$label}.");
                }

                if ($penanda->teks !== self::SUMBER_DANA) {
                    throw new ImporDitolak("Sumber dana \"{$penanda->teks}\" pada {$label} tidak didukung. Hanya \"".self::SUMBER_DANA.'" yang bisa diimpor.');
                }

                $blok = ['tahap' => null, 'jumlah' => 0];

                continue;
            }

            if ($blok === null) {
                if ($nomor !== null || $uraian !== null) {
                    throw new ImporDitolak("Baris di luar blok sumber dana tidak terbaca: {$label}.");
                }

                continue;
            }

            if ($blok['tahap'] === null) {
                throw new ImporDitolak("Tahap tidak ditemukan di bawah sumber dana sebelum {$label}.");
            }

            if ($nomor === null) {
                if ($uraian === null || count($baris) !== 1 || $aktif === null) {
                    throw new ImporDitolak("Baris tidak terbaca pada {$label}.");
                }

                if (str_starts_with($uraian->teks, '>>')) {
                    if ($aktif['uraian'] !== null) {
                        throw new ImporDitolak("Uraian ganda pada baris {$aktif['nomor']}.");
                    }

                    $aktif['uraian'] = trim(substr($uraian->teks, 2));
                } elseif ($aktif['uraian'] !== null && ! str_starts_with($uraian->teks, '>')) {
                    $aktif['uraian'] .= ' '.$uraian->teks;
                } else {
                    throw new ImporDitolak("Baris tidak terbaca pada {$label}.");
                }

                continue;
            }

            $tutupKegiatan();
            $kodeBaris = $this->kodeKelompok($baris);

            if ($uraian !== null && str_starts_with($uraian->teks, '>') && ! str_starts_with($uraian->teks, '>>')) {
                if ($kode === null) {
                    throw new ImporDitolak("Kode kegiatan untuk {$label} tidak ditemukan.");
                }

                $aktif = $this->kegiatan($baris, $nomor, $kode);
            } elseif ($kodeBaris !== null || ($uraian !== null && ! str_starts_with($uraian->teks, '>'))) {
                $kode = $kodeBaris ?? $kode;
            } else {
                throw new ImporDitolak("Baris tidak terbaca pada {$label}.");
            }
        }

        if ($blok !== null) {
            throw new ImporDitolak('Sub Total tidak ditemukan di akhir dokumen. Impor dibatalkan.');
        }

        return $total;
    }

    /**
     * @param  list<Token>  $baris
     * @return array{nomor: string, kode: string, koefisien: string, harga: int, pajak: int, jumlah: int, uraian: ?string}
     */
    private function kegiatan(array $baris, string $nomor, string $kode): array
    {
        $konteks = "baris {$nomor}";
        $koefisien = null;
        $angka = [];

        foreach ($baris as $t) {
            if ($t->x > 500) {
                $angka[] = $t;
            } elseif (abs($t->x - self::X_KOEFISIEN) <= self::TOLERANSI_X) {
                $koefisien = $t->teks;
            }
        }

        if ($koefisien === null) {
            throw new ImporDitolak("Koefisien pada {$konteks} tidak terbaca.");
        }

        if (count($angka) !== 5) {
            throw new ImporDitolak(sprintf('Kolom angka pada %s harus lima (harga, pajak, jumlah, tahap I, tahap II), terbaca %d.', $konteks, count($angka)));
        }

        [$harga, $pajak, $jumlah] = array_map(
            fn (Token $t, string $nama) => BacaToken::angka($t->teks, "{$nama} pada {$konteks}"),
            array_slice($angka, 0, 3),
            ['Harga', 'Pajak', 'Jumlah'],
        );

        foreach (array_slice($angka, 3) as $t) {
            BacaToken::angka($t->teks, "Tahap pada {$konteks}");
        }

        return ['nomor' => $nomor, 'kode' => $kode, 'koefisien' => $koefisien, 'harga' => $harga, 'pajak' => $pajak, 'jumlah' => $jumlah, 'uraian' => null];
    }

    /**
     * @param  list<Token>  $baris
     */
    private function periksaSubTotal(array $baris, int $tahap, int $terbaca): int
    {
        $nilai = [];

        foreach ($baris as $t) {
            if ($t->x > 500) {
                $nilai[] = BacaToken::angka($t->teks, "Sub Total Tahap {$tahap}");
            }
        }

        $bukanNol = array_values(array_filter($nilai, fn (int $n) => $n !== 0));

        if (count($nilai) !== 2 || count($bukanNol) > 1) {
            throw new ImporDitolak("Sub Total Tahap {$tahap} tidak terbaca.");
        }

        $diharapkan = $bukanNol[0] ?? 0;

        if ($diharapkan !== $terbaca) {
            throw new ImporDitolak(sprintf(
                'Sub Total Tahap %d tidak cocok: dokumen %s, terbaca %s. Impor dibatalkan.',
                $tahap,
                BacaToken::rupiah($diharapkan),
                BacaToken::rupiah($terbaca),
            ));
        }

        return $diharapkan;
    }

    /**
     * @param  list<Token>  $baris
     */
    private function nomor(array $baris): ?string
    {
        foreach ($baris as $t) {
            if ($t->x < 50 && preg_match('/^\d+$/', $t->teks) === 1) {
                return $t->teks;
            }
        }

        return null;
    }

    /**
     * @param  list<Token>  $baris
     */
    private function kodeKelompok(array $baris): ?string
    {
        foreach ($baris as $t) {
            if ($t->x >= 55 && $t->x <= 95 && preg_match('/^\d+(\.\d+)+$/', $t->teks) === 1) {
                return $t->teks;
            }
        }

        return null;
    }

    /**
     * @param  list<Token>  $baris
     */
    private function tokenDi(array $baris, float $x): ?Token
    {
        foreach ($baris as $t) {
            if (abs($t->x - $x) <= self::TOLERANSI_X) {
                return $t;
            }
        }

        return null;
    }
}
