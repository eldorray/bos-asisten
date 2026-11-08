<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rkas_pengaturans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->boolean('swasta')->default(false);
            $table->unsignedTinyInteger('persen_tahap_1');
            $table->unsignedTinyInteger('ambang_tahap_1');
            $table->unsignedTinyInteger('honor_maks_negeri');
            $table->unsignedTinyInteger('honor_maks_swasta');
            $table->unsignedTinyInteger('buku_min');
            $table->unsignedTinyInteger('sarpras_maks');
            $table->json('kode_honor');
            $table->string('rekening_honor');
            $table->json('kode_buku');
            $table->json('kode_sarpras');
            $table->json('rekening_sarpras_lain');
            $table->unsignedTinyInteger('kuota_pergeseran')->nullable();
            $table->unsignedTinyInteger('kuota_perubahan')->nullable();
            $table->timestamps();

            $table->unique(['sekolah_id', 'tahun']);
        });

        Schema::create('rkas_kertas_kerjas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->string('sumber_dana');
            $table->unsignedBigInteger('pagu')->default(0);
            $table->string('jenis_versi');
            $table->unsignedTinyInteger('nomor_pergeseran')->default(0);
            $table->unsignedTinyInteger('nomor_perubahan')->default(0);
            $table->foreignId('induk_id')->nullable()->constrained('rkas_kertas_kerjas')->restrictOnDelete();
            $table->date('tanggal_revisi')->nullable();
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('rkas_pemberi_danas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kertas_kerja_id')->constrained('rkas_kertas_kerjas')->cascadeOnDelete();
            $table->string('nama');
            $table->unsignedBigInteger('jumlah')->default(0);
            $table->timestamps();
        });

        Schema::create('rkas_baris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kertas_kerja_id')->constrained('rkas_kertas_kerjas')->cascadeOnDelete();
            $table->foreignId('ref_kegiatan_id')->constrained('rkas_ref_kegiatans')->restrictOnDelete();
            $table->foreignId('ref_rekening_id')->constrained('rkas_ref_rekenings')->restrictOnDelete();
            $table->foreignId('ref_barang_id')->nullable()->constrained('rkas_ref_barangs')->restrictOnDelete();
            $table->foreignId('ptk_id')->nullable()->constrained('rkas_ptks')->restrictOnDelete();
            $table->foreignId('asal_baris_id')->nullable()->constrained('rkas_baris')->nullOnDelete();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->text('uraian');
            $table->string('satuan');
            $table->unsignedBigInteger('harga')->default(0);
            $table->json('volume_bulan');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rkas_baris');
        Schema::dropIfExists('rkas_pemberi_danas');
        Schema::dropIfExists('rkas_kertas_kerjas');
        Schema::dropIfExists('rkas_pengaturans');
    }
};
