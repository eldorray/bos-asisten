<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SimpanKuitansi;
use App\Http\Requests\SimpanKuitansiRequest;
use App\Models\Kegiatan;
use App\Models\Kuitansi;
use Illuminate\Http\RedirectResponse;

class KuitansiController extends Controller
{
    public function store(SimpanKuitansiRequest $request, Kegiatan $kegiatan, SimpanKuitansi $simpan): RedirectResponse
    {
        $simpan->buat([$kegiatan->id, ...$request->rapelIds()], $request->isian());

        return to_route('kegiatan.show', $kegiatan);
    }

    public function update(SimpanKuitansiRequest $request, Kuitansi $kuitansi, SimpanKuitansi $simpan): RedirectResponse
    {
        $simpan->ubah($kuitansi, $request->isian());

        return back();
    }

    public function destroy(Kuitansi $kuitansi, SimpanKuitansi $simpan): RedirectResponse
    {
        $simpan->hapus($kuitansi);

        return back();
    }
}
