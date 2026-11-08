<?php

declare(strict_types=1);

use App\Models\RkasPengaturan;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\DataAwalSeeder;
use Inertia\Testing\AssertableInertia as Assert;

function dataPengaturan(array $ubah = []): array
{
    $data = RkasPengaturan::nilaiAwal();
    foreach (['kode_honor', 'kode_buku', 'kode_sarpras', 'rekening_sarpras_lain'] as $kunci) {
        $data[$kunci] = implode(', ', $data[$kunci]);
    }

    return array_replace($data, $ubah);
}

beforeEach(function () {
    $this->seed(DataAwalSeeder::class);
    $this->smp = Sekolah::where('nama', 'like', '%Garuda%')->firstOrFail();
    $this->actingAs(User::factory()->create());
});

it('menampilkan pengaturan 2027 SMP dengan nilai awal', function () {
    $this->get(route('rkas.pengaturan.edit', ['tahun' => 2027]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pengaturan/Rkas')
            ->where('tahun', 2027)
            ->where('sekolah.id', $this->smp->id)
            ->where('pengaturan.persen_tahap_1', 50)
            ->where('pengaturan.kode_honor', ['07.12.01.', '07.12.02.', '07.12.03.', '07.12.04.'])
            ->missing('pengaturan.created_at'));

    expect(RkasPengaturan::where('sekolah_id', $this->smp->id)->where('tahun', 2027)->exists())->toBeTrue();
});

it('menyimpan status swasta dan persentase tahap 40', function () {
    $pengaturan = RkasPengaturan::untuk($this->smp, 2027);

    $this->patch(route('rkas.pengaturan.update', $pengaturan), dataPengaturan([
        'swasta' => true,
        'persen_tahap_1' => 40,
        'kode_buku' => '03.02.02., 05.02.02.',
        'kuota_perubahan' => 2,
    ]))->assertRedirect();

    $pengaturan->refresh();
    expect($pengaturan->swasta)->toBeTrue()
        ->and($pengaturan->persen_tahap_1)->toBe(40)
        ->and($pengaturan->kode_buku)->toBe(['03.02.02.', '05.02.02.'])
        ->and($pengaturan->kuota_perubahan)->toBe(2)
        ->and($pengaturan->kuota_pergeseran)->toBeNull();
});

it('menolak persen_tahap_1 di luar 1 sampai 99 dan kode kegiatan yang bukan format bertitik', function () {
    $pengaturan = RkasPengaturan::untuk($this->smp, 2027);

    $this->patch(route('rkas.pengaturan.update', $pengaturan), dataPengaturan([
        'persen_tahap_1' => 100,
        'ambang_tahap_1' => 101,
        'sarpras_maks' => -1,
        'kode_honor' => '07.12.01, abc',
        'rekening_honor' => '5.1.02',
        'rekening_sarpras_lain' => '5.2.02',
        'kuota_pergeseran' => -3,
    ]))->assertSessionHasErrors([
        'persen_tahap_1', 'ambang_tahap_1', 'sarpras_maks', 'kode_honor.0', 'kode_honor.1',
        'rekening_honor', 'rekening_sarpras_lain.0', 'kuota_pergeseran',
    ]);

    $this->patch(route('rkas.pengaturan.update', $pengaturan), dataPengaturan(['persen_tahap_1' => 0]))
        ->assertSessionHasErrors('persen_tahap_1');

    expect($pengaturan->refresh()->persen_tahap_1)->toBe(50);
});

it('menolak pengaturan milik sekolah yang bukan ARKAS', function () {
    $mi = Sekolah::factory()->erkam()->create();
    $pengaturan = RkasPengaturan::factory()->create(['sekolah_id' => $mi->id]);

    $this->patch(route('rkas.pengaturan.update', $pengaturan), dataPengaturan())->assertNotFound();
});

it('menolak tamu', function () {
    auth()->logout();
    $pengaturan = RkasPengaturan::untuk($this->smp, 2027);

    $this->get(route('rkas.pengaturan.edit'))->assertRedirect('/login');
    $this->patch(route('rkas.pengaturan.update', $pengaturan), dataPengaturan())->assertRedirect('/login');
});
