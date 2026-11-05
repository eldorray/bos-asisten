<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impor_anggarans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('periode');
            $table->unsignedBigInteger('total')->default(0);
            $table->string('nama_file');
            $table->timestamps();

            $table->unique(['sekolah_id', 'tahun', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impor_anggarans');
    }
};
