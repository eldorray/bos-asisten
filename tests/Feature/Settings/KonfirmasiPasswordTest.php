<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(fn () => RateLimiter::clear('confirm-password:1|127.0.0.1'));

test('konfirmasi password benar menandai sesi terkonfirmasi', function () {
    $user = User::factory()->create(['id' => 1]);

    $this->actingAs($user)
        ->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect(session('auth.password_confirmed_at'))->not->toBeNull();
});

test('konfirmasi password dibatasi setelah 5 kali salah', function () {
    $user = User::factory()->create(['id' => 1]);

    foreach (range(1, 5) as $i) {
        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'salah'])
            ->assertSessionHasErrors('password');
    }

    // Percobaan ke-6 ditolak walau password benar.
    $this->actingAs($user)
        ->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertSessionHasErrors('password');

    expect(session('auth.password_confirmed_at'))->toBeNull();
});

test('konfirmasi password butuh login', function () {
    $this->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertRedirect(route('login'));
});
