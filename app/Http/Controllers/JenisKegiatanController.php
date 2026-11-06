<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\HapusJenisKegiatan;
use App\Actions\SimpanJenisKegiatan;
use App\Http\Requests\SimpanJenisKegiatanRequest;
use App\Models\JenisBerkas;
use App\Models\JenisKegiatan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class JenisKegiatanController extends Controller
{
    public function index(): Response
    {
        $jenisKegiatans = JenisKegiatan::query()->with('jenisBerkas')->orderBy('urutan')->orderBy('id')->get()
            ->map(fn (JenisKegiatan $jenis): array => [
                'id' => $jenis->id,
                'nama' => $jenis->nama,
                'kata_kunci' => implode(', ', $jenis->kata_kunci),
                'cadangan' => $jenis->cadangan,
                'berkas' => $jenis->jenisBerkas->map(fn (JenisBerkas $berkas): array => [
                    'id' => $berkas->id,
                    'nama' => $berkas->nama,
                    'tata_letak' => $berkas->tata_letak->value,
                ])->all(),
            ]);

        return Inertia::render('pengaturan/JenisKegiatan', ['jenisKegiatans' => $jenisKegiatans]);
    }

    public function store(SimpanJenisKegiatanRequest $request, SimpanJenisKegiatan $simpan): RedirectResponse
    {
        $simpan->jalankan(new JenisKegiatan, $request->dataJenis());

        return back();
    }

    public function update(SimpanJenisKegiatanRequest $request, JenisKegiatan $jenisKegiatan, SimpanJenisKegiatan $simpan): RedirectResponse
    {
        $simpan->jalankan($jenisKegiatan, $request->dataJenis());

        return back();
    }

    public function destroy(JenisKegiatan $jenisKegiatan, HapusJenisKegiatan $hapus): RedirectResponse
    {
        $alasan = $hapus->jalankan($jenisKegiatan);

        return $alasan === null ? back() : back()->withErrors(['hapus' => $alasan]);
    }
}
