<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SistemAnggaran;
use App\Models\Kegiatan;
use App\Models\Kuitansi;

class NilaiAwalKuitansi
{
    public const NAMA_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    private const BATAS = '(?<![\p{L}\p{N}])';

    private const BATAS_AKHIR = '(?![\p{L}\p{N}])';

    /**
     * @param  list<Kegiatan>  $kegiatanRapel  Baris lain yang ikut dicakup, di luar $kegiatan sendiri.
     * @return array{untuk_pembayaran: string, penerima: ?string, jumlah: int, nomor: string, sumber_dana: string}
     */
    public static function untuk(Kegiatan $kegiatan, array $kegiatanRapel = []): array
    {
        $kegiatan->loadMissing('sekolah');
        $sekolah = $kegiatan->sekolah;
        $dicakup = self::dicakup($kegiatan, $kegiatanRapel);
        $label = self::labelBulan($dicakup);
        $terakhir = self::bulanTerakhir($dicakup);
        $sebelumnya = self::kuitansiSebelumnya($kegiatan);

        $untuk = $sebelumnya !== null
            ? self::gantiBulanDanTahun($sebelumnya->untuk_pembayaran, $label, $sebelumnya->tahun, $kegiatan->tahun)
            : trim($kegiatan->nama.' '.$label.' '.$kegiatan->tahun);

        return [
            'untuk_pembayaran' => mb_substr($untuk, 0, 200),
            'penerima' => $sebelumnya?->penerima,
            'jumlah' => (int) array_sum(array_map(fn (Kegiatan $k): int => $k->jumlah, $dicakup)),
            'nomor' => self::nomorBerikut($kegiatan, $terakhir),
            'sumber_dana' => self::sumberDana($kegiatan),
        ];
    }

    public static function sumberDana(Kegiatan $kegiatan): string
    {
        $kegiatan->loadMissing('sekolah');

        return $kegiatan->sekolah->sistem === SistemAnggaran::Erkam
            ? 'Dana BOS Tahap '.(int) $kegiatan->tahap.' '.$kegiatan->tahun
            : 'BOSP Reguler '.$kegiatan->tahun;
    }

    /**
     * @param  list<Kegiatan>  $kegiatanRapel
     * @return list<Kegiatan>
     */
    private static function dicakup(Kegiatan $kegiatan, array $kegiatanRapel): array
    {
        $semua = [$kegiatan->id => $kegiatan];

        foreach ($kegiatanRapel as $lain) {
            $semua[$lain->id] = $lain;
        }

        $daftar = array_values($semua);
        usort($daftar, fn (Kegiatan $a, Kegiatan $b): int => ($a->bulanKerja() ?? 0) <=> ($b->bulanKerja() ?? 0));

        return $daftar;
    }

    /**
     * @param  list<Kegiatan>  $dicakup
     */
    private static function bulanTerakhir(array $dicakup): int
    {
        return max([0, ...array_map(fn (Kegiatan $k): int => $k->bulanKerja() ?? 0, $dicakup)]);
    }

    /**
     * Bulan berurutan ditulis rentang ("Januari-Maret"); bulan yang melompat ditulis satu per satu
     * ("Januari, Maret, Mei") supaya tidak menyebut periode yang tidak dibayar.
     *
     * @param  list<Kegiatan>  $dicakup
     */
    private static function labelBulan(array $dicakup): string
    {
        $bulan = array_values(array_unique(array_filter(
            array_map(fn (Kegiatan $k): int => $k->bulanKerja() ?? 0, $dicakup),
            fn (int $b): bool => $b > 0,
        )));
        sort($bulan);

        if ($bulan === []) {
            return '';
        }

        $nama = fn (int $b): string => self::NAMA_BULAN[$b] ?? '';
        $awal = $bulan[0];
        $akhir = $bulan[count($bulan) - 1];

        if (count($bulan) === 1) {
            return $nama($awal);
        }

        if ($akhir - $awal + 1 === count($bulan)) {
            return $nama($awal).'-'.$nama($akhir);
        }

        return implode(', ', array_map($nama, $bulan));
    }

    private static function kuitansiSebelumnya(Kegiatan $kegiatan): ?Kuitansi
    {
        return Kuitansi::query()
            ->where('sekolah_id', $kegiatan->sekolah_id)
            ->whereHas('kegiatans', function ($q) use ($kegiatan): void {
                $q->where('kode', $kegiatan->kode)->where('uraian', $kegiatan->uraian);

                if ($kegiatan->kode_program === null) {
                    $q->whereNull('kode_program');
                } else {
                    $q->where('kode_program', $kegiatan->kode_program);
                }
            })
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Mengganti bulan dan tahun hanya pada bagian sebelum "a/n" pertama; nama penerima tidak disentuh.
     */
    private static function gantiBulanDanTahun(string $teks, string $label, int $tahunLama, int $tahunBaru): string
    {
        $kepala = $teks;
        $ekor = '';

        if (preg_match('/'.self::BATAS.'a\/n'.self::BATAS_AKHIR.'/iu', $teks, $m, PREG_OFFSET_CAPTURE) === 1) {
            $kepala = substr($teks, 0, $m[0][1]);
            $ekor = substr($teks, $m[0][1]);
        }

        $bulan = self::BATAS.'(?:'.implode('|', self::NAMA_BULAN).')'.self::BATAS_AKHIR;
        $sambung = '\s*(?:-|–|—|s\/d|s\.d\.?|sampai|hingga|dan|,)\s*';

        $kepala = (string) preg_replace_callback(
            '/'.$bulan.'(?:'.$sambung.$bulan.')*/iu',
            fn (): string => $label,
            $kepala,
        );

        if ($tahunLama !== $tahunBaru) {
            $kepala = (string) preg_replace(
                '/'.self::BATAS.$tahunLama.self::BATAS_AKHIR.'/u',
                (string) $tahunBaru,
                $kepala,
            );
        }

        return $kepala.$ekor;
    }

    /**
     * Berikutnya = maks(nomor urut setahun, nomor awal - 1) + 1, naik terus sampai nomornya belum dipakai.
     */
    private static function nomorBerikut(Kegiatan $kegiatan, int $bulan): string
    {
        $sekolah = $kegiatan->sekolah;
        $dasar = Kuitansi::query()->where('sekolah_id', $sekolah->id)->where('tahun', $kegiatan->tahun);
        $urut = max((int) (clone $dasar)->max('nomor_urut'), ((int) ($sekolah->nomor_awal[$kegiatan->tahun] ?? 1)) - 1) + 1;

        while ((clone $dasar)->where('nomor', $nomor = self::formatNomor($kegiatan, $urut, $bulan))->exists()) {
            $urut++;
        }

        return $nomor;
    }

    private static function formatNomor(Kegiatan $kegiatan, int $urut, int $bulan): string
    {
        $sekolah = $kegiatan->sekolah;

        return $sekolah->sistem === SistemAnggaran::Erkam
            ? sprintf('%03d/T%d/%s/%d', $urut, (int) $kegiatan->tahap, $sekolah->kode_bukti, $kegiatan->tahun)
            : sprintf('%03d/%02d/%s/%d', $urut, $bulan, $sekolah->kode_bukti, $kegiatan->tahun);
    }
}
