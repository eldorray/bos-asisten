<?php

declare(strict_types=1);

namespace App\Pdf;

use App\Models\Kuitansi;
use App\Models\Sekolah;
use App\Support\Terbilang;
use FPDF;

/**
 * Kuitansi satu halaman penuh, mengikuti contoh "Kuitansi Lunas Bayar" milik pemilik:
 * bingkai, judul di tengah, isian berlabel, lalu tiga tanda tangan.
 */
class GambarKuitansiPenuh
{
    private const FONT = 'Helvetica';

    private const UKURAN = 11.0;

    private const TINGGI_BARIS = 6.4;

    private const KIRI = 21.0;

    private const NILAI_X = 67.0;

    private const KANAN = 125.0;

    public function __construct(
        private readonly FPDF $pdf,
        private readonly Sekolah $sekolah,
    ) {}

    public function gambar(?Kuitansi $kuitansi): void
    {
        $this->pdf->SetTextColor(0);
        $this->pdf->SetDrawColor(0);
        $this->pdf->SetLineWidth(0.3);
        $this->pdf->Rect(10.0, 10.0, 190.0, 277.0);

        if ($kuitansi === null) {
            $this->pdf->SetTextColor(90);
            $this->teks('KUITANSI BELUM DIBUAT', 105.0, 148.0, 'B', 16.0, 'C');

            return;
        }

        $sebutan = $this->sekolah->sebutan;

        $this->teks('KUITANSI/BUKTI PEMBAYARAN', 105.0, 17.0, 'B', 14.0, 'C');
        $this->teks('Tahun Anggaran', 109.0, 30.0);
        $this->teks(': '.$kuitansi->tahun, 150.0, 30.0);
        $this->teks('Nomor Bukti', 109.0, 36.7);
        $this->teks(': '.$kuitansi->nomor, 150.0, 36.7);

        $y = 57.5;

        foreach ([
            ['Sudah terima dari', "Kepala {$sebutan}", ''],
            [$sebutan, $this->sekolah->nama, ''],
            ['Desa/Kecamatan', $this->sekolah->desa_kecamatan, ''],
            ['Kabupaten', $this->sekolah->kabupaten, ''],
            ['Provinsi', $this->sekolah->provinsi, ''],
            ['Jumlah Uang', 'Rp '.number_format($kuitansi->jumlah, 0, ',', '.'), 'B'],
            ['Terbilang', Terbilang::rupiah($kuitansi->jumlah), ''],
            ['Untuk Pembayaran', $kuitansi->untuk_pembayaran, ''],
            ['Sumber Dana', $kuitansi->sumber_dana, ''],
        ] as [$label, $nilai, $gaya]) {
            $y = $this->baris($label, (string) $nilai, $y, $gaya);
        }

        $this->tandaTangan("Penerima Uang\nTanda Tangan", $kuitansi->penerima, self::KANAN, 132.0, 182.5);
        $this->teks('Lunas dibayar tanggal '.$kuitansi->tanggal_lunas->format('d-m-Y'), self::KANAN, 196.0);
        $this->tandaTangan("Kepala {$sebutan}\nTanda Tangan", $this->sekolah->nama_kepala, self::KIRI + 5.0, 201.0, 247.0);
        $this->tandaTangan("Bendahara {$sebutan}\nTanda Tangan", $this->sekolah->nama_bendahara, self::KANAN, 201.0, 247.0);
    }

    /**
     * Label di kiri, nilai di kanan yang boleh beberapa baris. Mengembalikan y baris berikutnya.
     */
    private function baris(string $label, string $nilai, float $y, string $gaya): float
    {
        $daftar = GambarKuitansi::barisTeks(': '.$nilai, 190.0 - (self::NILAI_X - 10.0) - 6.0, self::UKURAN, $gaya) ?: [': '];

        $this->teks($label, self::KIRI, $y);

        foreach ($daftar as $i => $isi) {
            $this->teks($isi, self::NILAI_X, $y + $i * self::TINGGI_BARIS, $gaya, self::UKURAN);
        }

        return $y + count($daftar) * self::TINGGI_BARIS + 0.5;
    }

    private function tandaTangan(string $jabatan, string $nama, float $x, float $yJabatan, float $yNama): void
    {
        foreach (explode("\n", $jabatan) as $i => $baris) {
            $this->teks($baris, $x, $yJabatan + $i * 4.7);
        }

        $isi = "({$nama})";
        $this->teks($isi, $x, $yNama);
        $this->pdf->SetFont(self::FONT, '', self::UKURAN);
        $lebar = $this->pdf->GetStringWidth(GambarKuitansi::teksPdf($isi));
        $this->pdf->SetLineWidth(0.25);
        $this->pdf->Line($x, $yNama + 0.9, $x + $lebar, $yNama + 0.9);
    }

    /**
     * @param  'L'|'C'  $rata
     */
    private function teks(string $teks, float $x, float $y, string $gaya = '', float $ukuran = self::UKURAN, string $rata = 'L'): void
    {
        $this->pdf->SetFont(self::FONT, $gaya, $ukuran);
        $isi = GambarKuitansi::teksPdf($teks);
        $geser = $rata === 'C' ? -$this->pdf->GetStringWidth($isi) / 2 : 0.0;
        $this->pdf->Text($x + $geser, $y, $isi);
    }
}
