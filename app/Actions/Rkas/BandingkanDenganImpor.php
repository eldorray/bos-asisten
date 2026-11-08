<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

use App\Enums\StatusKertasKerja;
use App\Models\ImporAnggaran;
use App\Models\Kegiatan;
use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;

/**
 * Perbandingan hanya-baca antara kertas kerja dan anggaran bulanan hasil impor PDF ARKAS.
 * Baris dengan kunci sama (kode rekening + uraian ternormalisasi) dijumlahkan per sisi,
 * karena kode rekening di impor tidak unik (mis. beberapa baris gaji).
 */
class BandingkanDenganImpor
{
    /**
     * Versi Disahkan terakhir dalam rantai, atau versi yang dibuka bila belum ada yang disahkan.
     */
    public function pembanding(RkasKertasKerja $kertasKerja): RkasKertasKerja
    {
        return $kertasKerja->rantai()
            ->filter(fn (RkasKertasKerja $k): bool => $k->status === StatusKertasKerja::Disahkan)
            ->last() ?? $kertasKerja;
    }

    public function untuk(RkasKertasKerja $kertasKerja, int $bulan): PerbandinganBulan
    {
        $sudahDiimpor = ImporAnggaran::query()
            ->where('sekolah_id', $kertasKerja->sekolah_id)
            ->where('tahun', $kertasKerja->tahun)
            ->where('periode', $bulan)
            ->exists();

        if (! $sudahDiimpor) {
            return new PerbandinganBulan(false, 0, 0, [], [], []);
        }

        $pembanding = $this->pembanding($kertasKerja);

        /** @var array<string, array{kode: string, uraian: string, jumlah: int, kode_program: string|null}> $draf */
        $draf = [];
        $pembanding->baris()->with('refRekening')->orderBy('urutan')->orderBy('id')->get()
            ->each(function (RkasBaris $baris) use (&$draf, $bulan): void {
                $jumlah = $baris->jumlahBulan($bulan);
                if ($jumlah <= 0) {
                    return;
                }
                $kode = $baris->refRekening->kode;
                $kunci = $this->kunci($kode, $baris->uraian);
                $draf[$kunci] ??= ['kode' => $kode, 'uraian' => $baris->uraian, 'jumlah' => 0, 'kode_program' => null];
                $draf[$kunci]['jumlah'] += $jumlah;
            });

        /** @var array<string, array{kode: string, uraian: string, jumlah: int, kode_program: string|null}> $impor */
        $impor = [];
        Kegiatan::query()
            ->where('sekolah_id', $kertasKerja->sekolah_id)
            ->where('tahun', $kertasKerja->tahun)
            ->where('periode_impor', $bulan)
            ->where('hilang_dari_anggaran', false)
            ->orderBy('id')
            ->get()
            ->each(function (Kegiatan $kegiatan) use (&$impor): void {
                $kunci = $this->kunci($kegiatan->kode, $kegiatan->uraian);
                $impor[$kunci] ??= ['kode' => $kegiatan->kode, 'uraian' => $kegiatan->uraian, 'jumlah' => 0, 'kode_program' => $kegiatan->kode_program];
                $impor[$kunci]['jumlah'] += $kegiatan->jumlah;
            });

        $hanyaDiDraf = [];
        $hanyaDiImpor = [];
        $berbeda = [];

        foreach ($draf as $kunci => $baris) {
            if (! isset($impor[$kunci])) {
                $hanyaDiDraf[] = ['kode_rekening' => $baris['kode'], 'uraian' => $baris['uraian'], 'jumlah' => $baris['jumlah']];
            } elseif ($impor[$kunci]['jumlah'] !== $baris['jumlah']) {
                $berbeda[] = ['kode_rekening' => $baris['kode'], 'uraian' => $baris['uraian'], 'draf' => $baris['jumlah'], 'impor' => $impor[$kunci]['jumlah']];
            }
        }

        foreach ($impor as $kunci => $baris) {
            if (! isset($draf[$kunci])) {
                $hanyaDiImpor[] = $baris;
            }
        }

        return new PerbandinganBulan(
            true,
            array_sum(array_column($draf, 'jumlah')),
            array_sum(array_column($impor, 'jumlah')),
            $hanyaDiDraf,
            $hanyaDiImpor,
            $berbeda,
        );
    }

    private function kunci(string $kode, string $uraian): string
    {
        $uraian = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $uraian)));

        return trim($kode).'|'.$uraian;
    }
}
