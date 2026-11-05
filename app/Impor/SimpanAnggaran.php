<?php

declare(strict_types=1);

namespace App\Impor;

use App\Enums\SistemAnggaran;
use App\Models\ImporAnggaran;
use App\Models\Kegiatan;
use App\Models\Sekolah;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan hasil baca PDF anggaran tanpa merusak data yang sudah dikerjakan pemilik.
 *
 * @phpstan-type Rencana array{kunci: string, bulan: int|null, tahap: int|null, kode: string, kode_program: string|null, uraian: string, nama: string, koefisien: string, harga: int, pajak: int, jumlah: int}
 */
class SimpanAnggaran
{
    private const SUMBER_ARKAS = 'BOSP Reguler';

    private const KOLOM_PEMBARUAN = ['uraian', 'nama', 'koefisien', 'harga', 'pajak', 'jumlah', 'tahap'];

    public function __construct(private readonly PenyaranJenis $penyaran) {}

    /**
     * Menghitung dampak impor tanpa menyimpan apa pun. Penolakan dilempar di sini juga.
     */
    public function bandingkan(AnggaranTerbaca $anggaran): RingkasanImpor
    {
        [$sekolah, $periode, $rencana] = $this->rencana($anggaran);
        [$baru, $berubah, $hilang] = $this->selisih($sekolah, $anggaran->tahun, $periode, $rencana);

        return $this->ringkasan($sekolah, $anggaran, $rencana, count($baru), count($berubah), count($hilang));
    }

    public function simpan(AnggaranTerbaca $anggaran, string $namaFile): RingkasanImpor
    {
        [$sekolah, $periode, $rencana] = $this->rencana($anggaran);

        return DB::transaction(function () use ($anggaran, $namaFile, $sekolah, $periode, $rencana): RingkasanImpor {
            [$baru, $berubah, $hilang] = $this->selisih($sekolah, $anggaran->tahun, $periode, $rencana);

            foreach ($baru as $baris) {
                Kegiatan::create($baris + [
                    'sekolah_id' => $sekolah->id,
                    'tahun' => $anggaran->tahun,
                    'periode_impor' => $periode,
                    'kunci_impor' => $baris['kunci'],
                    'jenis_kegiatan_id' => $this->penyaran->untuk($baris['uraian'])->id,
                    'jenis_dikonfirmasi' => false,
                    'hilang_dari_anggaran' => false,
                ]);
            }

            foreach ($berubah as [$kegiatan, $baris]) {
                $kegiatan->update(array_intersect_key($baris, array_flip(self::KOLOM_PEMBARUAN)) + ['hilang_dari_anggaran' => false]);
            }

            foreach ($hilang as $kegiatan) {
                if ($this->punyaBerkas($kegiatan)) {
                    $kegiatan->update(['hilang_dari_anggaran' => true]);
                } else {
                    $kegiatan->delete();
                }
            }

            ImporAnggaran::updateOrCreate(
                ['sekolah_id' => $sekolah->id, 'tahun' => $anggaran->tahun, 'periode' => $periode],
                ['total' => $anggaran->totalDokumen, 'nama_file' => $namaFile],
            );

            return $this->ringkasan($sekolah, $anggaran, $rencana, count($baru), count($berubah), count($hilang));
        });
    }

    /**
     * @param  list<Rencana>  $rencana
     */
    private function ringkasan(Sekolah $sekolah, AnggaranTerbaca $anggaran, array $rencana, int $baru, int $berubah, int $hilang): RingkasanImpor
    {
        return new RingkasanImpor($sekolah, $anggaran->bulan, $anggaran->tahun, count($rencana), $anggaran->totalDokumen, $baru, $berubah, $hilang);
    }

    /**
     * @return array{Sekolah, int, list<Rencana>}
     */
    private function rencana(AnggaranTerbaca $anggaran): array
    {
        $sekolah = Sekolah::where('identitas', $anggaran->identitas)->first();

        if ($sekolah === null) {
            throw new ImporDitolak("Sekolah dengan identitas {$anggaran->identitas} belum terdaftar di aplikasi.");
        }

        if ($sekolah->sistem !== $anggaran->sistem) {
            throw new ImporDitolak(sprintf(
                'PDF ini dari %s, tetapi %s memakai %s.',
                strtoupper($anggaran->sistem->value),
                $sekolah->nama,
                strtoupper($sekolah->sistem->value),
            ));
        }

        if ($anggaran->sistem === SistemAnggaran::Arkas) {
            if ($anggaran->sumberDana !== self::SUMBER_ARKAS) {
                throw new ImporDitolak("Sumber dana \"{$anggaran->sumberDana}\" tidak didukung. Hanya \"".self::SUMBER_ARKAS.'" yang bisa diimpor.');
            }

            if ($anggaran->bulan === null) {
                throw new ImporDitolak('Bulan pada PDF ARKAS tidak terbaca.');
            }
        }

        $rencana = [];
        $kemunculan = [];

        foreach ($anggaran->baris as $baris) {
            $dasar = sha1($baris->kode.'|'.$baris->kodeProgram.'|'.$baris->uraian);
            $kunci = $dasar.'#'.($kemunculan[$dasar] = ($kemunculan[$dasar] ?? 0) + 1);

            array_push($rencana, ...($anggaran->sistem === SistemAnggaran::Arkas
                ? [$this->barisArkas($baris, $kunci, (int) $anggaran->bulan)]
                : $this->barisErkam($baris, $kunci)));
        }

        return [$sekolah, $anggaran->sistem === SistemAnggaran::Arkas ? (int) $anggaran->bulan : 0, $rencana];
    }

    /**
     * @return Rencana
     */
    private function barisArkas(BarisAnggaran $baris, string $kunci, int $bulan): array
    {
        return $this->susun($baris, $kunci, explode('-', $baris->uraian, 2)[0], $bulan, null);
    }

    /**
     * @return list<Rencana>
     */
    private function barisErkam(BarisAnggaran $baris, string $kunci): array
    {
        $nama = explode(' | ', $baris->uraian, 2)[0];

        if (preg_match('/^(\d+) bulan$/', $baris->koefisien, $m) !== 1) {
            return [$this->susun($baris, $kunci, $nama, null, $baris->tahap)];
        }

        $jumlahBulan = (int) $m[1];

        if ($baris->tahap === null || $jumlahBulan < 1) {
            throw new ImporDitolak("Kegiatan rutin \"{$baris->uraian}\" tidak punya tahap atau jumlah bulan yang sah.");
        }

        if ($jumlahBulan * $baris->harga !== $baris->jumlah) {
            throw new ImporDitolak("Kegiatan rutin \"{$baris->uraian}\": {$jumlahBulan} bulan × harga tidak sama dengan jumlah.");
        }

        $awal = ($baris->tahap - 1) * 6 + 1;

        if ($awal + $jumlahBulan - 1 > $baris->tahap * 6) {
            throw new ImporDitolak("Kegiatan rutin \"{$baris->uraian}\" melewati bulan terakhir Tahap {$baris->tahap}.");
        }

        if ($baris->pajak % $jumlahBulan !== 0) {
            throw new ImporDitolak("Pajak kegiatan rutin \"{$baris->uraian}\" tidak bisa dibagi rata per bulan.");
        }

        $hasil = [];

        for ($bulan = $awal; $bulan < $awal + $jumlahBulan; $bulan++) {
            $hasil[] = [
                ...$this->susun($baris, "{$kunci}@{$bulan}", $nama, $bulan, $baris->tahap),
                'koefisien' => '1 bulan',
                'pajak' => intdiv($baris->pajak, $jumlahBulan),
                'jumlah' => $baris->harga,
            ];
        }

        return $hasil;
    }

    /**
     * @return Rencana
     */
    private function susun(BarisAnggaran $baris, string $kunci, string $nama, ?int $bulan, ?int $tahap): array
    {
        return [
            'kunci' => $kunci,
            'bulan' => $bulan,
            'tahap' => $tahap,
            'kode' => $baris->kode ?? '',
            'kode_program' => $baris->kodeProgram,
            'uraian' => $baris->uraian,
            'nama' => mb_substr(trim($nama), 0, 255),
            'koefisien' => $baris->koefisien,
            'harga' => $baris->harga,
            'pajak' => $baris->pajak,
            'jumlah' => $baris->jumlah,
        ];
    }

    /**
     * @param  list<Rencana>  $rencana
     * @return array{list<Rencana>, list<array{Kegiatan, Rencana}>, list<Kegiatan>}
     */
    private function selisih(Sekolah $sekolah, int $tahun, int $periode, array $rencana): array
    {
        /** @var array<string, Kegiatan> $ada */
        $ada = Kegiatan::where('sekolah_id', $sekolah->id)
            ->where('tahun', $tahun)
            ->where('periode_impor', $periode)
            ->withCount(['kuitansi', 'fotos'])
            ->get()
            ->keyBy('kunci_impor')
            ->all();

        $baru = [];
        $berubah = [];

        foreach ($rencana as $baris) {
            $kegiatan = $ada[$baris['kunci']] ?? null;

            if ($kegiatan === null) {
                $baru[] = $baris;

                continue;
            }

            unset($ada[$baris['kunci']]);

            if ($kegiatan->hilang_dari_anggaran || $this->berbeda($kegiatan, $baris)) {
                $berubah[] = [$kegiatan, $baris];
            }
        }

        return [$baru, $berubah, array_values($ada)];
    }

    /**
     * @param  Rencana  $baris
     */
    private function berbeda(Kegiatan $kegiatan, array $baris): bool
    {
        foreach (self::KOLOM_PEMBARUAN as $kolom) {
            if ($kegiatan->getAttribute($kolom) !== $baris[$kolom]) {
                return true;
            }
        }

        return false;
    }

    private function punyaBerkas(Kegiatan $kegiatan): bool
    {
        return $kegiatan->acara_id !== null
            || $kegiatan->status_manual !== null
            || $kegiatan->getAttribute('kuitansi_count') > 0
            || $kegiatan->getAttribute('fotos_count') > 0;
    }
}
