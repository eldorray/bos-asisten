<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Pengaturan lama dibuat dengan bawaan negeri, padahal sekolahnya swasta, sehingga batas honor terbaca 20%.
     * Sekolah negeri tinggal menghapus centang "Sekolah swasta" di Pengaturan RKAS.
     */
    public function up(): void
    {
        DB::table('rkas_pengaturans')->where('swasta', false)->update(['swasta' => true]);
    }

    public function down(): void
    {
        // Status sebelumnya tidak tersimpan, jadi tidak ada yang dikembalikan.
    }
};
