<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\HitungStatusKegiatan;
use App\Actions\KelayakanKuitansi;
use App\Actions\NilaiAwalKuitansi;
use App\Actions\SimpanKuitansi;
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
    public function show(Request $request, Kegiatan $kegiatan): Response
    {
        $kegiatan->load([...HitungStatusKegiatan::RELASI, 'sekolah']);
        $status = HitungStatusKegiatan::untuk($kegiatan);
        $kuitansi = $kegiatan->kuitansi->first();
        $kandidat = $kuitansi === null ? KelayakanKuitansi::kandidatRapel($kegiatan) : new Collection;
        $dipilih = $this->rapelDipilih($request, $kandidat);

        return Inertia::render('kegiatan/Show', [
            'kegiatan' => [
                'id' => $kegiatan->id,
                'nama' => $kegiatan->nama,
                'uraian' => $kegiatan->uraian,
                'sekolah' => $kegiatan->sekolah->nama,
                'tahun' => $kegiatan->tahun,
                'bulan' => $kegiatan->bulan,
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
                'rapel' => $status->kuitansiRapel === null ? null : [
                    'bulan' => $status->kuitansiRapel->bulan,
                    'nomor' => $status->kuitansiRapel->nomor,
                ],
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
            'jenisKegiatans' => JenisKegiatan::query()->orderBy('urutan')->get(['id', 'nama']),
        ]);
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

    public function destroy(Kegiatan $kegiatan, SimpanKuitansi $simpan): RedirectResponse
    {
        abort_unless($kegiatan->hilang_dari_anggaran, 422, 'Hanya kegiatan yang sudah tidak ada di anggaran yang boleh dihapus.');

        $kuitansi = $kegiatan->kuitansi()->first();

        abort_if(
            $kuitansi !== null && $kuitansi->kegiatans()->count() > 1,
            422,
            'Kegiatan ini termasuk kuitansi rapel yang mencakup kegiatan lain. Hapus kuitansinya dulu.',
        );

        DB::transaction(function () use ($kegiatan, $kuitansi, $simpan): void {
            $kegiatan->fotos->each->delete();

            if ($kuitansi !== null) {
                $simpan->hapus($kuitansi);
            }

            $kegiatan->delete();
        });

        return to_route('dashboard');
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
            'nomor' => $kuitansi->nomor,
            'untuk_pembayaran' => $kuitansi->untuk_pembayaran,
            'penerima' => $kuitansi->penerima,
            'tanggal_lunas' => $kuitansi->tanggal_lunas->toDateString(),
            'jumlah' => $kuitansi->jumlah,
            'sumberDana' => $kuitansi->sumber_dana,
            'terbilang' => Terbilang::rupiah($kuitansi->jumlah),
            'bulanCakupan' => array_values($kuitansi->kegiatans->pluck('bulan')->filter()->sort()->values()->all()),
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
                'fotos' => array_values($fotos->where('jenis_berkas_id', $berkas->id)->map(fn (Foto $foto): array => ['id' => $foto->id])->all()),
            ];
        }

        return $daftar;
    }
}
