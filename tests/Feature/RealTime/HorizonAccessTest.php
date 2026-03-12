<?php

use App\Models\User;

it('admin can access horizon dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/horizon')
        ->assertSuccessful();
});

it('non-admin users cannot access horizon', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get('/horizon')
        ->assertForbidden();
});
