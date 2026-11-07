<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

function hashSesi(User $user): string
{
    return Auth::guard('web')->hashPasswordForCookie($user->getAuthPassword());
}

test('ganti password mengakhiri sesi lain', function () {
    $user = User::factory()->create(['password' => Hash::make('password-lama-12345')]);
    $hashLama = hashSesi($user);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('user-password.update'), [
            'current_password' => 'password-lama-12345',
            'password' => 'password-baru-67890',
            'password_confirmation' => 'password-baru-67890',
        ])
        ->assertSessionHasNoErrors();

    Auth::forgetGuards();

    $this->actingAs($user->fresh())
        ->withSession(['password_hash_web' => $hashLama])
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('ganti password tidak mengeluarkan sesi yang sedang dipakai', function () {
    $user = User::factory()->create(['password' => Hash::make('password-lama-12345')]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('user-password.update'), [
            'current_password' => 'password-lama-12345',
            'password' => 'password-baru-67890',
            'password_confirmation' => 'password-baru-67890',
        ])
        ->assertSessionHasNoErrors();

    expect(session('password_hash_web'))->toBe(hashSesi($user->fresh()));

    $this->get(route('dashboard'))->assertOk();
});

test('reset password mengakhiri sesi lama', function () {
    $user = User::factory()->create();
    $hashLama = hashSesi($user);

    $this->post(route('password.update'), [
        'token' => Password::createToken($user),
        'email' => $user->email,
        'password' => 'password-baru-67890',
        'password_confirmation' => 'password-baru-67890',
    ])->assertSessionHasNoErrors();

    Auth::forgetGuards();

    $this->actingAs($user->fresh())
        ->withSession(['password_hash_web' => $hashLama])
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('tautan reset password memakai APP_URL, bukan header Host', function () {
    Notification::fake();
    config(['app.url' => 'https://spj.example.test']);
    $user = User::factory()->create();

    $this->post('http://penyerang.example/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notifikasi) use ($user): bool {
        $tautan = $notifikasi->toMail($user)->actionUrl;

        expect($tautan)->toStartWith('https://spj.example.test/reset-password/')
            ->and($tautan)->toContain($notifikasi->token)
            ->and($tautan)->toContain('email='.urlencode($user->email));

        return true;
    });
});
