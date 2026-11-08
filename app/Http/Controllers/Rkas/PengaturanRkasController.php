<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rkas;

use App\Enums\SistemAnggaran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rkas\UpdatePengaturanRkasRequest;
use App\Models\RkasPengaturan;
use App\Models\Sekolah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PengaturanRkasController extends Controller
{
    public function edit(Request $request): Response
    {
        $tahun = $request->integer('tahun') ?: now()->year + 1;
        abort_unless($tahun >= 2000 && $tahun <= 2100, 404);

        $sekolahs = Sekolah::query()->where('sistem', SistemAnggaran::Arkas)->orderBy('nama')->get();
        $sekolah = $request->filled('sekolah_id')
            ? $sekolahs->firstWhere('id', $request->integer('sekolah_id'))
            : $sekolahs->first();
        abort_if($sekolah === null, 404);

        $pengaturan = RkasPengaturan::untuk($sekolah, $tahun);

        return Inertia::render('pengaturan/Rkas', [
            'pengaturan' => [
                'id' => $pengaturan->id,
                'swasta' => $pengaturan->swasta,
                'persen_tahap_1' => $pengaturan->persen_tahap_1,
                'ambang_tahap_1' => $pengaturan->ambang_tahap_1,
                'honor_maks_negeri' => $pengaturan->honor_maks_negeri,
                'honor_maks_swasta' => $pengaturan->honor_maks_swasta,
                'buku_min' => $pengaturan->buku_min,
                'sarpras_maks' => $pengaturan->sarpras_maks,
                'kode_honor' => $pengaturan->kode_honor,
                'rekening_honor' => $pengaturan->rekening_honor,
                'kode_buku' => $pengaturan->kode_buku,
                'kode_sarpras' => $pengaturan->kode_sarpras,
                'rekening_sarpras_lain' => $pengaturan->rekening_sarpras_lain,
                'kuota_pergeseran' => $pengaturan->kuota_pergeseran,
                'kuota_perubahan' => $pengaturan->kuota_perubahan,
            ],
            'sekolah' => ['id' => $sekolah->id, 'nama' => $sekolah->nama],
            'pilihanSekolah' => $sekolahs->map(fn (Sekolah $s): array => ['id' => $s->id, 'nama' => $s->nama])->values(),
            'tahun' => $tahun,
        ]);
    }

    public function update(UpdatePengaturanRkasRequest $request, RkasPengaturan $pengaturan): RedirectResponse
    {
        abort_unless($pengaturan->sekolah->sistem === SistemAnggaran::Arkas, 404);

        $pengaturan->update($request->dataPengaturan());

        return back();
    }
}
