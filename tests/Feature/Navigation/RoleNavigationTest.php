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

// Placeholder route access tests

it('allows dispatcher to access dispatch routes', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)->get(route('dispatch.console'))->assertOk();
    $this->actingAs($dispatcher)->get(route('incidents.queue'))->assertOk();
    $this->actingAs($dispatcher)->get(route('incidents.index'))->assertOk();
    $this->actingAs($dispatcher)->get(route('messages.index'))->assertOk();
});

it('blocks responder from dispatch routes', function () {
    $responder = User::factory()->responder()->create();

    $this->actingAs($responder)->get(route('dispatch.console'))->assertStatus(403);
    $this->actingAs($responder)->get(route('incidents.queue'))->assertStatus(403);
    $this->actingAs($responder)->get(route('incidents.index'))->assertStatus(403);
});

it('allows responder to access responder-only routes', function () {
    $responder = User::factory()->responder()->create();

    $this->actingAs($responder)->get(route('assignment.index'))->assertOk();
    $this->actingAs($responder)->get(route('my-incidents.index'))->assertOk();
    $this->actingAs($responder)->get(route('messages.index'))->assertOk();
});

it('blocks dispatcher from responder-only routes', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)->get(route('assignment.index'))->assertStatus(403);
    $this->actingAs($dispatcher)->get(route('my-incidents.index'))->assertStatus(403);
});

it('allows supervisor to access supervisor routes', function () {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)->get(route('dispatch.console'))->assertOk();
    $this->actingAs($supervisor)->get(route('incidents.index'))->assertOk();
    $this->actingAs($supervisor)->get(route('units.index'))->assertOk();
    $this->actingAs($supervisor)->get(route('analytics.index'))->assertOk();
    $this->actingAs($supervisor)->get(route('messages.index'))->assertOk();
});

it('blocks dispatcher from supervisor-only routes', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)->get(route('units.index'))->assertStatus(403);
    $this->actingAs($dispatcher)->get(route('analytics.index'))->assertStatus(403);
});

it('allows admin to access all placeholder routes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('dispatch.console'))->assertOk();
    $this->actingAs($admin)->get(route('incidents.queue'))->assertOk();
    $this->actingAs($admin)->get(route('incidents.index'))->assertOk();
    $this->actingAs($admin)->get(route('messages.index'))->assertOk();
    $this->actingAs($admin)->get(route('units.index'))->assertOk();
    $this->actingAs($admin)->get(route('analytics.index'))->assertOk();
});

it('blocks admin from responder-only routes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('assignment.index'))->assertStatus(403);
    $this->actingAs($admin)->get(route('my-incidents.index'))->assertStatus(403);
});

it('renders dispatch console with correct Inertia component', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('dispatch.console'))
        ->assertInertia(fn ($page) => $page
            ->component('dispatch/Console')
            ->has('incidents')
            ->has('units')
            ->has('agencies')
            ->has('metrics')
        );
});

it('allows all communication roles to access messages', function () {
    collect(['admin', 'dispatcher', 'responder', 'supervisor'])->each(function (string $role) {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)
            ->get(route('messages.index'))
            ->assertOk();
    });
});
