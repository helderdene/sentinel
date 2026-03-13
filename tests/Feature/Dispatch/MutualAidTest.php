<?php

use App\Enums\IncidentStatus;
use App\Events\IncidentCreated;
use App\Events\MutualAidRequested;
use App\Models\Agency;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;

beforeEach(function () {
    Event::fake([
        IncidentCreated::class,
        MutualAidRequested::class,
    ]);
});

it('creates mutual aid request with timeline entry and event', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();
    $agency = Agency::create(['name' => 'BFP Caraga', 'code' => 'BFP']);

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($dispatcher)
        ->postJson(route('dispatch.mutual-aid', $incident), [
            'agency_id' => $agency->id,
            'notes' => 'Need additional fire trucks',
        ])
        ->assertSuccessful();

    // Verify timeline entry
    $this->assertDatabaseHas('incident_timeline', [
        'incident_id' => $incident->id,
        'event_type' => 'mutual_aid_requested',
    ]);

    Event::assertDispatched(MutualAidRequested::class);
});

it('returns agencies with suggested agencies based on incident type', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create(['category' => 'Fire']);

    $bfp = Agency::create(['name' => 'BFP Caraga', 'code' => 'BFP']);
    $bfp->incidentTypes()->attach($type->id);

    $pnp = Agency::create(['name' => 'PNP Butuan', 'code' => 'PNP']);

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($dispatcher)
        ->get(route('dispatch.console'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('agencies', 2)
        );
});
