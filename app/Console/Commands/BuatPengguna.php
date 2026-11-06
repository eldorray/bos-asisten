<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('app:buat-pengguna {email : Alamat email pengguna}')]
#[Description('Buat akun pengguna yang sudah terverifikasi')]
class BuatPengguna extends Command
{
    private const PANJANG_PASSWORD_MINIMAL = 12;

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (User::where('email', $email)->exists()) {
            $this->components->error('Email sudah terdaftar.');

            return self::FAILURE;
        }

        $nama = trim((string) $this->ask('Nama'));
        $password = (string) $this->secret('Password');

        if ($nama === '') {
            $this->components->error('Nama wajib diisi.');

            return self::FAILURE;
        }

        if (mb_strlen($password) < self::PANJANG_PASSWORD_MINIMAL) {
            $this->components->error('Password minimal '.self::PANJANG_PASSWORD_MINIMAL.' karakter.');

            return self::FAILURE;
        }

        $pengguna = new User(['name' => $nama, 'email' => $email, 'password' => $password]);
        $pengguna->email_verified_at = Carbon::now();
        $pengguna->save();

        $this->components->info("Pengguna {$email} berhasil dibuat.");

        return self::SUCCESS;
    }
}
