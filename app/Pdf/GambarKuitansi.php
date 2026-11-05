<?php

declare(strict_types=1);

namespace App\Pdf;

use App\Enums\SistemAnggaran;
use App\Models\Kuitansi;
use App\Models\Sekolah;
use App\Support\Terbilang;
use FPDF;

/**
 * Menggambar satu kuitansi di blok setinggi sepertiga lembar. Teks ditulis lewat
 * Text() supaya pembagian baris di sini sama persis dengan yang dihitung ukuranFontPas().
 */
class GambarKuitansi
{
    private const FONT = 'Helvetica';

    private const PADDING = 8.0;

    private const PT_KE_MM = 25.4 / 72;

    private const SPASI_BARIS = 1.2;

    private const LANGKAH_FONT = 0.5;

    private const JARAK_KOLOM = 4.0;

    /** Kepala kuitansi: judul di atas garis, isi mulai di bawahnya. */
    private const TINGGI_KEPALA = 8.0;

    /** Ruang tanda tangan tiap format, diukur dari dasar isi. */
    private const TINGGI_TTD_ARKAS = 29.0;

    private const TINGGI_TTD_ERKAM = 30.0;

    private const TINGGI_NAMA_TTD = 8.0;

    /** Ukuran terkecil yang dipakai: nilai terpanjang yang sah selalu muat di ukuran ini. */
    private const FONT_MIN = 6.0;

    private const ABU = 90;

    private static ?FPDF $pengukur = null;

    public function __construct(
        private readonly FPDF $pdf,
        private readonly Sekolah $sekolah,
    ) {}

    /**
     * Ukuran font terbesar yang membuat teks muat. Kalau sampai $min pun tidak muat,
     * hasilnya $min dan teks dipotong saat digambar.
     */
    public static function ukuranFontPas(string $teks, float $lebar, float $tinggiMaks, float $maks = 9.0, float $min = 6.0, string $gaya = ''): float
    {
        for ($ukuran = $maks; $ukuran > $min; $ukuran -= self::LANGKAH_FONT) {
            if (count(self::barisTeks($teks, $lebar, $ukuran, $gaya)) * self::tinggiBaris($ukuran) <= $tinggiMaks) {
                return $ukuran;
            }
        }

        return $min;
    }

    /**
     * Pecah teks UTF-8 menjadi baris windows-1252 yang tidak melebihi $lebar.
     *
     * @param  bool  $rapat  Isi tiap baris sampai penuh tanpa menjaga batas kata, untuk teks yang hanya muat dengan cara ini.
     * @return list<string>
     */
    public static function barisTeks(string $teks, float $lebar, float $ukuran, string $gaya = '', bool $rapat = false): array
    {
        $pengukur = self::$pengukur ??= new FPDF('P', 'mm', 'A4');
        $pengukur->SetFont(self::FONT, $gaya, $ukuran);

        $daftarKata = preg_split('/\s+/', trim(self::teksPdf($teks))) ?: [];

        if ($rapat) {
            $daftarKata = [implode(' ', $daftarKata)];
        }

        $baris = [];
        $sekarang = '';

        foreach ($daftarKata as $kata) {
            $calon = $sekarang === '' ? $kata : $sekarang.' '.$kata;

            if ($pengukur->GetStringWidth($calon) <= $lebar) {
                $sekarang = $calon;

                continue;
            }

            if ($sekarang !== '') {
                $baris[] = $sekarang;
            }

            // Kata yang lebih panjang dari satu baris dipotong per karakter.
            $sekarang = '';

            foreach (str_split($kata) as $huruf) {
                if ($sekarang !== '' && $pengukur->GetStringWidth($sekarang.$huruf) > $lebar) {
                    $baris[] = $sekarang;
                    $sekarang = '';
                }

                $sekarang .= $huruf;
            }
        }

        if ($sekarang !== '') {
            $baris[] = $sekarang;
        }

        return $baris;
    }

    public static function tinggiBaris(float $ukuran): float
    {
        return $ukuran * self::PT_KE_MM * self::SPASI_BARIS;
    }

    /**
     * Font inti FPDF hanya mengenal windows-1252. Karakter di luar itu menjadi "?".
     */
    public static function teksPdf(string $teks): string
    {
        $bersih = mb_scrub($teks, 'UTF-8');
        $hasil = @iconv('UTF-8', 'windows-1252//TRANSLIT', $bersih);

        return $hasil !== false ? $hasil : (string) preg_replace('/[^\x20-\x7E]/u', '?', $bersih);
    }

    public function gambar(?Kuitansi $kuitansi, float $x, float $y, float $lebar, float $tinggi, bool $adaDiBawah): void
    {
        $this->pdf->SetTextColor(0);
        $this->pdf->SetDrawColor(0);

        $kiri = $x + self::PADDING;
        $atas = $y + self::PADDING;
        $lebarIsi = $lebar - 2 * self::PADDING;
        $tinggiIsi = $tinggi - 2 * self::PADDING;

        match (true) {
            $kuitansi === null => $this->belumDibuat($kiri, $atas, $lebarIsi, $tinggiIsi),
            $this->sekolah->sistem === SistemAnggaran::Erkam => $this->erkam($kuitansi, $kiri, $atas, $lebarIsi, $tinggiIsi),
            default => $this->arkas($kuitansi, $kiri, $atas, $lebarIsi, $tinggiIsi),
        };

        if ($adaDiBawah) {
            $this->garisPutus($kiri, $y + $tinggi, $kiri + $lebarIsi);
        }
    }

    private function belumDibuat(float $x, float $y, float $lebar, float $tinggi): void
    {
        $this->pdf->SetDrawColor(self::ABU);
        $this->pdf->SetLineWidth(0.3);
        $this->pdf->Rect($x, $y, $lebar, $tinggi);
        $this->pdf->SetTextColor(self::ABU);
        $this->tulis('KUITANSI BELUM DIBUAT', $x, $y + $tinggi / 2 - 3, $lebar, 6.0, 13.0, 13.0, 'B', 'C');
    }

    /**
     * Format B: kepala, dua kolom (uraian dan jumlah), tiga tanda tangan.
     */
    private function arkas(Kuitansi $kuitansi, float $x, float $y, float $lebar, float $tinggi): void
    {
        $lunas = $kuitansi->tanggal_lunas->format('d-m-Y');

        $this->kepala('KUITANSI', "No. {$kuitansi->nomor} \u{00B7} Lunas {$lunas}", $x, $y, $lebar);

        $bawahIsi = $y + $tinggi;
        $atasTtd = $bawahIsi - self::TINGGI_TTD_ARKAS;
        $yIsi = $y + self::TINGGI_KEPALA;
        $lebarKiri = 120.0;
        $lebarLabel = 13.0;
        $xKanan = $x + $lebarKiri + 6.0;
        $lebarKanan = $lebar - $lebarKiri - 6.0;

        $yKiri = $yIsi;
        $yKiri += $this->baris('Untuk', $kuitansi->untuk_pembayaran, $x, $yKiri, $lebarLabel, $lebarKiri - $lebarLabel, 28.0, 9.0);
        $yKiri += $this->baris('Dana', $kuitansi->sumber_dana, $x, $yKiri, $lebarLabel, $lebarKiri - $lebarLabel, 7.0, 8.5);

        $kode = $this->kode($kuitansi);

        if ($kode !== '') {
            $this->baris('Kode', $kode, $x, $yKiri, $lebarLabel, $lebarKiri - $lebarLabel, $atasTtd - $yKiri - 1.0, 8.5);
        }

        $tinggiJumlah = $this->tulis($this->rupiah($kuitansi->jumlah), $xKanan, $yIsi, $lebarKanan, 5.0, 13.0, 8.0, 'B');
        $this->tulis(Terbilang::rupiah($kuitansi->jumlah), $xKanan, $yIsi + $tinggiJumlah + 1.0, $lebarKanan, $atasTtd - $yIsi - $tinggiJumlah - 2.0, 9.0, 6.0, 'I');

        $this->tandaTangan($x, $atasTtd, $bawahIsi, $lebar, [
            ['Kepala '.$this->sekolah->sebutan, $this->sekolah->nama_kepala],
            ['Bendahara', $this->sekolah->nama_bendahara],
            ['Penerima', $kuitansi->penerima],
        ]);
    }

    /**
     * Isian mengikuti kuitansi ERKAM pemilik, diperkecil ke sepertiga lembar.
     */
    private function erkam(Kuitansi $kuitansi, float $x, float $y, float $lebar, float $tinggi): void
    {
        $sebutan = $this->sekolah->sebutan;

        $this->kepala('KUITANSI/BUKTI PEMBAYARAN', '', $x, $y, $lebar);

        $bawahIsi = $y + $tinggi;
        $atasTtdLega = $bawahIsi - self::TINGGI_TTD_ERKAM;
        $lebarLabel = 28.0;
        $lebarKolom = ($lebar - self::JARAK_KOLOM) / 2;
        $satuBaris = self::tinggiBaris(8.5);
        $duaBaris = self::tinggiBaris(self::FONT_MIN) * 2;
        $tigaBaris = self::tinggiBaris(self::FONT_MIN) * 3;

        $kolomKiri = [
            ['Tahun Anggaran', (string) $kuitansi->tahun, $satuBaris],
            ['Nomor Bukti', $kuitansi->nomor, $duaBaris],
            ['Sudah terima dari', "Kepala {$sebutan}", $duaBaris],
            [$sebutan, $this->sekolah->nama, $tigaBaris],
        ];
        $kolomKanan = [
            ['Desa/Kecamatan', $this->sekolah->desa_kecamatan, $tigaBaris],
            ['Kabupaten', $this->sekolah->kabupaten, $tigaBaris],
            ['Provinsi', $this->sekolah->provinsi, $tigaBaris],
        ];

        $yKiri = $yKanan = $y + self::TINGGI_KEPALA;

        foreach ($kolomKiri as [$label, $nilai, $tinggiMaks]) {
            $yKiri += $this->baris($label, $nilai, $x, $yKiri, $lebarLabel, $lebarKolom - $lebarLabel, $tinggiMaks, 8.5);
        }

        foreach ($kolomKanan as [$label, $nilai, $tinggiMaks]) {
            $yKanan += $this->baris($label, $nilai, $x + $lebarKolom + self::JARAK_KOLOM, $yKanan, $lebarLabel, $lebarKolom - $lebarLabel, $tinggiMaks, 8.5);
        }

        $yBaris = max($yKiri, $yKanan);
        $lebarNilai = $lebar - $lebarLabel;

        $yBaris += $this->baris('Jumlah Uang', $this->rupiah($kuitansi->jumlah), $x, $yBaris, $lebarLabel, $lebarNilai, 5.0, 11.0, 'B');
        $yBaris += $this->baris('Terbilang', Terbilang::rupiah($kuitansi->jumlah), $x, $yBaris, $lebarLabel, $lebarNilai, self::tinggiBaris(7.0) * 2, 8.5, 'I');

        // Untuk Pembayaran tidak boleh kehilangan baris: kalau sempit, ruang tanda tanganlah yang mengalah.
        $tinggiUntuk = max($tigaBaris, $atasTtdLega - $yBaris - $satuBaris - 1.0);
        $yBaris += $this->baris('Untuk Pembayaran', $kuitansi->untuk_pembayaran, $x, $yBaris, $lebarLabel, $lebarNilai, $tinggiUntuk, 8.5);
        $yBaris += $this->baris('Sumber Dana', $kuitansi->sumber_dana, $x, $yBaris, $lebarLabel, $lebarNilai, $satuBaris, 8.5);

        $atasTtd = max($atasTtdLega, $yBaris);

        $lunas = 'Lunas dibayar tanggal '.$kuitansi->tanggal_lunas->format('d-m-Y');
        $tinggiLunas = $this->tulis($lunas, $x + ($lebar + self::JARAK_KOLOM) / 3, $atasTtd, ($lebar - self::JARAK_KOLOM) * 2 / 3, self::tinggiBaris(8.0), 8.0, 6.0, '', 'C');

        $this->tandaTangan($x, $atasTtd + $tinggiLunas, $bawahIsi, $lebar, [
            ['Penerima Uang', $kuitansi->penerima],
            ["Kepala {$sebutan}", $this->sekolah->nama_kepala],
            ["Bendahara {$sebutan}", $this->sekolah->nama_bendahara],
        ]);
    }

    private function kepala(string $judul, string $keterangan, float $x, float $y, float $lebar): void
    {
        $this->pdf->SetFont(self::FONT, 'B', 11.0);
        $lebarJudul = $this->pdf->GetStringWidth($judul) + 4.0;
        $this->tulis($judul, $x, $y, $lebarJudul, 5.0, 11.0, 11.0, 'B');

        if ($keterangan !== '') {
            $this->tulis($keterangan, $x + $lebarJudul, $y + 0.5, $lebar - $lebarJudul, self::tinggiBaris(8.5), 8.5, 6.0, '', 'R');
        }

        $this->pdf->SetLineWidth(0.4);
        $this->pdf->Line($x, $y + 6.0, $x + $lebar, $y + 6.0);
    }

    /**
     * Label di kiri, nilai yang bisa beberapa baris di kanan. Mengembalikan tinggi yang terpakai.
     */
    private function baris(string $label, string $nilai, float $x, float $y, float $lebarLabel, float $lebarNilai, float $tinggiMaks, float $ukuranMaks, string $gaya = ''): float
    {
        $this->pdf->SetTextColor(self::ABU);
        $tinggiLabel = $this->tulis($label, $x, $y, $lebarLabel - 1.0, max(self::tinggiBaris(8.0), $tinggiMaks), 8.0, self::FONT_MIN);
        $this->pdf->SetTextColor(0);
        $tinggiNilai = $this->tulis($nilai, $x + $lebarLabel, $y, $lebarNilai, $tinggiMaks, $ukuranMaks, self::FONT_MIN, $gaya);

        return max($tinggiLabel, $tinggiNilai) + 0.6;
    }

    /**
     * @param  array{array{string, string}, array{string, string}, array{string, string}}  $penanda  Jabatan dan nama.
     */
    private function tandaTangan(float $x, float $atas, float $bawah, float $lebar, array $penanda): void
    {
        $lebarKolom = ($lebar - 2 * self::JARAK_KOLOM) / 3;

        foreach ($penanda as $i => [$jabatan, $nama]) {
            $xKolom = $x + $i * ($lebarKolom + self::JARAK_KOLOM);
            $tata = $this->tataTeks($nama, $lebarKolom, self::TINGGI_NAMA_TTD, 8.5, self::FONT_MIN, 'B');
            $tinggiNama = count($tata['baris']) * self::tinggiBaris($tata['ukuran']);

            $this->tulis($jabatan, $xKolom, $atas, $lebarKolom, self::tinggiBaris(8.5), 8.5, self::FONT_MIN, '', 'C');
            $this->cetak($tata, $xKolom, $bawah - $tinggiNama, $lebarKolom, 'B', 'C');
        }
    }

    /**
     * Tulis teks dalam kotak, tidak pernah melewati $tinggiMaks. Mengembalikan tinggi yang terpakai.
     */
    private function tulis(string $teks, float $x, float $y, float $lebar, float $tinggiMaks, float $maks, float $min, string $gaya = '', string $rata = 'L'): float
    {
        return $this->cetak($this->tataTeks($teks, $lebar, $tinggiMaks, $maks, $min, $gaya), $x, $y, $lebar, $gaya, $rata);
    }

    /**
     * Pilih ukuran dan baris. Teks yang tidak muat kalau menjaga batas kata diisi rapat. Memotong
     * dengan "..." hanya pertahanan terakhir untuk nilai yang tersimpan sebelum ada batas validasi.
     *
     * @return array{ukuran: float, baris: list<string>}
     */
    private function tataTeks(string $teks, float $lebar, float $tinggiMaks, float $maks, float $min, string $gaya): array
    {
        $ukuran = self::ukuranFontPas($teks, $lebar, $tinggiMaks, $maks, $min, $gaya);
        $maksBaris = max(1, (int) floor($tinggiMaks / self::tinggiBaris($ukuran) + 1e-9));
        $baris = self::barisTeks($teks, $lebar, $ukuran, $gaya);

        if (count($baris) > $maksBaris) {
            $baris = self::barisTeks($teks, $lebar, $ukuran, $gaya, rapat: true);
        }

        if (count($baris) > $maksBaris) {
            $this->pdf->SetFont(self::FONT, $gaya, $ukuran);
            $baris = [...array_slice($baris, 0, $maksBaris - 1), $this->potong($baris[$maksBaris - 1], $lebar)];
        }

        return ['ukuran' => $ukuran, 'baris' => $baris];
    }

    /**
     * @param  array{ukuran: float, baris: list<string>}  $tata
     */
    private function cetak(array $tata, float $x, float $y, float $lebar, string $gaya, string $rata): float
    {
        $tinggiBaris = self::tinggiBaris($tata['ukuran']);

        $this->pdf->SetFont(self::FONT, $gaya, $tata['ukuran']);

        foreach ($tata['baris'] as $i => $isi) {
            $geser = match ($rata) {
                'R' => $lebar - $this->pdf->GetStringWidth($isi),
                'C' => ($lebar - $this->pdf->GetStringWidth($isi)) / 2,
                default => 0.0,
            };

            // Garis dasar teks kira-kira tiga perempat tinggi baris.
            $this->pdf->Text($x + $geser, $y + $i * $tinggiBaris + $tinggiBaris * 0.75, $isi);
        }

        return count($tata['baris']) * $tinggiBaris;
    }

    private function potong(string $baris, float $lebar): string
    {
        while ($baris !== '' && $this->pdf->GetStringWidth($baris.'...') > $lebar) {
            $baris = substr($baris, 0, -1);
        }

        return $baris.'...';
    }

    private function garisPutus(float $x1, float $y, float $x2): void
    {
        $this->pdf->SetDrawColor(153);
        $this->pdf->SetLineWidth(0.2);

        for ($x = $x1; $x < $x2; $x += 3.0) {
            $this->pdf->Line($x, $y, min($x + 1.5, $x2), $y);
        }
    }

    private function kode(Kuitansi $kuitansi): string
    {
        $kegiatan = $kuitansi->kegiatans->first();

        return $kegiatan === null ? '' : implode(" \u{00B7} ", array_filter([$kegiatan->kode, $kegiatan->kode_program]));
    }

    private function rupiah(int $jumlah): string
    {
        return 'Rp '.number_format($jumlah, 0, ',', '.');
    }
}
