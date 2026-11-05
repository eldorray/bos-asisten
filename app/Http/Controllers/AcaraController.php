<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\GabungAcara;
use App\Http\Requests\GabungAcaraRequest;
use App\Models\Acara;
use Illuminate\Http\RedirectResponse;

class AcaraController extends Controller
{
    public function store(GabungAcaraRequest $request, GabungAcara $gabung): RedirectResponse
    {
        /** @var array<int, int> $ids */
        $ids = array_map(intval(...), $request->validated('kegiatan_ids'));

        $gabung->gabung($ids, $request->validated('nama'));

        return back();
    }

    public function destroy(Acara $acara, GabungAcara $gabung): RedirectResponse
    {
        $gabung->pisah($acara);

        return back();
    }
}
