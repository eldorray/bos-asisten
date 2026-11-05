<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SimpanFoto;
use App\Http\Requests\SimpanFotoRequest;
use App\Models\Foto;
use App\Models\JenisBerkas;
use App\Models\Kegiatan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FotoController extends Controller
{
    public function store(SimpanFotoRequest $request, Kegiatan $kegiatan, SimpanFoto $simpan): RedirectResponse
    {
        $jenis = JenisBerkas::findOrFail($request->integer('jenis_berkas_id'));

        $simpan->jalankan($kegiatan, $jenis, $request->file('foto'));

        return back();
    }

    public function show(Foto $foto): BinaryFileResponse
    {
        return response()->file(Storage::disk('local')->path($foto->path), [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function destroy(Foto $foto): RedirectResponse
    {
        $foto->delete();

        return back();
    }
}
