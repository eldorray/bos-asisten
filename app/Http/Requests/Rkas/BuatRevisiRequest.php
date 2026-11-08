<?php

declare(strict_types=1);

namespace App\Http\Requests\Rkas;

use App\Enums\JenisVersiRkas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuatRevisiRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'jenis' => ['required', Rule::in([JenisVersiRkas::Pergeseran->value, JenisVersiRkas::Perubahan->value])],
            'tanggal_revisi' => ['required', 'date_format:Y-m-d'],
            'pagu_baru' => ['required_if:jenis,'.JenisVersiRkas::Perubahan->value, 'nullable', 'integer', 'min:0', 'max:9999999999999'],
        ];
    }

    public function jenis(): JenisVersiRkas
    {
        return JenisVersiRkas::from($this->string('jenis')->toString());
    }

    public function tanggalRevisi(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->string('tanggal_revisi')->toString())->startOfDay();
    }

    public function paguBaru(): ?int
    {
        return $this->filled('pagu_baru') ? $this->integer('pagu_baru') : null;
    }
}
