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
            ->download($path, 'cadangan-spj-'.now()->format('Y-m-d').'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
