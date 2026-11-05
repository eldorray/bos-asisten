<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\HitungStatusKegiatan;
use App\Enums\StatusManual;
use App\Http\Requests\TandaiKegiatanRequest;
use App\Http\Requests\UpdateKegiatanRequest;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KegiatanController extends Controller
{
    public function show(Kegiatan $kegiatan): Response
    {
        $kegiatan->load([...HitungStatusKegiatan::RELASI, 'sekolah']);
        $status = HitungStatusKegiatan::untuk($kegiatan);

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

    public function destroy(Kegiatan $kegiatan): RedirectResponse
    {
        abort_unless($kegiatan->hilang_dari_anggaran, 422, 'Hanya kegiatan yang sudah tidak ada di anggaran yang boleh dihapus.');

        DB::transaction(function () use ($kegiatan): void {
            $kegiatan->fotos->each->delete();
            $kegiatan->kuitansi()->detach();
            $kegiatan->delete();
        });

        return to_route('dashboard');
    }
}
