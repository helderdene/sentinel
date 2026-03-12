<?php

use App\Models\User;

it('shares admin role and permissions via Inertia props', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('auth.user.role', 'admin')
        ->where('auth.user.can.manage_users', true)
        ->where('auth.user.can.manage_incident_types', true)
        ->where('auth.user.can.manage_barangays', true)
        ->where('auth.user.can.create_incidents', true)
        ->where('auth.user.can.dispatch_units', true)
        ->where('auth.user.can.respond_incidents', false)
        ->where('auth.user.can.view_analytics', true)
        ->where('auth.user.can.view_all_incidents', true)
        ->where('auth.user.can.manage_system', true)
    );
});

it('shares dispatcher role and permissions via Inertia props', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $response = $this->actingAs($dispatcher)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('auth.user.role', 'dispatcher')
        ->where('auth.user.can.manage_users', false)
        ->where('auth.user.can.create_incidents', true)
        ->where('auth.user.can.dispatch_units', true)
        ->where('auth.user.can.respond_incidents', false)
        ->where('auth.user.can.view_analytics', false)
        ->where('auth.user.can.view_all_incidents', true)
        ->where('auth.user.can.manage_system', false)
    );
});

it('shares responder role and permissions via Inertia props', function () {
    $responder = User::factory()->responder()->create();

    $response = $this->actingAs($responder)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('auth.user.role', 'responder')
        ->where('auth.user.can.manage_users', false)
        ->where('auth.user.can.create_incidents', false)
        ->where('auth.user.can.dispatch_units', false)
        ->where('auth.user.can.respond_incidents', true)
        ->where('auth.user.can.view_analytics', false)
        ->where('auth.user.can.view_all_incidents', false)
        ->where('auth.user.can.manage_system', false)
    );
});

it('shares supervisor role and permissions via Inertia props', function () {
    $supervisor = User::factory()->supervisor()->create();

    $response = $this->actingAs($supervisor)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('auth.user.role', 'supervisor')
        ->where('auth.user.can.manage_users', false)
        ->where('auth.user.can.create_incidents', true)
        ->where('auth.user.can.dispatch_units', true)
        ->where('auth.user.can.respond_incidents', false)
        ->where('auth.user.can.view_analytics', true)
        ->where('auth.user.can.view_all_incidents', true)
        ->where('auth.user.can.manage_system', false)
    );
});

it('allows all roles to access the dashboard', function () {
    collect(['admin', 'dispatcher', 'responder', 'supervisor'])->each(function (string $role) {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    });
});

it('returns null auth user for guests', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
