<?php

use App\Enums\IncidentStatus;
use App\Events\IncidentCreated;
use App\Events\IncidentStatusChanged;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;

beforeEach(function () {
    Event::fake([
        IncidentCreated::class,
        IncidentStatusChanged::class,
    ]);
});

it('advances from DISPATCHED to EN_ROUTE', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($dispatcher)
        ->postJson(route('dispatch.advance-status', $incident), [
            'status' => 'EN_ROUTE',
        ])
        ->assertSuccessful();

    expect($incident->fresh()->status)->toBe(IncidentStatus::EnRoute);
    expect($incident->fresh()->en_route_at)->not->toBeNull();

    Event::assertDispatched(IncidentStatusChanged::class);
});

it('advances from EN_ROUTE to ON_SCENE', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::EnRoute,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($dispatcher)
        ->postJson(route('dispatch.advance-status', $incident), [
            'status' => 'ON_SCENE',
        ])
        ->assertSuccessful();

    expect($incident->fresh()->status)->toBe(IncidentStatus::OnScene);
    expect($incident->fresh()->on_scene_at)->not->toBeNull();
});

it('advances from ON_SCENE to RESOLVED', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::OnScene,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($dispatcher)
        ->postJson(route('dispatch.advance-status', $incident), [
            'status' => 'RESOLVED',
        ])
        ->assertSuccessful();

    expect($incident->fresh()->status)->toBe(IncidentStatus::Resolved);
    expect($incident->fresh()->resolved_at)->not->toBeNull();
});

it('rejects backward status transition', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::OnScene,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($dispatcher)
        ->postJson(route('dispatch.advance-status', $incident), [
            'status' => 'DISPATCHED',
        ])
        ->assertUnprocessable();
});

it('rejects advancing PENDING status from dispatch', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Pending,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($dispatcher)
        ->postJson(route('dispatch.advance-status', $incident), [
            'status' => 'DISPATCHED',
        ])
        ->assertUnprocessable();
});

it('rejects advancing TRIAGED status from dispatch', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Triaged,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($dispatcher)
        ->postJson(route('dispatch.advance-status', $incident), [
            'status' => 'DISPATCHED',
        ])
        ->assertUnprocessable();
});
