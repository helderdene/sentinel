<?php

use App\Models\Camera;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

pest()->group('mqtt');

beforeEach(function () {
    Cache::forget('mqtt:listener:last_known_state');
    Cache::forget('mqtt:listener:last_message_received_at');
    Cache::forget('mqtt:listener:last_state_since');
});

it('exposes mqtt_listener_health as an Inertia shared prop on the dispatch console', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get(route('dispatch.console'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('mqtt_listener_health', fn (Assert $health) => $health
                ->has('status')
                ->has('last_message_received_at')
                ->has('since')
                ->has('active_camera_count')
            )
        );
});

it('defaults mqtt_listener_health.status to NO_ACTIVE_CAMERAS when cache is empty', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get(route('dispatch.console'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mqtt_listener_health.status', 'NO_ACTIVE_CAMERAS')
        );
});

it('reflects SILENT status when the listener state cache key is set to SILENT', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    Cache::put('mqtt:listener:last_known_state', 'SILENT');

    $this->actingAs($dispatcher)
        ->get(route('dispatch.console'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mqtt_listener_health.status', 'SILENT')
        );
});

it('reports active_camera_count matching Camera::active()->count()', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    Camera::factory()->count(3)->create();
    Camera::factory()->create(['decommissioned_at' => now()->subDay()]);

    $this->actingAs($dispatcher)
        ->get(route('dispatch.console'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mqtt_listener_health.active_camera_count', 3)
        );
});
