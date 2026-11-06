<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SusunLayarBulan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BulanController extends Controller
{
    public function __invoke(Request $request, SusunLayarBulan $susun): Response
    {
        $bulan = $request->query('bulan');

        if (is_string($bulan) && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $bulan, $m) === 1) {
            [$tahun, $nomor] = [(int) $m[1], (int) $m[2]];
        } else {
            [$tahun, $nomor] = [now()->year, now()->month];
        }

        return Inertia::render('Dashboard', $susun->susun($tahun, $nomor));
    }
}
