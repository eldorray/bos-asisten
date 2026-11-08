<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rkas_ref_kegiatans', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            $table->string('kode');
            $table->string('kode_induk')->nullable();
            $table->text('uraian');
            $table->unsignedTinyInteger('level');
            $table->string('sumber_dana')->default('bosp_reguler');
            $table->date('kedaluwarsa')->nullable();
            $table->timestamps();

            $table->unique(['tahun', 'kode', 'sumber_dana']);
        });

        Schema::create('rkas_ref_rekenings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            $table->string('kode');
            $table->string('nama');
            $table->date('kedaluwarsa')->nullable();
            $table->timestamps();

            $table->unique(['tahun', 'kode']);
        });

        Schema::create('rkas_ref_barangs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            $table->string('kode_rekening')->nullable();
            $table->string('nama');
            $table->string('satuan');
            $table->unsignedBigInteger('harga')->default(0);
            $table->unsignedBigInteger('batas_bawah')->default(0);
            $table->unsignedBigInteger('batas_atas')->default(0);
            $table->date('kedaluwarsa')->nullable();
            $table->timestamps();
        });

        Schema::create('rkas_ref_satuans', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->timestamps();
        });

        Schema::create('rkas_ptks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained()->cascadeOnDelete();
            $table->string('nama');
            $table->string('nuptk')->nullable();
            $table->unsignedTinyInteger('jenis');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rkas_ptks');
        Schema::dropIfExists('rkas_ref_satuans');
        Schema::dropIfExists('rkas_ref_barangs');
        Schema::dropIfExists('rkas_ref_rekenings');
        Schema::dropIfExists('rkas_ref_kegiatans');
    }
};
