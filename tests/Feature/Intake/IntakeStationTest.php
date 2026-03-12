<?php

use App\Events\IncidentCreated;
use App\Models\User;

beforeEach(function () {
    Event::fake([IncidentCreated::class]);
});

it('renders intake station for operator', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->get(route('intake.station'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('intake/IntakeStation'));
});

it('renders intake station for supervisor', function () {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)
        ->get(route('intake.station'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('intake/IntakeStation'));
});

it('renders intake station for admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('intake.station'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('intake/IntakeStation'));
});

it('returns 403 for dispatcher', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get(route('intake.station'))
        ->assertForbidden();
});

it('returns 403 for responder', function () {
    $responder = User::factory()->responder()->create();

    $this->actingAs($responder)
        ->get(route('intake.station'))
        ->assertForbidden();
});

it('redirects unauthenticated to login', function () {
    $this->get(route('intake.station'))
        ->assertRedirect(route('login'));
});

it('receives required props', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->get(route('intake.station'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('intake/IntakeStation')
            ->has('incidentTypes')
            ->has('channels')
            ->has('priorities')
            ->has('pendingIncidents')
            ->has('triagedIncidents')
        );
});
