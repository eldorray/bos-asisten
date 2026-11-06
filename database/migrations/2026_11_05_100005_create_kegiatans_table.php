<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan')->nullable();
            $table->unsignedTinyInteger('tahap')->nullable();
            $table->unsignedTinyInteger('periode_impor');
            $table->string('kunci_impor');
            $table->string('kode');
            $table->string('kode_program')->nullable();
            $table->text('uraian');
            $table->string('nama');
            $table->string('koefisien');
            $table->unsignedBigInteger('harga')->default(0);
            $table->unsignedInteger('pajak')->default(0);
            $table->unsignedBigInteger('jumlah')->default(0);
            $table->foreignId('jenis_kegiatan_id')->constrained();
            $table->boolean('jenis_dikonfirmasi')->default(false);
            $table->foreignId('acara_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status_manual')->nullable();
            $table->string('catatan_status')->nullable();
            $table->boolean('hilang_dari_anggaran')->default(false);
            $table->timestamps();

            $table->unique(['sekolah_id', 'tahun', 'periode_impor', 'kunci_impor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatans');
    }
};
