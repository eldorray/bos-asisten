<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SistemAnggaran;
use App\Enums\TataLetakBerkas;
use App\Models\JenisKegiatan;
use App\Models\Sekolah;
use Illuminate\Database\Seeder;

class DataAwalSeeder extends Seeder
{
    public function run(): void
    {
        $this->sekolah();
        $this->jenisKegiatan();
    }

    private function sekolah(): void
    {
        $this->simpanSekolah('20606714', [
            'nama' => 'SMP Garuda',
            'sebutan' => 'Sekolah',
            'sistem' => SistemAnggaran::Arkas,
            'desa_kecamatan' => 'Kec. Neglasari',
            'kabupaten' => 'Kota Tangerang',
            'provinsi' => 'Banten',
            'kode_bukti' => 'SMPG',
            'nama_kepala' => 'Fahmie Al Khudhorie',
            'nama_bendahara' => 'Dra. Nurjanah',
        ]);

        $this->simpanSekolah('111236710070', [
            'nama' => 'MI Daarul Hikmah',
            'sebutan' => 'Madrasah',
            'sistem' => SistemAnggaran::Erkam,
            'desa_kecamatan' => 'Neglasari',
            'kabupaten' => 'Kota Tangerang',
            'provinsi' => 'Banten',
            'kode_bukti' => 'MIDH',
            'nama_kepala' => 'Dra. Nurjanah',
            'nama_bendahara' => 'Fahmie Al Khudhorie, S.Pd',
        ]);
    }

    /**
     * nomor_awal hanya diisi saat sekolah baru dibuat, supaya pengaturan pemilik tidak tertimpa.
     *
     * @param  array<string, mixed>  $atribut
     */
    private function simpanSekolah(string $identitas, array $atribut): void
    {
        $sekolah = Sekolah::firstOrNew(['identitas' => $identitas]);
        $sekolah->fill($atribut);

        if (! $sekolah->exists) {
            $sekolah->nomor_awal = [];
        }

        $sekolah->save();
    }

    private function jenisKegiatan(): void
    {
        $jenis = [
            'Langganan' => [
                'kata_kunci' => ['listrik', 'internet', 'bandwidth', 'retribusi', 'hosting'],
                'berkas' => [['Struk bayar', TataLetakBerkas::Penuh]],
            ],
            'Rapat/Pelatihan' => [
                'kata_kunci' => ['jamuan', 'nasi kotak', 'snack', 'konsumsi', 'rapat', 'pelatihan'],
                'berkas' => [
                    ['Undangan', TataLetakBerkas::Penuh],
                    ['Daftar hadir', TataLetakBerkas::Penuh],
                    ['Notulen', TataLetakBerkas::Penuh],
                    ['Dokumentasi foto', TataLetakBerkas::Kisi],
                ],
            ],
            'Gaji/Honor' => [
                'kata_kunci' => ['gaji', 'honor'],
                'berkas' => [],
            ],
            'Belanja barang' => [
                'kata_kunci' => [
                    'bola', 'laptop', 'komputer', 'jendela', 'kipas', 'pagar', 'tinta',
                    'kertas', 'filling', 'buku', 'cetak', 'penggandaan', 'jaringan',
                ],
                'berkas' => [
                    ['Nota', TataLetakBerkas::Penuh],
                    ['Foto barang', TataLetakBerkas::Penuh],
                ],
            ],
            'Lainnya' => [
                'kata_kunci' => [],
                'berkas' => [],
                'cadangan' => true,
            ],
        ];

        $urutan = 0;

        foreach ($jenis as $nama => $data) {
            $kegiatan = JenisKegiatan::updateOrCreate(['nama' => $nama], [
                'kata_kunci' => $data['kata_kunci'],
                'urutan' => ++$urutan,
                'cadangan' => $data['cadangan'] ?? false,
            ]);

            foreach ($data['berkas'] as $i => [$namaBerkas, $tataLetak]) {
                $kegiatan->jenisBerkas()->updateOrCreate(['nama' => $namaBerkas], [
                    'urutan' => $i + 1,
                    'tata_letak' => $tataLetak,
                ]);
            }
        }
    }
}
