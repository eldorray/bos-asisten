<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class BuatCadangan
{
    /**
     * Membuat zip sementara berisi salinan basis data dan seluruh foto.
     * Pemanggil bertanggung jawab menghapus file hasilnya.
     */
    public static function jalankan(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP zip belum aktif di server ini, cadangan tidak bisa dibuat.');
        }

        $salinan = self::salinBasisData();
        $zipPath = self::fileSementara('cadangan-');

        try {
            $zip = new ZipArchive;

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('File cadangan tidak bisa dibuat.');
            }

            $zip->addFile($salinan, 'database.sqlite');

            $disk = Storage::disk('local');

            foreach ($disk->allFiles('foto') as $foto) {
                $zip->addFile($disk->path($foto), $foto);
            }

            // ZipArchive membaca file saat close(), jadi salinan baru boleh dihapus sesudahnya.
            if (! $zip->close()) {
                throw new RuntimeException('File cadangan tidak bisa ditulis.');
            }
        } catch (\Throwable $e) {
            @unlink($zipPath);

            throw $e;
        } finally {
            @unlink($salinan);
        }

        return $zipPath;
    }

    private static function salinBasisData(): string
    {
        $path = self::fileSementara('salinan-db-');

        try {
            DB::statement('VACUUM INTO ?', [$path]);
        } catch (\Throwable $e) {
            @unlink($path);

            throw $e;
        }

        return $path;
    }

    private static function fileSementara(string $awalan): string
    {
        $path = tempnam(sys_get_temp_dir(), $awalan);

        if ($path === false) {
            throw new RuntimeException('Folder sementara tidak bisa ditulis.');
        }

        return $path;
    }
}
