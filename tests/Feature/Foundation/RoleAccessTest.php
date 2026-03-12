<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:admin'])->get('/test-admin-only', fn () => 'ok');
    Route::middleware(['web', 'auth', 'role:dispatcher,supervisor'])->get('/test-dispatch', fn () => 'ok');
});

it('blocks unauthenticated users from role-protected routes', function () {
    $this->get('/test-admin-only')->assertRedirect('/login');
});

it('blocks users with wrong role from admin route', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get('/test-admin-only')
        ->assertStatus(403);
});

it('allows admin to access admin route', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/test-admin-only')
        ->assertStatus(200);
});

it('allows dispatcher to access dispatch route', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get('/test-dispatch')
        ->assertStatus(200);
});

it('allows supervisor to access dispatch route', function () {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)
        ->get('/test-dispatch')
        ->assertStatus(200);
});

it('blocks responder from dispatch route', function () {
    $responder = User::factory()->responder()->create();

    $this->actingAs($responder)
        ->get('/test-dispatch')
        ->assertStatus(403);
});

it('enforces manage-users gate for admin only', function () {
    $admin = User::factory()->admin()->create();
    $dispatcher = User::factory()->dispatcher()->create();

    expect(Gate::forUser($admin)->allows('manage-users'))->toBeTrue();
    expect(Gate::forUser($dispatcher)->allows('manage-users'))->toBeFalse();
});

it('enforces create-incidents gate for dispatcher, supervisor, and admin', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $supervisor = User::factory()->supervisor()->create();
    $admin = User::factory()->admin()->create();
    $responder = User::factory()->responder()->create();

    expect(Gate::forUser($dispatcher)->allows('create-incidents'))->toBeTrue();
    expect(Gate::forUser($supervisor)->allows('create-incidents'))->toBeTrue();
    expect(Gate::forUser($admin)->allows('create-incidents'))->toBeTrue();
    expect(Gate::forUser($responder)->allows('create-incidents'))->toBeFalse();
});

it('enforces view-analytics gate for supervisor and admin', function () {
    $supervisor = User::factory()->supervisor()->create();
    $admin = User::factory()->admin()->create();
    $dispatcher = User::factory()->dispatcher()->create();

    expect(Gate::forUser($supervisor)->allows('view-analytics'))->toBeTrue();
    expect(Gate::forUser($admin)->allows('view-analytics'))->toBeTrue();
    expect(Gate::forUser($dispatcher)->allows('view-analytics'))->toBeFalse();
});

it('enforces respond-incidents gate for responder only', function () {
    $responder = User::factory()->responder()->create();
    $dispatcher = User::factory()->dispatcher()->create();

    expect(Gate::forUser($responder)->allows('respond-incidents'))->toBeTrue();
    expect(Gate::forUser($dispatcher)->allows('respond-incidents'))->toBeFalse();
});
