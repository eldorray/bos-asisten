<?php

declare(strict_types=1);

namespace App\Http\Requests\Rkas;

use App\Actions\Rkas\PesanRkas;
use App\Enums\JenisVersiRkas;
use App\Enums\SistemAnggaran;
use App\Enums\SumberDanaRkas;
use App\Models\RkasKertasKerja;
use App\Models\Sekolah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SimpanKertasKerjaRequest extends FormRequest
{
    private const PAGU_MAKS = 9_999_999_999_999;

    private function kertasKerja(): ?RkasKertasKerja
    {
        $model = $this->route('kertasKerja');

        return $model instanceof RkasKertasKerja ? $model : null;
    }

    private function sumberDana(): ?SumberDanaRkas
    {
        $kertasKerja = $this->kertasKerja();

        return $kertasKerja !== null
            ? $kertasKerja->sumber_dana
            : SumberDanaRkas::tryFrom((string) $this->input('sumber_dana'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = $this->kertasKerja() === null
            ? [
                'sekolah_id' => ['required', 'integer', 'exists:sekolahs,id'],
                'tahun' => ['required', 'integer', 'between:2000,2100'],
                'sumber_dana' => ['required', Rule::enum(SumberDanaRkas::class)],
            ]
            : [];

        if ($this->sumberDana() === SumberDanaRkas::Lainnya) {
            return $rules + [
                'pemberi_dana' => ['required', 'array', 'min:1', 'max:50'],
                'pemberi_dana.*.nama' => ['required', 'string', 'max:255'],
                'pemberi_dana.*.jumlah' => ['required', 'integer', 'min:1', 'max:'.self::PAGU_MAKS],
            ];
        }

        return $rules + [
            'pagu' => ['required', 'integer', 'min:0', 'max:'.self::PAGU_MAKS],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $kertasKerja = $this->kertasKerja();

            if ($kertasKerja !== null) {
                if (! $kertasKerja->bisaDiubah()) {
                    $validator->errors()->add('pagu', sprintf(PesanRkas::TERKUNCI, $kertasKerja->status->label()));
                }

                return;
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $sekolah = Sekolah::find($this->integer('sekolah_id'));

            if ($sekolah === null || $sekolah->sistem !== SistemAnggaran::Arkas) {
                $validator->errors()->add('sekolah_id', PesanRkas::BUKAN_ARKAS);

                return;
            }

            $sudahAda = RkasKertasKerja::query()
                ->where('sekolah_id', $sekolah->id)
                ->where('tahun', $this->integer('tahun'))
                ->where('sumber_dana', $this->string('sumber_dana')->toString())
                ->where('jenis_versi', JenisVersiRkas::Awal->value)
                ->exists();

            if ($sudahAda) {
                $validator->errors()->add('sumber_dana', PesanRkas::SUDAH_ADA);
            }
        }];
    }

    public function sekolah(): Sekolah
    {
        return Sekolah::findOrFail($this->integer('sekolah_id'));
    }
}
