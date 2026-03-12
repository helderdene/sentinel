<?php

use App\Models\User;

it('redirects operator to intake station after login', function () {
    $operator = User::factory()->operator()->create();

    $this->post(route('login'), [
        'email' => $operator->email,
        'password' => 'password',
    ])->assertRedirect(route('intake.station'));
});

it('redirects dispatcher to dashboard after login', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->post(route('login'), [
        'email' => $dispatcher->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
});
