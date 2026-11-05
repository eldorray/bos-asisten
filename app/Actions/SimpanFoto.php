<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Foto;
use App\Models\JenisBerkas;
use App\Models\Kegiatan;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SimpanFoto
{
    public const PESAN_FORMAT = 'Format foto tidak didukung. Ubah ke JPG lalu unggah lagi.';

    public const SISI_MAKS = 1600;

    private const MUTU_JPEG = 80;

    public function jalankan(Kegiatan $kegiatan, JenisBerkas $jenis, UploadedFile $file): Foto
    {
        $sumber = (string) $file->getRealPath();
        $info = @getimagesize($sumber);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw $this->gagal(self::PESAN_FORMAT);
        }

        [$lebar, $tinggi] = [$info[0], $info[1]];
        $orientasi = $info[2] === IMAGETYPE_JPEG ? $this->orientasi($sumber) : 1;
        $path = 'foto/'.now()->format('Y').'/'.Str::uuid().'.jpg';
        $disk = Storage::disk('local');

        if ($info[2] === IMAGETYPE_JPEG && $orientasi === 1 && max($lebar, $tinggi) <= self::SISI_MAKS) {
            $disk->putFileAs(dirname($path), $file, basename($path));
        } else {
            [$jpeg, $lebar, $tinggi] = $this->olah($sumber, $orientasi);
            $disk->put($path, $jpeg);
        }

        $pemilik = $kegiatan->pemilikBerkas();
        $urutan = (int) $pemilik->fotos()->where('jenis_berkas_id', $jenis->id)->max('urutan');

        return $pemilik->fotos()->create([
            'jenis_berkas_id' => $jenis->id,
            'path' => $path,
            'lebar' => $lebar,
            'tinggi' => $tinggi,
            'urutan' => $urutan + 1,
        ]);
    }

    private function orientasi(string $sumber): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($sumber);

        return is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    private function olah(string $sumber, int $orientasi): array
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            throw $this->gagal('Server belum bisa memproses foto ini. Kecilkan foto lalu unggah lagi.');
        }

        $asli = @imagecreatefromstring((string) file_get_contents($sumber));

        if ($asli === false) {
            throw $this->gagal(self::PESAN_FORMAT);
        }

        $skala = min(1, self::SISI_MAKS / max(imagesx($asli), imagesy($asli)));
        $kanvas = imagecreatetruecolor(max(1, (int) round(imagesx($asli) * $skala)), max(1, (int) round(imagesy($asli) * $skala)));
        imagefill($kanvas, 0, 0, (int) imagecolorallocate($kanvas, 255, 255, 255));
        imagecopyresampled($kanvas, $asli, 0, 0, 0, 0, imagesx($kanvas), imagesy($kanvas), imagesx($asli), imagesy($asli));
        unset($asli);

        $tegak = $this->tegakkan($kanvas, $orientasi);

        ob_start();
        imagejpeg($tegak, null, self::MUTU_JPEG);

        return [(string) ob_get_clean(), imagesx($tegak), imagesy($tegak)];
    }

    private function tegakkan(GdImage $gambar, int $orientasi): GdImage
    {
        $putar = match ($orientasi) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($putar !== 0) {
            $gambar = imagerotate($gambar, $putar, 0) ?: $gambar;
        }

        // Orientasi 2, 4 (diputar 180 di atas), 5, dan 7 adalah foto tercermin.
        if (in_array($orientasi, [2, 4, 5, 7], true)) {
            imageflip($gambar, IMG_FLIP_HORIZONTAL);
        }

        return $gambar;
    }

    private function gagal(string $pesan): ValidationException
    {
        return ValidationException::withMessages(['foto' => $pesan]);
    }
}
