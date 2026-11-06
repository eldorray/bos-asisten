<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_kegiatans', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->json('kata_kunci');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('cadangan')->default(false);
            $table->timestamps();
        });

        Schema::create('jenis_berkas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_kegiatan_id')->constrained()->cascadeOnDelete();
            $table->string('nama');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->string('tata_letak')->default('penuh');
            $table->timestamps();

            $table->unique(['jenis_kegiatan_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_berkas');
        Schema::dropIfExists('jenis_kegiatans');
    }
};
