<?php

use App\Models\Kegiatan;
use App\Models\Sekolah;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard menampilkan layar bulanan', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('ringkasan')
        ->has('sekolah')
        ->has('menunggakPerBulan', 12));
});

test('dashboard mengirim anggaran per sekolah per bulan', function () {
    $this->actingAs(User::factory()->create());
    $sekolah = Sekolah::factory()->create();
    $kegiatan = fn (int $bulan, int $jumlah) => Kegiatan::factory()->create(['sekolah_id' => $sekolah->id, 'tahun' => 2026, 'bulan' => $bulan, 'jumlah' => $jumlah]);
    $kegiatan(7, 1_000_000);
    $kegiatan(7, 500_000);
    $kegiatan(8, 2_000_000);

    $this->get(route('dashboard', ['bulan' => '2026-07']))->assertInertia(fn ($page) => $page
        ->where("anggaranPerBulan.{$sekolah->id}.7", 1_500_000)
        ->where("anggaranPerBulan.{$sekolah->id}.8", 2_000_000)
        ->where("anggaranPerBulan.{$sekolah->id}.9", 0));
});
