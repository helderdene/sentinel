<?php

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

pest()->group('fras');

/*
 * Five new Phase 22 gates per D-27 / 22-PATTERNS §Wave 1 "AppServiceProvider (MOD)":
 *   view-fras-alerts            → [Operator, Supervisor, Admin]
 *   manage-cameras              → [Supervisor, Admin]
 *   manage-personnel            → [Supervisor, Admin]
 *   trigger-enrollment-retry    → [Supervisor, Admin]
 *   view-recognition-image      → [Operator, Supervisor, Admin]
 *
 * Each dataset below maps every UserRole case to the boolean the gate must
 * return. Datasets are per-gate (not a single shared matrix) because the
 * allowed-role set differs gate-to-gate.
 */

dataset('view_fras_alerts_matrix', [
    'admin' => [UserRole::Admin, true],
    'supervisor' => [UserRole::Supervisor, true],
    'operator' => [UserRole::Operator, true],
    'dispatcher' => [UserRole::Dispatcher, false],
    'responder' => [UserRole::Responder, false],
]);

dataset('manage_cameras_matrix', [
    'admin' => [UserRole::Admin, true],
    'supervisor' => [UserRole::Supervisor, true],
    'operator' => [UserRole::Operator, false],
    'dispatcher' => [UserRole::Dispatcher, false],
    'responder' => [UserRole::Responder, false],
]);

dataset('manage_personnel_matrix', [
    'admin' => [UserRole::Admin, true],
    'supervisor' => [UserRole::Supervisor, true],
    'operator' => [UserRole::Operator, false],
    'dispatcher' => [UserRole::Dispatcher, false],
    'responder' => [UserRole::Responder, false],
]);

dataset('trigger_enrollment_retry_matrix', [
    'admin' => [UserRole::Admin, true],
    'supervisor' => [UserRole::Supervisor, true],
    'operator' => [UserRole::Operator, false],
    'dispatcher' => [UserRole::Dispatcher, false],
    'responder' => [UserRole::Responder, false],
]);

dataset('view_recognition_image_matrix', [
    'admin' => [UserRole::Admin, true],
    'supervisor' => [UserRole::Supervisor, true],
    'operator' => [UserRole::Operator, true],
    'dispatcher' => [UserRole::Dispatcher, false],
    'responder' => [UserRole::Responder, false],
]);

it('enforces view-fras-alerts per role', function (UserRole $role, bool $expected) {
    $user = User::factory()->create(['role' => $role]);
    expect($user->can('view-fras-alerts'))->toBe($expected);
})->with('view_fras_alerts_matrix');

it('enforces manage-cameras per role', function (UserRole $role, bool $expected) {
    $user = User::factory()->create(['role' => $role]);
    expect($user->can('manage-cameras'))->toBe($expected);
})->with('manage_cameras_matrix');

it('enforces manage-personnel per role', function (UserRole $role, bool $expected) {
    $user = User::factory()->create(['role' => $role]);
    expect($user->can('manage-personnel'))->toBe($expected);
})->with('manage_personnel_matrix');

it('enforces trigger-enrollment-retry per role', function (UserRole $role, bool $expected) {
    $user = User::factory()->create(['role' => $role]);
    expect($user->can('trigger-enrollment-retry'))->toBe($expected);
})->with('trigger_enrollment_retry_matrix');

it('enforces view-recognition-image per role', function (UserRole $role, bool $expected) {
    $user = User::factory()->create(['role' => $role]);
    expect($user->can('view-recognition-image'))->toBe($expected);
})->with('view_recognition_image_matrix');

it('shares 5 new can keys in Inertia props', function () {
    $user = User::factory()->create(['role' => UserRole::Operator]);

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['auth']['user']['can'])
        ->toHaveKey('view_fras_alerts', true)
        ->toHaveKey('manage_cameras', false)
        ->toHaveKey('manage_personnel', false)
        ->toHaveKey('trigger_enrollment_retry', false)
        ->toHaveKey('view_recognition_image', true);
});
