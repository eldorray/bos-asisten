<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PratinjauImporRequest;
use App\Http\Requests\SimpanImporRequest;
use App\Impor\ImporDitolak;
use App\Impor\PdfAnggaranParser;
use App\Impor\RingkasanImpor;
use App\Impor\SimpanAnggaran;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ImporController extends Controller
{
    private const FOLDER = 'impor-sementara';

    private const KEDALUWARSA = 'Pratinjau sudah tidak berlaku, unggah ulang';

    public function index(): Response
    {
        return Inertia::render('impor/Index', [
            'pratinjau' => session('pratinjau', []),
            'hasil' => session('hasil', []),
        ]);
    }

    public function pratinjau(PratinjauImporRequest $request, PdfAnggaranParser $parser, SimpanAnggaran $simpan): RedirectResponse
    {
        $this->bersihkan();

        $daftar = [];

        foreach ($request->file('files', []) as $file) {
            $token = (string) Str::uuid();
            $path = $file->storeAs(self::FOLDER, "{$token}.pdf", 'local');
            $nama = $file->getClientOriginalName();

            try {
                $ringkasan = $simpan->bandingkan($parser->parse(Storage::disk('local')->path((string) $path)));
                Cache::put($this->kunciNama($token), $nama, now()->addDay());
                $daftar[] = ['token' => $token, 'nama_file' => $nama, 'ringkasan' => $this->ringkasan($ringkasan)];
            } catch (Throwable $e) {
                Storage::disk('local')->delete((string) $path);
                $daftar[] = ['nama_file' => $nama, 'ditolak' => $this->pesan($e)];
            }
        }

        return to_route('impor.index')->with('pratinjau', $daftar);
    }

    public function simpan(SimpanImporRequest $request, PdfAnggaranParser $parser, SimpanAnggaran $simpan): RedirectResponse
    {
        $hasil = [];

        /** @var list<string> $tokens */
        $tokens = array_values(array_unique($request->validated('tokens')));

        foreach ($tokens as $token) {
            $path = self::FOLDER."/{$token}.pdf";
            $nama = (string) Cache::get($this->kunciNama($token), "{$token}.pdf");

            if (! Storage::disk('local')->exists($path)) {
                $hasil[] = ['nama_file' => $nama, 'ditolak' => self::KEDALUWARSA];

                continue;
            }

            try {
                $ringkasan = $simpan->simpan($parser->parse(Storage::disk('local')->path($path)), $nama);
                $hasil[] = ['nama_file' => $nama, 'ringkasan' => $this->ringkasan($ringkasan)];
            } catch (UniqueConstraintViolationException) {
                // Simpan ganda yang berbarengan: salah satunya sudah menyimpan lebih dulu.
                $hasil[] = ['nama_file' => $nama, 'ditolak' => self::KEDALUWARSA];
            } catch (Throwable $e) {
                $hasil[] = ['nama_file' => $nama, 'ditolak' => $this->pesan($e)];
            }

            Storage::disk('local')->delete($path);
            Cache::forget($this->kunciNama($token));
        }

        return to_route('impor.index')->with('hasil', $hasil);
    }

    private function bersihkan(): void
    {
        $batas = now()->subDay()->getTimestamp();
        $disk = Storage::disk('local');

        foreach ($disk->files(self::FOLDER) as $file) {
            if ($disk->lastModified($file) < $batas) {
                $disk->delete($file);
            }
        }
    }

    private function kunciNama(string $token): string
    {
        return "impor-nama.{$token}";
    }

    private function pesan(Throwable $e): string
    {
        if ($e instanceof ImporDitolak) {
            return $e->getMessage();
        }

        report($e);

        return 'File tidak bisa dibaca. Pastikan ini PDF asli dari ARKAS atau ERKAM.';
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkasan(RingkasanImpor $r): array
    {
        return [
            'sekolah' => $r->sekolah->nama,
            'bulan' => $r->bulan,
            'tahun' => $r->tahun,
            'jumlah_kegiatan' => $r->jumlahKegiatan,
            'total' => $r->total,
            'baru' => $r->baru,
            'berubah' => $r->berubah,
            'hilang' => $r->hilang,
            'rincian' => $r->rincian,
        ];
    }
}
