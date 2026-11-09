<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuatCadangan;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CadanganController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $path = BuatCadangan::jalankan();

        return response()
            // Berisi database dan hash password: tidak boleh tersimpan di cache peramban atau proxy.
            ->download($path, 'cadangan-spj-'.now()->format('Y-m-d').'.zip', [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'no-store, private',
            ])
            ->deleteFileAfterSend();
    }
}
