<?php

use App\Models\Kegiatan;
use App\Models\Kuitansi;
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

test('realisasi per bulan memakai jumlah kuitansi, rapel dibagi menurut porsi anggaran', function () {
    $this->actingAs(User::factory()->create());
    $sekolah = Sekolah::factory()->create();
    $kegiatan = fn (int $bulan, int $jumlah) => Kegiatan::factory()->create(['sekolah_id' => $sekolah->id, 'tahun' => 2026, 'bulan' => $bulan, 'jumlah' => $jumlah]);
    $satu = $kegiatan(1, 1_000_000);
    $dua = $kegiatan(2, 3_000_000);
    $tanpaKuitansi = $kegiatan(3, 500_000);

    $rapel = Kuitansi::factory()->create(['sekolah_id' => $sekolah->id, 'tahun' => 2026, 'bulan' => 2, 'jumlah' => 3_600_000]);
    $rapel->kegiatans()->attach([$satu->id, $dua->id]);

    $this->get(route('dashboard', ['bulan' => '2026-01']))->assertInertia(fn ($page) => $page
        ->where("realisasiPerBulan.{$sekolah->id}.1", 900_000)
        ->where("realisasiPerBulan.{$sekolah->id}.2", 2_700_000)
        ->where("realisasiPerBulan.{$sekolah->id}.3", 0));

    expect($tanpaKuitansi->kuitansi)->toHaveCount(0);
});
