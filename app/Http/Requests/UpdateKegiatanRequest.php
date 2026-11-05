<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SistemAnggaran;
use App\Enums\StatusManual;
use App\Models\Kegiatan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateKegiatanRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'bulan' => ['sometimes', 'integer', 'between:1,12'],
            'jenis_kegiatan_id' => ['sometimes', 'integer', Rule::exists('jenis_kegiatans', 'id')],
            'status_manual' => ['sometimes', 'nullable', Rule::enum(StatusManual::class)],
            'catatan_status' => [
                Rule::requiredIf(fn (): bool => $this->input('status_manual') === StatusManual::TidakDilaksanakan->value),
                'nullable', 'string', 'max:255',
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $kegiatan = $this->route('kegiatan');

            if (
                $this->has('jenis_kegiatan_id')
                && $kegiatan instanceof Kegiatan
                && $kegiatan->acara_id !== null
                && $this->integer('jenis_kegiatan_id') !== $kegiatan->jenis_kegiatan_id
            ) {
                $validator->errors()->add('jenis_kegiatan_id', 'Kegiatan ini bagian dari acara. Pisahkan acaranya dulu sebelum mengubah jenis.');
            }

            if (
                $this->has('jenis_kegiatan_id')
                && $kegiatan instanceof Kegiatan
                && $this->integer('jenis_kegiatan_id') !== $kegiatan->jenis_kegiatan_id
                && $kegiatan->fotos()->exists()
            ) {
                $validator->errors()->add('jenis_kegiatan_id', 'Kegiatan ini sudah punya foto berkas. Hapus fotonya dulu sebelum mengubah jenis.');
            }

            if (! $this->has('bulan') || $validator->errors()->has('bulan')) {
                return;
            }

            $pesan = $this->pesanBulan($this->route('kegiatan'), $this->integer('bulan'));

            if ($pesan !== null) {
                $validator->errors()->add('bulan', $pesan);
            }
        }];
    }

    private function pesanBulan(mixed $kegiatan, int $bulan): ?string
    {
        if (! $kegiatan instanceof Kegiatan || $kegiatan->bulan === $bulan) {
            return null;
        }

        $kegiatan->loadMissing('sekolah')->loadCount('kuitansi');

        if ($kegiatan->sekolah->sistem !== SistemAnggaran::Erkam) {
            return 'Bulan kegiatan ARKAS ditentukan oleh PDF dan tidak bisa diubah.';
        }

        if ($kegiatan->acara_id !== null || $kegiatan->getAttribute('kuitansi_count') > 0) {
            return 'Bulan tidak bisa diubah karena kegiatan ini sudah punya kuitansi atau acara.';
        }

        if ($kegiatan->tahap === null || intdiv($bulan - 1, 6) + 1 !== $kegiatan->tahap) {
            return $kegiatan->tahap === null
                ? 'Kegiatan ini tidak punya tahap.'
                : 'Bulan harus berada di Tahap '.$kegiatan->tahap.'.';
        }

        return null;
    }
}
