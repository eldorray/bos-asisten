<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kuitansis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->unsignedTinyInteger('tahap')->nullable();
            $table->unsignedInteger('nomor_urut');
            $table->string('nomor');
            $table->text('untuk_pembayaran');
            $table->string('sumber_dana');
            $table->string('penerima');
            $table->date('tanggal_lunas');
            $table->unsignedBigInteger('jumlah');
            $table->timestamps();

            $table->unique(['sekolah_id', 'tahun', 'nomor']);
        });

        Schema::create('kegiatan_kuitansi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('kuitansi_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan_kuitansi');
        Schema::dropIfExists('kuitansis');
    }
};
