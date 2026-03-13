<?php

use App\Models\User;

it('allows supervisor to access analytics dashboard', function () {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)
        ->get(route('analytics.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('analytics/Dashboard'));
});

it('allows admin to access analytics dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('analytics.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('analytics/Dashboard'));
});

it('returns 403 for dispatcher on analytics dashboard', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get(route('analytics.dashboard'))
        ->assertForbidden();
});

it('returns 403 for operator on analytics dashboard', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->get(route('analytics.dashboard'))
        ->assertForbidden();
});

it('returns 403 for responder on analytics dashboard', function () {
    $responder = User::factory()->responder()->create();

    $this->actingAs($responder)
        ->get(route('analytics.dashboard'))
        ->assertForbidden();
});

it('redirects unauthenticated user to login', function () {
    $this->get(route('analytics.dashboard'))
        ->assertRedirect(route('login'));
});
