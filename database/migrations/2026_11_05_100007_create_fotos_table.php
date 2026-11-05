<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fotos', function (Blueprint $table) {
            $table->id();
            $table->morphs('pemilik');
            $table->foreignId('jenis_berkas_id')->constrained('jenis_berkas')->restrictOnDelete();
            $table->string('path');
            $table->unsignedInteger('lebar');
            $table->unsignedInteger('tinggi');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fotos');
    }
};
