<?php

use App\Enums\CameraStatus;
use App\Enums\UserRole;
use App\Models\Camera;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;

pest()->group('fras');

it('DispatchConsoleController::show includes cameras prop with non-decommissioned + coords-having rows only', function () {
    Camera::factory()->create([
        'name' => 'Gate East',
        'status' => CameraStatus::Online,
        'decommissioned_at' => null,
        'location' => Point::makeGeodetic(8.9475, 125.5406),
    ]);
    Camera::factory()->create([
        'name' => 'Gate West',
        'status' => CameraStatus::Offline,
        'decommissioned_at' => null,
        'location' => Point::makeGeodetic(8.9500, 125.5420),
    ]);
    // Decommissioned — excluded
    Camera::factory()->create([
        'decommissioned_at' => now(),
        'location' => Point::makeGeodetic(8.95, 125.55),
    ]);
    // No location — excluded
    Camera::factory()->create([
        'decommissioned_at' => null,
        'location' => null,
    ]);

    $dispatcher = User::factory()->create(['role' => UserRole::Dispatcher]);

    $this->actingAs($dispatcher)
        ->get(route('dispatch.console'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dispatch/Console')
            ->has('cameras', 2)
            ->has('cameras.0.id')
            ->has('cameras.0.camera_id_display')
            ->has('cameras.0.name')
            ->has('cameras.0.status')
            ->has('cameras.0.coordinates.lat')
            ->has('cameras.0.coordinates.lng')
        );
});

it('cameras prop exposes enum value strings (not enum instances) for JSON safety', function () {
    Camera::factory()->create([
        'status' => CameraStatus::Online,
        'decommissioned_at' => null,
        'location' => Point::makeGeodetic(8.9475, 125.5406),
    ]);

    $dispatcher = User::factory()->create(['role' => UserRole::Dispatcher]);

    $this->actingAs($dispatcher)
        ->get(route('dispatch.console'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('cameras.0.status', 'online')
        );
});

it('cameras prop is an empty array when no active cameras exist', function () {
    $dispatcher = User::factory()->create(['role' => UserRole::Dispatcher]);

    $this->actingAs($dispatcher)
        ->get(route('dispatch.console'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('cameras', 0)
        );
});
