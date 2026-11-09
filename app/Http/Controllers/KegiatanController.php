<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\GabungAcara;
use App\Actions\HitungStatusKegiatan;
use App\Actions\KelayakanKuitansi;
use App\Actions\NilaiAwalKuitansi;
use App\Actions\SimpanKuitansi;
use App\Actions\TundaKegiatan;
use App\Enums\SistemAnggaran;
use App\Enums\StatusManual;
use App\Http\Requests\TandaiKegiatanRequest;
use App\Http\Requests\UpdateKegiatanRequest;
use App\Models\Foto;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Kuitansi;
use App\Support\Terbilang;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KegiatanController extends Controller
{
    public function show(Request $request, Kegiatan $kegiatan, GabungAcara $gabung): Response
    {
        $kegiatan->load([...HitungStatusKegiatan::RELASI, 'sekolah']);
        $status = HitungStatusKegiatan::untuk($kegiatan);
        $kuitansi = $kegiatan->kuitansi->first();
        $kandidat = $kuitansi === null ? KelayakanKuitansi::kandidatRapel($kegiatan) : new Collection;
        $dipilih = $this->rapelDipilih($request, $kandidat);

        return Inertia::render('kegiatan/Show', [
            'kegiatan' => [
                'id' => $kegiatan->id,
                'uuid' => $kegiatan->uuid,
                'nama' => $kegiatan->nama,
                'uraian' => $kegiatan->uraian,
                'sekolah' => $kegiatan->sekolah->nama,
                'tahun' => $kegiatan->tahun,
                'bulan' => $kegiatan->bulan,
                'bulanPelaksanaan' => $kegiatan->bulan_pelaksanaan,
                'perluDiperiksa' => $status->perluDiperiksa,
                'tahap' => $kegiatan->tahap,
                'koefisien' => $kegiatan->koefisien,
                'harga' => $kegiatan->harga,
                'pajak' => $kegiatan->pajak,
                'jumlah' => $kegiatan->jumlah,
                'jenisKegiatanId' => $kegiatan->jenis_kegiatan_id,
                'jenisDikonfirmasi' => $kegiatan->jenis_dikonfirmasi,
                'statusManual' => $kegiatan->status_manual?->value,
                'catatanStatus' => $kegiatan->catatan_status,
                'hilangDariAnggaran' => $kegiatan->hilang_dari_anggaran,
                'status' => $status->status,
                'berkas' => $status->berkas,
                'bisaUnduhPdf' => $status->bisaUnduhPdf(),
                'rapel' => $status->kuitansiRapel === null ? null : [
                    'bulan' => $status->kuitansiRapel->bulan,
                    'nomor' => $status->kuitansiRapel->nomor,
                ],
            ],
            'acara' => $kegiatan->acara === null ? null : [
                'id' => $kegiatan->acara->id,
                'uuid' => $kegiatan->acara->uuid,
                'nama' => $kegiatan->acara->nama,
                'anggota' => $kegiatan->acara->kegiatans()->orderBy('id')->get(['id', 'nama'])
                    ->map(fn (Kegiatan $k): array => ['id' => $k->id, 'nama' => $k->nama])->all(),
            ],
            'berkasFoto' => $this->berkasFoto($kegiatan),
            'kuitansi' => $kuitansi === null ? null : $this->dataKuitansi($kuitansi),
            'nilaiAwal' => $kuitansi === null && $kegiatan->bulan !== null
                ? $this->nilaiAwal($kegiatan, $dipilih)
                : null,
            'rapelDipilih' => $dipilih->modelKeys(),
            'kandidatRapel' => $kandidat->map(fn (Kegiatan $k): array => [
                'id' => $k->id,
                'bulan' => $k->bulan,
                'jumlah' => $k->jumlah,
            ])->values()->all(),
            'pilihanBulan' => $this->pilihanBulan($kegiatan),
            'tunda' => $kegiatan->sekolah->sistem === SistemAnggaran::Arkas ? [
                'pilihan' => TundaKegiatan::pilihan($kegiatan),
                'alasan' => TundaKegiatan::alasanTolak($kegiatan),
            ] : null,
            'kandidatAcara' => $gabung->kandidat($kegiatan)
                ->map(fn (Kegiatan $k): array => ['id' => $k->id, 'nama' => $k->nama, 'jumlah' => $k->jumlah])
                ->all(),
            'selisihAnggaran' => $kuitansi === null ? null : $this->selisihAnggaran($kuitansi),
            'jenisKegiatans' => JenisKegiatan::query()->orderBy('urutan')->get(['id', 'nama']),
            'tetangga' => $this->tetangga($kegiatan),
        ]);
    }

    /**
     * Kegiatan sebelum dan sesudahnya di daftar bulan yang sama, urut seperti di layar Bulanan.
     *
     * @return array{sebelumnya: array{uuid: string, nama: string}|null, berikutnya: array{uuid: string, nama: string}|null, posisi: int, total: int}|null
     */
    private function tetangga(Kegiatan $kegiatan): ?array
    {
        $bulan = $kegiatan->bulanKerja();

        if ($bulan === null) {
            return null;
        }

        $sebulan = fn () => Kegiatan::query()
            ->where('sekolah_id', $kegiatan->sekolah_id)
            ->where('tahun', $kegiatan->tahun)
            ->padaBulan($bulan);
        $ringkas = fn (?Kegiatan $k): ?array => $k === null ? null : ['uuid' => $k->uuid, 'nama' => $k->nama];

        return [
            'sebelumnya' => $ringkas($sebulan()->where('id', '<', $kegiatan->id)->orderByDesc('id')->first(['uuid', 'nama'])),
            'berikutnya' => $ringkas($sebulan()->where('id', '>', $kegiatan->id)->orderBy('id')->first(['uuid', 'nama'])),
            'posisi' => $sebulan()->where('id', '<=', $kegiatan->id)->count(),
            'total' => $sebulan()->count(),
        ];
    }

    public function update(UpdateKegiatanRequest $request, Kegiatan $kegiatan): RedirectResponse
    {
        $data = $request->validated();

        if (array_key_exists('jenis_kegiatan_id', $data)) {
            $data['jenis_dikonfirmasi'] = true;
        }

        if (array_key_exists('status_manual', $data) && $data['status_manual'] !== StatusManual::TidakDilaksanakan->value) {
            $data['catatan_status'] = null;
        }

        $kegiatan->update($data);

        return back();
    }

    public function tandai(TandaiKegiatanRequest $request): RedirectResponse
    {
        Kegiatan::whereIn('id', $request->validated('ids'))->update([
            'status_manual' => $request->validated('status_manual'),
            'catatan_status' => null,
        ]);

        return back();
    }

    public function destroy(Kegiatan $kegiatan, SimpanKuitansi $simpan, GabungAcara $gabung): RedirectResponse
    {
        abort_unless($kegiatan->hilang_dari_anggaran, 422, 'Hanya kegiatan yang sudah tidak ada di anggaran yang boleh dihapus.');

        $kuitansi = $kegiatan->kuitansi()->first();
        $bulanLayar = $kegiatan->bulanKerja() ?? ($kegiatan->tahap === null ? null : ($kegiatan->tahap - 1) * 6 + 1);

        abort_if(
            $kuitansi !== null && $kuitansi->kegiatans()->count() > 1,
            422,
            'Kegiatan ini termasuk kuitansi rapel yang mencakup kegiatan lain. Hapus kuitansinya dulu.',
        );

        DB::transaction(function () use ($kegiatan, $kuitansi, $simpan, $gabung): void {
            $acara = $kegiatan->acara;

            // Foto bersama milik acara, jadi tidak ikut terhapus; hanya foto milik baris ini.
            $kegiatan->fotos->each->delete();

            if ($kuitansi !== null) {
                $simpan->hapus($kuitansi);
            }

            $kegiatan->delete();

            if ($acara !== null && $acara->kegiatans()->count() === 1) {
                $gabung->pisah($acara);
            }
        });

        return to_route('dashboard', $bulanLayar === null ? [] : ['bulan' => sprintf('%04d-%02d', $kegiatan->tahun, $bulanLayar)]);
    }

    /**
     * Bulan yang boleh dipilih di halaman rincian. Aturan lengkapnya ada di UpdateKegiatanRequest.
     *
     * @return list<int>
     */
    private function pilihanBulan(Kegiatan $kegiatan): array
    {
        if (
            $kegiatan->sekolah->sistem !== SistemAnggaran::Erkam
            || $kegiatan->tahap === null
            || $kegiatan->acara_id !== null
            || $kegiatan->kuitansi->isNotEmpty()
        ) {
            return [];
        }

        $awal = ($kegiatan->tahap - 1) * 6 + 1;

        return range($awal, $awal + 5);
    }

    /**
     * @return array{kuitansi: int, anggaran: int}|null
     */
    private function selisihAnggaran(Kuitansi $kuitansi): ?array
    {
        $anggaran = (int) $kuitansi->kegiatans()->sum('jumlah');

        return $anggaran === $kuitansi->jumlah ? null : ['kuitansi' => $kuitansi->jumlah, 'anggaran' => $anggaran];
    }

    /**
     * Pilihan rapel dari query, dibatasi pada kandidat yang sah.
     *
     * @param  Collection<int, Kegiatan>  $kandidat
     * @return Collection<int, Kegiatan>
     */
    private function rapelDipilih(Request $request, Collection $kandidat): Collection
    {
        $ids = array_map(intval(...), array_filter($request->array('rapel'), is_scalar(...)));

        return $kandidat->whereIn('id', $ids)->values();
    }

    /**
     * @param  Collection<int, Kegiatan>  $dipilih
     * @return array<string, mixed>
     */
    private function nilaiAwal(Kegiatan $kegiatan, Collection $dipilih): array
    {
        $awal = NilaiAwalKuitansi::untuk($kegiatan, array_values($dipilih->all()));

        return $awal + ['terbilang' => Terbilang::rupiah($awal['jumlah'])];
    }

    /**
     * @return array<string, mixed>
     */
    private function dataKuitansi(Kuitansi $kuitansi): array
    {
        $kuitansi->loadMissing('kegiatans');

        return [
            'id' => $kuitansi->id,
            'uuid' => $kuitansi->uuid,
            'nomor' => $kuitansi->nomor,
            'untuk_pembayaran' => $kuitansi->untuk_pembayaran,
            'penerima' => $kuitansi->penerima,
            'tanggal_lunas' => $kuitansi->tanggal_lunas->toDateString(),
            'jumlah' => $kuitansi->jumlah,
            'sumberDana' => $kuitansi->sumber_dana,
            'terbilang' => Terbilang::rupiah($kuitansi->jumlah),
            'bulanCakupan' => array_values($kuitansi->kegiatans->map(fn (Kegiatan $k): ?int => $k->bulanKerja())->filter()->sort()->values()->all()),
        ];
    }

    /**
     * @return list<array{id: int, nama: string, fotos: list<array{id: int}>}>
     */
    private function berkasFoto(Kegiatan $kegiatan): array
    {
        $fotos = $kegiatan->pemilikBerkas()->fotos->sortBy('urutan');

        $daftar = [];

        foreach ($kegiatan->jenisKegiatan->jenisBerkas as $berkas) {
            $daftar[] = [
                'id' => $berkas->id,
                'nama' => $berkas->nama,
                'fotos' => array_values($fotos->where('jenis_berkas_id', $berkas->id)->map(fn (Foto $foto): array => ['id' => $foto->id, 'uuid' => $foto->uuid])->all()),
            ];
        }

        return $daftar;
    }
}
