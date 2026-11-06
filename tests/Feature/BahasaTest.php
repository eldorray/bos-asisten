<?php

use App\Models\Kegiatan;
use App\Models\User;

test('pesan validasi bawaan berbahasa indonesia', function () {
    $kegiatan = Kegiatan::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('kegiatan.update', $kegiatan), ['status_manual' => 'tidak_dilaksanakan'])
        ->assertSessionHasErrors(['catatan_status' => 'Catatan wajib diisi.']);
});

test('pesan login gagal berbahasa indonesia', function () {
    $this->post(route('login.store'), ['email' => 'tidak@ada.test', 'password' => 'salah'])
        ->assertSessionHasErrors(['email' => 'Email atau password salah.']);
});
