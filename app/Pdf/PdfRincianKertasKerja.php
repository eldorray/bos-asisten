<?php

declare(strict_types=1);

namespace App\Pdf;

use App\Actions\Rkas\PesanRkas;
use App\Actions\Rkas\PropsBarisRkas;
use App\Actions\Rkas\RingkasanRkas;
use App\Models\RkasKertasKerja;
use FPDF;

/**
 * Cetak rincian kertas kerja (A4 lanskap) sebagai panduan mengetik ke ARKAS.
 * Urutan dan pengelompokan baris diambil dari PropsBarisRkas agar sama dengan layar.
 */
class PdfRincianKertasKerja
{
    private const MARGIN = 8.0;

    private const UKURAN = 6.5;

    private const TINGGI = 3.4;

    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private const WATERMARK = 'DRAF — bukan dokumen resmi';

    /** @var array<string, float> */
    private const LEBAR = ['rekening' => 25.0, 'uraian' => 72.0, 'satuan' => 14.0, 'harga' => 20.0, 'bulan' => 9.5, 'volume' => 12.0, 'jumlah' => 24.0];

    private function __construct(private readonly FPDF $pdf, private readonly RkasKertasKerja $kk) {}

    public static function buat(RkasKertasKerja $kertasKerja, RingkasanRkas $ringkasan): string
    {
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(self::MARGIN, self::MARGIN);

        $self = new self($pdf, $kertasKerja);
        $baris = (new PropsBarisRkas)->baris($kertasKerja);

        $self->halamanBaru(true);

        $program = $komponen = null;
        $subtotal = 0;
        $kodeKegiatan = null;

        foreach ($baris as $b) {
            /** @var array{kode: string, uraian: string, induk: array{kode: string, uraian: string}|null, program: array{kode: string, uraian: string}|null} $k */
            $k = $b['kegiatan'];

            if ($k['kode'] !== $kodeKegiatan) {
                if ($kodeKegiatan !== null) {
                    $self->subtotal($subtotal);
                }
                $subtotal = 0;
                $kodeKegiatan = $k['kode'];

                if (($k['program']['kode'] ?? null) !== $program) {
                    $program = $k['program']['kode'] ?? null;
                    $self->judul('Program', $k['program'], 7.0);
                }
                if (($k['induk']['kode'] ?? null) !== $komponen) {
                    $komponen = $k['induk']['kode'] ?? null;
                    $self->judul('Komponen', $k['induk'], 7.0);
                }
                $self->judul('Kegiatan', ['kode' => $k['kode'], 'uraian' => $k['uraian']], 7.0);
            }

            /** @var array{kode: string} $rek */
            $rek = $b['rekening'];
            /** @var array<int|string, int> $vb */
            $vb = $b['volume_bulan'];
            $self->barisData($rek['kode'], (string) $b['uraian'], (string) $b['satuan'], (int) $b['harga'], $vb, (int) $b['volume'], (int) $b['jumlah']);
            $subtotal += (int) $b['jumlah'];
        }

        if ($kodeKegiatan !== null) {
            $self->subtotal($subtotal);
        }

        $self->penutup($ringkasan);

        return $pdf->Output('S');
    }

    private function halamanBaru(bool $pertama): void
    {
        $this->pdf->AddPage();

        $this->pdf->SetFont('Helvetica', 'B', 40.0);
        $this->pdf->SetTextColor(228);
        $teks = GambarKuitansi::teksPdf(self::WATERMARK);
        $this->pdf->Text((297.0 - $this->pdf->GetStringWidth($teks)) / 2, 112.0, $teks);

        $this->pdf->SetTextColor(0);
        $this->pdf->SetXY(self::MARGIN, self::MARGIN);

        if ($pertama) {
            $this->pdf->SetFont('Helvetica', 'B', 12.0);
            $this->sel(281.0, 6.0, 'Rincian '.$this->kk->label(), 0, 1);
            $this->pdf->SetFont('Helvetica', '', 8.0);
            $info = [
                ['Sekolah', $this->kk->sekolah->nama.' (NPSN '.$this->kk->sekolah->identitas.')', 'Sumber dana', $this->namaSumber()],
                ['Tahun', (string) $this->kk->tahun, 'Versi', $this->kk->labelVersi()],
                ['Status', $this->kk->status->label(), 'Pagu', PesanRkas::rupiah($this->kk->pagu)],
            ];
            foreach ($info as [$a, $b, $c, $d]) {
                $this->sel(22.0, 4.2, $a, 0, 0);
                $this->sel(110.0, 4.2, ': '.$b, 0, 0);
                $this->sel(24.0, 4.2, $c, 0, 0);
                $this->sel(100.0, 4.2, ': '.$d, 0, 1);
            }
            $this->pdf->Ln(2.0);
        } else {
            $this->pdf->SetFont('Helvetica', 'B', 8.0);
            $this->sel(281.0, 5.0, 'Rincian '.$this->kk->label().' - '.$this->kk->sekolah->nama.' '.$this->kk->tahun.' (lanjutan)', 0, 1);
            $this->pdf->Ln(1.0);
        }

        $this->kepalaTabel();
    }

    private function kepalaTabel(): void
    {
        $this->pdf->SetFont('Helvetica', 'B', self::UKURAN);
        $this->pdf->SetFillColor(225);
        $this->pdf->SetDrawColor(120);
        $this->pdf->SetLineWidth(0.15);
        $l = self::LEBAR;
        foreach ([['Rekening', $l['rekening']], ['Uraian', $l['uraian']], ['Satuan', $l['satuan']], ['Harga', $l['harga']]] as [$t, $w]) {
            $this->sel((float) $w, 5.0, $t, 1, 0, 'C', true);
        }
        foreach (self::BULAN as $bulan) {
            $this->sel($l['bulan'], 5.0, $bulan, 1, 0, 'C', true);
        }
        $this->sel($l['volume'], 5.0, 'Volume', 1, 0, 'C', true);
        $this->sel($l['jumlah'], 5.0, 'Jumlah', 1, 1, 'C', true);
    }

    /**
     * @param  array{kode: string, uraian: string}|null  $isi
     */
    private function judul(string $jenis, ?array $isi, float $tinggiMin): void
    {
        if ($isi === null) {
            return;
        }

        $teks = $jenis.' '.$isi['kode'].' '.$isi['uraian'];
        $garis = $this->bungkus($teks, 281.0 - 2.0, 'B');
        $tinggi = max(count($garis) * self::TINGGI + 1.0, 4.5);
        $this->pastikanMuat($tinggi + self::TINGGI * 2);

        $this->pdf->SetFont('Helvetica', 'B', self::UKURAN + 0.5);
        $this->pdf->SetFillColor($jenis === 'Kegiatan' ? 240 : 228);
        $x = $this->pdf->GetX();
        $y = $this->pdf->GetY();
        $this->pdf->Rect($x, $y, 281.0, $tinggi, 'DF');
        foreach ($garis as $i => $g) {
            $this->pdf->SetXY($x + 1.0, $y + 0.5 + $i * self::TINGGI);
            $this->pdf->Cell(279.0, self::TINGGI, $g, 0, 0, 'L');
        }
        $this->pdf->SetXY($x, $y + $tinggi);
    }

    /**
     * @param  array<int|string, int>  $volumeBulan
     */
    private function barisData(string $rekening, string $uraian, string $satuan, int $harga, array $volumeBulan, int $volume, int $jumlah): void
    {
        $l = self::LEBAR;
        $garis = $this->bungkus($uraian, $l['uraian'] - 2.0, '');
        $tinggi = max(count($garis), 1) * self::TINGGI + 1.0;
        $this->pastikanMuat($tinggi);

        $this->pdf->SetFont('Helvetica', '', self::UKURAN);
        $x = $this->pdf->GetX();
        $y = $this->pdf->GetY();

        $kolom = [
            [$l['rekening'], [$rekening], 'L'],
            [$l['uraian'], $garis, 'L'],
            [$l['satuan'], $this->bungkus($satuan, $l['satuan'] - 2.0, ''), 'L'],
            [$l['harga'], [number_format($harga, 0, ',', '.')], 'R'],
        ];
        foreach (self::BULAN as $i => $_) {
            $v = (int) ($volumeBulan[$i + 1] ?? 0);
            $kolom[] = [$l['bulan'], [$v > 0 ? (string) $v : ''], 'C'];
        }
        $kolom[] = [$l['volume'], [(string) $volume], 'C'];
        $kolom[] = [$l['jumlah'], [number_format($jumlah, 0, ',', '.')], 'R'];

        $this->pdf->SetDrawColor(160);
        $this->pdf->SetLineWidth(0.1);
        foreach ($kolom as [$w, $baris, $rata]) {
            $this->pdf->Rect($x, $y, (float) $w, $tinggi);
            foreach ($baris as $i => $teks) {
                $this->pdf->SetXY($x + 1.0, $y + 0.5 + $i * self::TINGGI);
                $this->pdf->Cell((float) $w - 2.0, self::TINGGI, $teks, 0, 0, $rata);
            }
            $x += (float) $w;
        }
        $this->pdf->SetXY(self::MARGIN, $y + $tinggi);
    }

    private function subtotal(int $jumlah): void
    {
        $this->pastikanMuat(5.0);
        $this->pdf->SetFont('Helvetica', 'B', self::UKURAN + 0.5);
        $this->pdf->SetFillColor(245);
        $this->pdf->SetDrawColor(160);
        $this->sel(257.0, 4.5, 'Subtotal kegiatan', 1, 0, 'R', true);
        $this->sel(24.0, 4.5, PesanRkas::rupiah($jumlah), 1, 1, 'R', true);
    }

    private function penutup(RingkasanRkas $r): void
    {
        $baris = [['Total dianggarkan', $r->dianggarkan]];
        foreach ($r->tahap as $t) {
            $baris[] = ['Tahap '.$t['tahap'].' (alokasi '.PesanRkas::rupiah($t['alokasi']).')', $t['dianggarkan']];
        }
        $baris[] = ['Pagu', $r->pagu];
        $baris[] = ['Sisa pagu', $r->sisa];

        $this->pastikanMuat(count($baris) * 5.0 + 4.0);
        $this->pdf->Ln(3.0);
        $this->pdf->SetFont('Helvetica', 'B', 8.0);
        $this->pdf->SetFillColor(225);
        $this->pdf->SetDrawColor(120);
        foreach ($baris as [$nama, $nilai]) {
            $this->sel(90.0, 5.0, $nama, 1, 0, 'L', true);
            $this->sel(40.0, 5.0, PesanRkas::rupiah($nilai), 1, 1, 'R', false);
        }
    }

    private function pastikanMuat(float $tinggi): void
    {
        // Sisakan ruang bawah satu margin.
        if ($this->pdf->GetY() + $tinggi > 210.0 - self::MARGIN) {
            $this->halamanBaru(false);
        }
    }

    private function sel(float $w, float $h, string $teks, int $garis, int $ln, string $rata = 'L', bool $isi = false): void
    {
        $this->pdf->Cell($w, $h, GambarKuitansi::teksPdf($teks), $garis, $ln, $rata, $isi);
    }

    private function namaSumber(): string
    {
        return $this->kk->sumber_dana->value === 'lainnya' ? 'Lainnya' : 'BOSP Reguler';
    }

    /**
     * Pecah teks per kata agar muat di $lebar (mm); kata yang terlalu panjang dipotong per huruf.
     *
     * @return list<string> baris sudah berupa windows-1252
     */
    private function bungkus(string $teks, float $lebar, string $gaya): array
    {
        $this->pdf->SetFont('Helvetica', $gaya, $gaya === 'B' ? self::UKURAN + 0.5 : self::UKURAN);
        $hasil = [];
        $baris = '';

        foreach (preg_split('/\s+/u', trim($teks)) ?: [] as $kata) {
            $kata = GambarKuitansi::teksPdf($kata);
            while ($this->pdf->GetStringWidth($kata) > $lebar && strlen($kata) > 1) {
                $n = strlen($kata);
                while ($n > 1 && $this->pdf->GetStringWidth(substr($kata, 0, $n)) > $lebar) {
                    $n--;
                }
                if ($baris !== '') {
                    $hasil[] = $baris;
                    $baris = '';
                }
                $hasil[] = substr($kata, 0, $n);
                $kata = substr($kata, $n);
            }
            $coba = $baris === '' ? $kata : $baris.' '.$kata;
            if ($baris !== '' && $this->pdf->GetStringWidth($coba) > $lebar) {
                $hasil[] = $baris;
                $baris = $kata;
            } else {
                $baris = $coba;
            }
        }
        if ($baris !== '') {
            $hasil[] = $baris;
        }

        return $hasil;
    }
}
