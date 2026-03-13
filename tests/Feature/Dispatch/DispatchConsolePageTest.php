<?php

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Events\IncidentCreated;
use App\Models\Agency;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    Event::fake([IncidentCreated::class]);
});

it('renders dispatch console for dispatcher', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get(route('dispatch.console'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dispatch/Console')
            ->has('incidents')
            ->has('units')
            ->has('agencies')
            ->has('metrics')
        );
});

it('returns 403 for responder', function () {
    $responder = User::factory()->responder()->create();

    $this->actingAs($responder)
        ->get(route('dispatch.console'))
        ->assertForbidden();
});

it('returns incidents with correct statuses only', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    // These should appear
    Incident::factory()->create(['status' => IncidentStatus::Triaged, 'incident_type_id' => $type->id]);
    Incident::factory()->create(['status' => IncidentStatus::Dispatched, 'incident_type_id' => $type->id]);
    Incident::factory()->create(['status' => IncidentStatus::EnRoute, 'incident_type_id' => $type->id]);
    Incident::factory()->create(['status' => IncidentStatus::OnScene, 'incident_type_id' => $type->id]);

    // These should NOT appear
    Incident::factory()->create(['status' => IncidentStatus::Pending, 'incident_type_id' => $type->id]);
    Incident::factory()->create(['status' => IncidentStatus::Resolved, 'incident_type_id' => $type->id]);

    $this->actingAs($dispatcher)
        ->get(route('dispatch.console'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('incidents', 4)
        );
});

it('returns metrics with averageHandleTime null when no resolved incidents today', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get(route('dispatch.console'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('metrics')
            ->where('metrics.averageHandleTime', null)
        );
});

it('returns metrics with computed averageHandleTime when resolved incidents exist today', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    // Create a resolved incident with known timestamps (60 min handle time)
    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => IncidentStatus::Resolved,
        'created_at' => now()->subMinutes(60),
        'resolved_at' => now(),
    ]);

    $this->actingAs($dispatcher)
        ->get(route('dispatch.console'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('metrics')
            ->where('metrics.averageHandleTime', fn ($value) => $value !== null && abs($value - 60.0) < 1)
        );
});

it('returns agencies in metrics', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    Agency::create(['name' => 'Test Agency', 'code' => 'TEST']);

    $this->actingAs($dispatcher)
        ->get(route('dispatch.console'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('agencies', 1)
        );
});
