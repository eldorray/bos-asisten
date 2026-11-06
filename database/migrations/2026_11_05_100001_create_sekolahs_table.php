<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sekolahs', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('sebutan');
            $table->string('sistem');
            $table->string('identitas')->unique();
            $table->string('desa_kecamatan');
            $table->string('kabupaten');
            $table->string('provinsi');
            $table->string('kode_bukti');
            $table->string('nama_kepala');
            $table->string('nama_bendahara');
            $table->json('nomor_awal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sekolahs');
    }
};
