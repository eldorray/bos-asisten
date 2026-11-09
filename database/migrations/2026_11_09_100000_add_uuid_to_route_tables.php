<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * UUID untuk alamat URL. Kunci utama tetap angka, jadi relasi dan data lama tidak berubah.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABEL = [
        'sekolahs', 'kegiatans', 'kuitansis', 'acaras', 'fotos', 'jenis_kegiatans',
        'rkas_pengaturans', 'rkas_kertas_kerjas', 'rkas_baris',
        'rkas_ref_kegiatans', 'rkas_ref_rekenings', 'rkas_ref_barangs', 'rkas_ref_satuans', 'rkas_ptks',
    ];

    public function up(): void
    {
        foreach (self::TABEL as $tabel) {
            // Bawaan kosong hanya agar baris lama lolos NOT NULL sebelum diisi; indeks unik menolak dua baris kosong.
            Schema::table($tabel, fn (Blueprint $t) => $t->uuid('uuid')->default('')->after('id'));

            DB::table($tabel)->orderBy('id')->each(
                fn (object $baris) => DB::table($tabel)->where('id', $baris->id)->update(['uuid' => (string) Str::uuid()]),
            );

            Schema::table($tabel, fn (Blueprint $t) => $t->unique('uuid'));
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel) {
            Schema::table($tabel, function (Blueprint $t): void {
                $t->dropUnique(['uuid']);
                $t->dropColumn('uuid');
            });
        }
    }
};
