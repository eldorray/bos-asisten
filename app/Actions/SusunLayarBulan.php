<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SistemAnggaran;
use App\Models\Kegiatan;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class SusunLayarBulan
{
    /**
     * @return array{bulan: string, ringkasan: array{selesai: int, kurang: int, belum: int}, sekolah: list<array<string, mixed>>, menunggakPerBulan: array<int, int>, pdfBelumPerBulan: array<int, bool>}
     */
    public function susun(int $tahun, int $bulan): array
    {
        $kegiatans = Kegiatan::query()
            ->where('tahun', $tahun)
            ->with(HitungStatusKegiatan::RELASI)
            ->orderBy('id')
            ->get();

        /** @var Collection<int, array{kegiatan: Kegiatan, status: StatusKegiatan}> $baris */
        $baris = $kegiatans->map(fn (Kegiatan $k): array => ['kegiatan' => $k, 'status' => HitungStatusKegiatan::untuk($k)]);

        $bulanIni = $baris->filter(fn (array $b): bool => $b['kegiatan']->bulanKerja() === $bulan);

        $sekolahs = Sekolah::query()
            ->with(['imporAnggarans' => fn ($q) => $q->where('tahun', $tahun)])
            ->orderBy('id')
            ->get();

        return [
            'bulan' => sprintf('%04d-%02d', $tahun, $bulan),
            'ringkasan' => $this->ringkasan($bulanIni),
            'sekolah' => array_values($sekolahs
                ->map(fn (Sekolah $s): array => $this->sekolah($s, $tahun, $bulan, $baris))
                ->all()),
            'menunggakPerBulan' => $this->menunggakPerBulan($baris),
            'pdfBelumPerBulan' => $this->pdfBelumPerBulan($sekolahs, $tahun),
        ];
    }

    /**
     * Angka lencana di menu Bulanan: kegiatan satu bulan yang masih harus dikerjakan.
     */
    public function jumlahMenunggak(int $tahun, int $bulan): int
    {
        return Kegiatan::query()
            ->where('tahun', $tahun)
            ->padaBulan($bulan)
            ->with(HitungStatusKegiatan::RELASI)
            ->get()
            ->filter(fn (Kegiatan $k): bool => HitungStatusKegiatan::untuk($k)->menunggak())
            ->count();
    }

    /**
     * @param  Collection<int, array{kegiatan: Kegiatan, status: StatusKegiatan}>  $baris
     * @return array<string, mixed>
     */
    private function sekolah(Sekolah $sekolah, int $tahun, int $bulan, Collection $baris): array
    {
        $milik = $baris->filter(fn (array $b): bool => $b['kegiatan']->sekolah_id === $sekolah->id);
        $bulanIni = $milik->filter(fn (array $b): bool => $b['kegiatan']->bulanKerja() === $bulan);

        return [
            'id' => $sekolah->id,
            'nama' => $sekolah->nama,
            'sistem' => $sekolah->sistem->value,
            'total' => (int) $bulanIni->sum(fn (array $b): int => $b['kegiatan']->jumlah),
            'peringatan' => $this->peringatan($sekolah, $bulan),
            'kegiatan' => $bulanIni->map(fn (array $b): array => $this->baris($b['kegiatan'], $b['status']))->values()->all(),
            'saranAcara' => array_map(fn (array $kelompok): array => [
                'ids' => array_map(fn (Kegiatan $k): int => $k->id, $kelompok),
                'nama' => $kelompok[0]->nama,
                'baris' => array_map(fn (Kegiatan $k): array => ['id' => $k->id, 'nama' => $k->nama, 'jumlah' => $k->jumlah], $kelompok),
            ], SaranAcara::untukBulan($sekolah, $tahun, $bulan)),
            'ditunda' => $milik
                ->filter(fn (array $b): bool => $b['kegiatan']->bulan === $bulan && $b['kegiatan']->bulan_pelaksanaan !== null)
                ->map(fn (array $b): array => $this->baris($b['kegiatan'], $b['status']))
                ->values()
                ->all(),
            'belumBerbulan' => $milik
                ->filter(fn (array $b): bool => $b['kegiatan']->bulan === null && $b['kegiatan']->status_manual === null)
                ->groupBy(fn (array $b): int => (int) $b['kegiatan']->tahap)
                ->sortKeys()
                ->map(fn (Collection $kelompok, int $tahap): array => [
                    'tahap' => $tahap,
                    'kegiatan' => $kelompok->map(fn (array $b): array => $this->baris($b['kegiatan'], $b['status']))->values()->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function peringatan(Sekolah $sekolah, int $bulan): ?string
    {
        $periode = $sekolah->sistem === SistemAnggaran::Arkas ? $bulan : 0;

        if ($sekolah->imporAnggarans->contains('periode', $periode)) {
            return null;
        }

        return $sekolah->sistem === SistemAnggaran::Arkas
            ? 'PDF ARKAS bulan ini belum diunggah'
            : 'RKAM ERKAM tahun ini belum diunggah';
    }

    /**
     * @return array<string, mixed>
     */
    private function baris(Kegiatan $kegiatan, StatusKegiatan $status): array
    {
        return [
            'id' => $kegiatan->id,
            'nama' => $kegiatan->nama,
            'koefisien' => $kegiatan->koefisien,
            'jumlah' => $kegiatan->jumlah,
            'bulan' => $kegiatan->bulan,
            'bulanPelaksanaan' => $kegiatan->bulan_pelaksanaan,
            'ditundaDari' => $kegiatan->bulan_pelaksanaan === null ? null : $kegiatan->bulan,
            'perluDiperiksa' => $status->perluDiperiksa,
            'tahap' => $kegiatan->tahap,
            'jenis' => $kegiatan->jenisKegiatan->nama,
            'status' => $status->status,
            'berkas' => $status->berkas,
            'kurang' => $status->kurang,
            'catatanStatus' => $kegiatan->catatan_status,
            'rapel' => $status->kuitansiRapel === null ? null : [
                'bulan' => $status->kuitansiRapel->bulan,
                'nomor' => $status->kuitansiRapel->nomor,
            ],
            'acara' => $kegiatan->acara === null ? null : ['id' => $kegiatan->acara->id, 'nama' => $kegiatan->acara->nama],
            'hilangDariAnggaran' => $kegiatan->hilang_dari_anggaran,
            'perluDitinjau' => $kegiatan->jenisKegiatan->cadangan && ! $kegiatan->jenis_dikonfirmasi,
        ];
    }

    /**
     * @param  Collection<int, array{kegiatan: Kegiatan, status: StatusKegiatan}>  $bulanIni
     * @return array{selesai: int, kurang: int, belum: int}
     */
    private function ringkasan(Collection $bulanIni): array
    {
        $hitung = fn (array $status): int => $bulanIni->filter(fn (array $b): bool => in_array($b['status']->status, $status, true))->count();

        return [
            'selesai' => $hitung(['selesai', 'dirapel', 'di_luar_aplikasi']),
            'kurang' => $hitung(['kurang']),
            'belum' => $hitung(['belum']),
        ];
    }

    /**
     * Bulan yang sudah lewat atau sedang berjalan tetapi PDF-nya belum diunggah.
     * Bulan seperti itu tidak punya baris, jadi jumlah tunggakan saja tidak bisa menunjukkannya.
     *
     * @param  EloquentCollection<int, Sekolah>  $sekolahs
     * @return array<int, bool>
     */
    private function pdfBelumPerBulan(EloquentCollection $sekolahs, int $tahun): array
    {
        $hasil = [];

        foreach (range(1, 12) as $bulan) {
            $sudahTiba = $tahun < now()->year || ($tahun === now()->year && $bulan <= now()->month);

            $hasil[$bulan] = $sudahTiba && $sekolahs->contains(fn (Sekolah $s): bool => $this->peringatan($s, $bulan) !== null);
        }

        return $hasil;
    }

    /**
     * @param  Collection<int, array{kegiatan: Kegiatan, status: StatusKegiatan}>  $baris
     * @return array<int, int>
     */
    private function menunggakPerBulan(Collection $baris): array
    {
        $hasil = array_fill(1, 12, 0);

        foreach ($baris as $b) {
            $bulan = $b['kegiatan']->bulanKerja();

            if ($bulan !== null && $b['status']->menunggak()) {
                $hasil[$bulan]++;
            }
        }

        return $hasil;
    }
}
