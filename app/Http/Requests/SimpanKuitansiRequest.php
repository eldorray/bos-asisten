<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Kegiatan;
use App\Models\Kuitansi;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SimpanKuitansiRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        [$sekolahId, $tahun, $kuitansiId] = $this->konteks();

        $rules = [
            'nomor' => [
                'required', 'string', 'max:50', 'regex:/^\d{1,9}(?!\d)/',
                Rule::unique('kuitansis', 'nomor')
                    ->where('sekolah_id', $sekolahId)
                    ->where('tahun', $tahun)
                    ->ignore($kuitansiId),
            ],
            'untuk_pembayaran' => ['required', 'string', 'max:200'],
            'penerima' => ['required', 'string', 'max:255'],
            'tanggal_lunas' => [
                'required', 'date_format:Y-m-d',
                function (string $atribut, mixed $nilai, Closure $gagal) use ($tahun): void {
                    if (is_string($nilai) && Carbon::hasFormat($nilai, 'Y-m-d') && (int) substr($nilai, 0, 4) !== $tahun) {
                        $gagal("Tanggal lunas harus berada di tahun anggaran {$tahun}.");
                    }
                },
            ],
            'jumlah' => ['required', 'integer', 'min:1', 'max:999999999999'],
        ];

        if ($this->route('kegiatan') instanceof Kegiatan) {
            $rules['kegiatan_rapel_ids'] = ['sometimes', 'array', 'max:11'];
            $rules['kegiatan_rapel_ids.*'] = ['integer', 'distinct', Rule::exists('kegiatans', 'id')];
        }

        return $rules;
    }

    /**
     * @return array{nomor: string, untuk_pembayaran: string, penerima: string, tanggal_lunas: string, jumlah: int}
     */
    public function isian(): array
    {
        return [
            'nomor' => $this->string('nomor')->toString(),
            'untuk_pembayaran' => $this->string('untuk_pembayaran')->toString(),
            'penerima' => $this->string('penerima')->toString(),
            'tanggal_lunas' => $this->string('tanggal_lunas')->toString(),
            'jumlah' => $this->integer('jumlah'),
        ];
    }

    /**
     * @return list<int>
     */
    public function rapelIds(): array
    {
        return array_values(array_map(intval(...), $this->array('kegiatan_rapel_ids')));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nomor.required' => 'Nomor bukti wajib diisi.',
            'nomor.regex' => 'Nomor bukti harus diawali angka urut (maksimal 9 digit), misalnya 001/01/SMPG/2026.',
            'nomor.unique' => 'Nomor ini sudah dipakai kuitansi lain.',
            'untuk_pembayaran.required' => 'Isi untuk pembayaran apa.',
            'untuk_pembayaran.max' => 'Untuk pembayaran maksimal 200 karakter.',
            'penerima.required' => 'Isi nama penerima.',
            'tanggal_lunas.required' => 'Isi tanggal lunas.',
            'tanggal_lunas.date_format' => 'Tanggal lunas tidak sah.',
            'jumlah.required' => 'Isi jumlah.',
            'jumlah.integer' => 'Jumlah harus berupa angka rupiah.',
            'jumlah.min' => 'Jumlah minimal Rp 1.',
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int|null}
     */
    private function konteks(): array
    {
        $kegiatan = $this->route('kegiatan');
        $kuitansi = $this->route('kuitansi');

        if ($kegiatan instanceof Kegiatan) {
            return [$kegiatan->sekolah_id, $kegiatan->tahun, null];
        }

        if ($kuitansi instanceof Kuitansi) {
            return [$kuitansi->sekolah_id, $kuitansi->tahun, $kuitansi->id];
        }

        return [0, 0, null];
    }
}
