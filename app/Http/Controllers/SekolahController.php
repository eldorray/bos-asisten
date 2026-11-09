<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\HapusSekolah;
use App\Actions\UrutkanNomorKuitansi;
use App\Http\Requests\UpdateSekolahRequest;
use App\Models\Sekolah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SekolahController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('pengaturan/Sekolah', [
            'sekolahs' => Sekolah::query()->orderBy('nama')->get()->map(fn (Sekolah $sekolah): array => [
                'id' => $sekolah->id,
                'uuid' => $sekolah->uuid,
                'nama' => $sekolah->nama,
                'sebutan' => $sekolah->sebutan,
                'sistem' => $sekolah->sistem->value,
                'identitas' => $sekolah->identitas,
                'desa_kecamatan' => $sekolah->desa_kecamatan,
                'kabupaten' => $sekolah->kabupaten,
                'provinsi' => $sekolah->provinsi,
                'kode_bukti' => $sekolah->kode_bukti,
                'nama_kepala' => $sekolah->nama_kepala,
                'nama_bendahara' => $sekolah->nama_bendahara,
                'nomor_awal' => (object) $sekolah->nomor_awal,
            ]),
            'tahun' => now()->year,
        ]);
    }

    public function update(UpdateSekolahRequest $request, Sekolah $sekolah): RedirectResponse
    {
        $data = $request->safe()->except(['tahun', 'nomor_awal']);

        if ($request->filled('nomor_awal')) {
            $nomorAwal = $sekolah->nomor_awal;
            $nomorAwal[$request->integer('tahun')] = $request->integer('nomor_awal');
            $data['nomor_awal'] = $nomorAwal;
        }

        $sekolah->update($data);

        return back();
    }

    public function destroy(Request $request, Sekolah $sekolah, HapusSekolah $hapus): RedirectResponse
    {
        $request->validate([
            'konfirmasi' => ['required', 'string', Rule::in([$sekolah->nama])],
        ], [
            'konfirmasi.in' => 'Nama yang diketik tidak sama dengan nama sekolah.',
        ]);

        $hapus->jalankan($sekolah);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$sekolah->nama} dihapus"]);

        return to_route('sekolah.index');
    }

    public function urutkanNomor(Request $request, Sekolah $sekolah, UrutkanNomorKuitansi $urutkan): RedirectResponse
    {
        $tahun = $request->validate(['tahun' => ['required', 'integer', 'between:2000,2100']])['tahun'];
        $berubah = $urutkan->jalankan($sekolah, (int) $tahun);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $berubah === 0 ? 'Nomor bukti sudah urut' : "{$berubah} nomor bukti diurutkan",
        ]);

        return back();
    }
}
