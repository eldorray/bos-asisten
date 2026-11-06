<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kegiatans', function (Blueprint $table): void {
            $table->unsignedTinyInteger('bulan_pelaksanaan')->nullable()->after('bulan');
        });
    }

    public function down(): void
    {
        Schema::table('kegiatans', function (Blueprint $table): void {
            $table->dropColumn('bulan_pelaksanaan');
        });
    }
};
