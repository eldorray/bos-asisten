<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

use App\Enums\JenisVersiRkas;
use App\Enums\SumberDanaRkas;
use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;
use App\Models\RkasPengaturan;
use Illuminate\Database\Eloquent\Collection;

/**
 * Menghitung ringkasan kertas kerja dengan bilangan bulat (spec §4.3, §7.3, §7.4).
 */
class HitungRingkasan
{
    public function __construct(private readonly PeriksaReferensiBaris $periksa) {}

    public function untuk(RkasKertasKerja $kertasKerja): RingkasanRkas
    {
        $baris = $kertasKerja->baris()->with(['refKegiatan', 'refRekening', 'refBarang'])->get();

        $pagu = $kertasKerja->pagu;
        $perBulan = array_fill_keys(range(1, 12), 0);
        $jenis = ['operasional' => 0, 'alat_mesin' => 0, 'aset' => 0];
        $rincian = ['honor' => 0, 'barang' => 0, 'jasa' => 0, 'pemeliharaan' => 0, 'perjalanan' => 0, 'peralatan_mesin' => 0, 'aset_lain' => 0];

        $konteks = $this->periksa->konteks($kertasKerja);
        $pengaturan = RkasPengaturan::untuk($kertasKerja->sekolah, $kertasKerja->tahun);
        $masalah = [];
        $peringatanBaris = [];
        $nilaiHonor = $nilaiBuku = $nilaiSarpras = 0;

        $asal = $this->asalBaris($kertasKerja, $baris);
        $batasBulan = $this->batasBulanLewat($kertasKerja);

        foreach ($baris as $b) {
            $b->setRelation('kertasKerja', $kertasKerja);
            foreach ($perBulan as $bulan => $_) {
                $perBulan[$bulan] += $b->jumlahBulan($bulan);
            }

            $jumlah = $b->jumlah();
            $rekening = $b->refRekening->kode;
            $kegiatan = $b->refKegiatan->kode;

            $this->tambahJenis($jenis, $rincian, $rekening, $jumlah);

            if (in_array($kegiatan, $pengaturan->kode_honor, true) && $rekening === $pengaturan->rekening_honor) {
                $nilaiHonor += $jumlah;
            }
            if (in_array($kegiatan, $pengaturan->kode_buku, true)) {
                $nilaiBuku += $jumlah;
            }
            if (in_array($kegiatan, $pengaturan->kode_sarpras, true)
                && (str_starts_with($rekening, '5.1.') || in_array($rekening, $pengaturan->rekening_sarpras_lain, true))) {
                $nilaiSarpras += $jumlah;
            }

            $pesan = $this->periksa->untuk($b, $konteks);
            if ($pesan !== []) {
                $masalah[$b->id] = $pesan;
            }

            if ($this->bulanLewatBerubah($b, $asal, $batasBulan)) {
                $peringatanBaris[$b->id] = PesanRkas::BULAN_LEWAT;
            }
        }

        $jumlahBermasalah = count($masalah);
        foreach ($peringatanBaris as $id => $pesanLewat) {
            $masalah[$id] = [...($masalah[$id] ?? []), $pesanLewat];
        }

        $dianggarkan = array_sum($perBulan);
        $tahap = [];
        $juknis = [];
        $peringatan = [];

        if ($kertasKerja->sumber_dana === SumberDanaRkas::BospReguler) {
            $alokasi1 = intdiv($pagu * $pengaturan->persen_tahap_1, 100);
            $nilai1 = array_sum(array_slice($perBulan, 0, 6));
            $nilai2 = $dianggarkan - $nilai1;
            foreach ([[1, $alokasi1, $nilai1], [2, $pagu - $alokasi1, $nilai2]] as [$no, $alokasi, $nilai]) {
                $tahap[] = ['tahap' => $no, 'alokasi' => $alokasi, 'dianggarkan' => $nilai, 'selisih' => $alokasi - $nilai];
                if ($nilai > $alokasi) {
                    $peringatan[] = sprintf(PesanRkas::TAHAP_LEBIH, $no, PesanRkas::rupiah($nilai - $alokasi));
                }
            }

            $batasHonor = $pengaturan->swasta ? $pengaturan->honor_maks_swasta : $pengaturan->honor_maks_negeri;
            $juknis = [
                // Status ikut ditampilkan: batas honor negeri dan swasta berbeda, dan salah status mudah terlewat.
                $this->juknis($pengaturan->swasta ? 'Honor (sekolah swasta)' : 'Honor (sekolah negeri)', $nilaiHonor, $batasHonor, $pagu, true, PesanRkas::HONOR),
                $this->juknis('Buku', $nilaiBuku, $pengaturan->buku_min, $pagu, false, PesanRkas::BUKU),
                $this->juknis('Sarpras', $nilaiSarpras, $pengaturan->sarpras_maks, $pagu, true, PesanRkas::SARPRAS),
            ];
            foreach ($juknis as $j) {
                if ($j['pesan'] !== null) {
                    $peringatan[] = $j['pesan'];
                }
            }
        }

        $dihapus = $this->jumlahBarisLewatDihapus($kertasKerja, $baris, $batasBulan);
        if ($dihapus > 0) {
            $peringatan[] = sprintf(PesanRkas::BARIS_LEWAT_DIHAPUS, $dihapus);
        }

        return new RingkasanRkas(
            pagu: $pagu,
            dianggarkan: $dianggarkan,
            sisa: $pagu - $dianggarkan,
            perBulan: $perBulan,
            tahap: $tahap,
            jenisBelanja: $jenis,
            rincianBelanja: $rincian,
            juknis: $juknis,
            peringatan: $peringatan,
            masalahPerBaris: $masalah,
            jumlahBarisBermasalah: $jumlahBermasalah,
        );
    }

    /**
     * @param  Collection<int, RkasBaris>  $baris
     * @return Collection<int, RkasBaris>
     */
    private function asalBaris(RkasKertasKerja $kertasKerja, Collection $baris): Collection
    {
        if ($kertasKerja->jenis_versi === JenisVersiRkas::Awal) {
            return new Collection;
        }

        return RkasBaris::query()->whereIn('id', $baris->pluck('asal_baris_id')->filter()->all())->get()->keyBy('id');
    }

    /**
     * Bulan pertama yang masih boleh berubah; bulan sebelumnya sudah lewat. Null bila bukan revisi.
     */
    private function batasBulanLewat(RkasKertasKerja $kertasKerja): ?int
    {
        $tanggal = $kertasKerja->tanggal_revisi;
        if ($kertasKerja->jenis_versi === JenisVersiRkas::Awal || $tanggal === null || $tanggal->year !== $kertasKerja->tahun) {
            return null;
        }

        return $tanggal->month;
    }

    /**
     * Baris induk yang sudah punya volume di bulan yang lewat tetapi tidak lagi ditunjuk baris mana pun di versi ini.
     *
     * @param  Collection<int, RkasBaris>  $baris
     */
    private function jumlahBarisLewatDihapus(RkasKertasKerja $kertasKerja, Collection $baris, ?int $batas): int
    {
        if ($batas === null || $kertasKerja->induk === null) {
            return 0;
        }

        $dirujuk = $baris->pluck('asal_baris_id')->filter()->all();

        return $kertasKerja->induk->baris()->get()
            ->reject(fn (RkasBaris $lama): bool => in_array($lama->id, $dirujuk, true))
            ->filter(fn (RkasBaris $lama): bool => $this->punyaVolumeLewat($lama, $batas))
            ->count();
    }

    private function punyaVolumeLewat(RkasBaris $baris, int $batas): bool
    {
        for ($bulan = 1; $bulan < $batas; $bulan++) {
            if ((int) ($baris->volume_bulan[$bulan] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, RkasBaris>  $asal
     */
    private function bulanLewatBerubah(RkasBaris $baris, Collection $asal, ?int $batas): bool
    {
        if ($batas === null) {
            return false;
        }

        $lama = $baris->asal_baris_id === null ? null : $asal->get($baris->asal_baris_id);
        if ($lama !== null && $lama->harga !== $baris->harga && $this->punyaVolumeLewat($lama, $batas)) {
            return true;
        }

        for ($bulan = 1; $bulan < $batas; $bulan++) {
            $sebelumnya = (int) ($lama?->volume_bulan[$bulan] ?? 0);
            if ((int) ($baris->volume_bulan[$bulan] ?? 0) !== $sebelumnya) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{operasional: int, alat_mesin: int, aset: int}  $jenis
     * @param  array{honor: int, barang: int, jasa: int, pemeliharaan: int, perjalanan: int, peralatan_mesin: int, aset_lain: int}  $rincian
     */
    private function tambahJenis(array &$jenis, array &$rincian, string $rekening, int $jumlah): void
    {
        if (str_starts_with($rekening, '5.1')) {
            $jenis['operasional'] += $jumlah;
        } elseif (str_starts_with($rekening, '5.2.02')) {
            $jenis['alat_mesin'] += $jumlah;
        } elseif (str_starts_with($rekening, '5.2')) {
            $jenis['aset'] += $jumlah;
        }

        $kunci = match (true) {
            $rekening === '5.1.02.02.01.0013' => 'honor',
            str_starts_with($rekening, '5.1.02.01') => 'barang',
            str_starts_with($rekening, '5.1.02.02') => 'jasa',
            str_starts_with($rekening, '5.1.02.03') => 'pemeliharaan',
            str_starts_with($rekening, '5.1.02.04') => 'perjalanan',
            str_starts_with($rekening, '5.2.02'), str_starts_with($rekening, '5.2.04') => 'peralatan_mesin',
            str_starts_with($rekening, '5.2.05') => 'aset_lain',
            default => null,
        };
        if ($kunci !== null) {
            $rincian[$kunci] += $jumlah;
        }
    }

    /**
     * @return array{nama: string, persen: float, batas: int, pesan: ?string}
     */
    private function juknis(string $nama, int $nilai, int $persenBatas, int $pagu, bool $maksimal, string $format): array
    {
        $dilanggar = $maksimal ? $nilai * 100 > $persenBatas * $pagu : $nilai * 100 < $persenBatas * $pagu;

        return [
            'nama' => $nama,
            'persen' => $pagu === 0 ? 0.0 : round($nilai / $pagu * 100, 2),
            'batas' => $persenBatas,
            'pesan' => $dilanggar ? sprintf($format, $persenBatas) : null,
        ];
    }
}
