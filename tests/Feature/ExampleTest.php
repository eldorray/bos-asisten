<?php

test('beranda mengarah ke dashboard', function () {
    $this->get(route('home'))->assertRedirect(route('dashboard'));
});
