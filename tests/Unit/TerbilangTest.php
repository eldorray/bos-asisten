<?php

use App\Support\Terbilang;

it('mengeja', fn ($n, $t) => expect(Terbilang::rupiah($n))->toBe($t))->with([
    [0, 'Nol rupiah'],
    [1, 'Satu rupiah'],
    [10, 'Sepuluh rupiah'],
    [12, 'Dua belas rupiah'],
    [1000, 'Seribu rupiah'],
    [1500, 'Seribu lima ratus rupiah'],
    [11, 'Sebelas rupiah'],
    [100, 'Seratus rupiah'],
    [200, 'Dua ratus rupiah'],
    [2000, 'Dua ribu rupiah'],
    [111000, 'Seratus sebelas ribu rupiah'],
    [1001000, 'Satu juta seribu rupiah'],
    [15960000, 'Lima belas juta sembilan ratus enam puluh ribu rupiah'],
    [47880000, 'Empat puluh tujuh juta delapan ratus delapan puluh ribu rupiah'],
    [3600000, 'Tiga juta enam ratus ribu rupiah'],
    [1000000000, 'Satu miliar rupiah'],
    [1000000000000, 'Satu triliun rupiah'],
    [2500000000001, 'Dua triliun lima ratus miliar satu rupiah'],
]);

it('menolak nilai negatif', fn () => Terbilang::rupiah(-1))->throws(InvalidArgumentException::class);
