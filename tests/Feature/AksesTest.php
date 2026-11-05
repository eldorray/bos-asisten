<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('pendaftaran tertutup', fn () => $this->get('/register')->assertNotFound());

test('tamu diarahkan ke login', function () {
    $this->get('/')->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertRedirect('/login');
});

test('beranda mengarah ke dashboard', fn () => $this->get(route('home'))->assertRedirect('/dashboard'));

test('dashboard perlu login', fn () => $this->get('/dashboard')->assertRedirect('/login'));

test('perintah membuat pengguna terverifikasi', function () {
    $this->artisan('app:buat-pengguna', ['email' => 'a@b.test'])
        ->expectsQuestion('Nama', 'Pemilik')
        ->expectsQuestion('Password', 'rahasia-panjang-123')
        ->assertSuccessful();

    $user = User::firstWhere('email', 'a@b.test');

    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->name)->toBe('Pemilik')
        ->and($user->password)->not->toBe('rahasia-panjang-123');
});

test('perintah menolak password pendek', function () {
    $this->artisan('app:buat-pengguna', ['email' => 'a@b.test'])
        ->expectsQuestion('Nama', 'Pemilik')
        ->expectsQuestion('Password', 'pendek')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

test('perintah menolak email yang sudah terdaftar', function () {
    User::factory()->create(['email' => 'a@b.test']);

    $this->artisan('app:buat-pengguna', ['email' => 'a@b.test'])->assertFailed();

    expect(User::count())->toBe(1);
});

test('login dibatasi percobaannya', function () {
    RateLimiter::clear('a@b.test|127.0.0.1');
    User::factory()->create(['email' => 'a@b.test']);

    // Batas Fortify: 5 percobaan per menit, jadi percobaan ke-6 berstatus 429.
    foreach (range(1, 5) as $percobaan) {
        $this->post('/login', ['email' => 'a@b.test', 'password' => 'salah'])
            ->assertSessionHasErrors('email');
    }

    $this->post('/login', ['email' => 'a@b.test', 'password' => 'salah'])
        ->assertStatus(429);
});
