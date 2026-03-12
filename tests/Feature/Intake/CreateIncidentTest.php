<?php

use App\Enums\IncidentChannel;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;

it('allows dispatcher to create incident with all required fields', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create(['default_priority' => 'P2']);

    $this->actingAs($dispatcher)
        ->post(route('incidents.store'), [
            'incident_type_id' => $type->id,
            'priority' => 'P2',
            'channel' => 'phone',
            'location_text' => 'J.C. Aquino Ave, Butuan City',
            'caller_name' => 'Juan Dela Cruz',
            'caller_contact' => '09171234567',
            'notes' => 'Smoke visible from third floor',
        ])
        ->assertRedirect(route('incidents.queue'));

    $this->assertDatabaseHas('incidents', [
        'incident_type_id' => $type->id,
        'priority' => 'P2',
        'channel' => 'phone',
        'location_text' => 'J.C. Aquino Ave, Butuan City',
        'caller_name' => 'Juan Dela Cruz',
    ]);
});

it('auto-generates incident number on creation', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $this->actingAs($dispatcher)
        ->post(route('incidents.store'), [
            'incident_type_id' => $type->id,
            'priority' => 'P3',
            'channel' => 'phone',
            'location_text' => 'Some location',
        ]);

    $incident = Incident::first();
    expect($incident->incident_no)->toMatch('/^INC-\d{4}-\d{5}$/');
});

it('sets created_by to authenticated user', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $this->actingAs($dispatcher)
        ->post(route('incidents.store'), [
            'incident_type_id' => $type->id,
            'priority' => 'P2',
            'channel' => 'sms',
            'location_text' => 'Some location',
        ]);

    $incident = Incident::first();
    expect($incident->created_by)->toBe($dispatcher->id);
});

it('validates required fields on create', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->post(route('incidents.store'), [])
        ->assertSessionHasErrors(['incident_type_id', 'priority', 'channel', 'location_text']);
});

it('validates channel against enum values', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $this->actingAs($dispatcher)
        ->post(route('incidents.store'), [
            'incident_type_id' => $type->id,
            'priority' => 'P2',
            'channel' => 'invalid_channel',
            'location_text' => 'Some location',
        ])
        ->assertSessionHasErrors('channel');
});

it('rejects non-dispatcher creating incidents', function () {
    $responder = User::factory()->responder()->create();
    $type = IncidentType::factory()->create();

    $this->actingAs($responder)
        ->post(route('incidents.store'), [
            'incident_type_id' => $type->id,
            'priority' => 'P2',
            'channel' => 'phone',
            'location_text' => 'Some location',
        ])
        ->assertForbidden();
});

it('allows admin to create incidents', function () {
    $admin = User::factory()->admin()->create();
    $type = IncidentType::factory()->create();

    $this->actingAs($admin)
        ->post(route('incidents.store'), [
            'incident_type_id' => $type->id,
            'priority' => 'P1',
            'channel' => 'radio',
            'location_text' => 'City Hall',
        ])
        ->assertRedirect(route('incidents.queue'));
});

it('allows supervisor to create incidents', function () {
    $supervisor = User::factory()->supervisor()->create();
    $type = IncidentType::factory()->create();

    $this->actingAs($supervisor)
        ->post(route('incidents.store'), [
            'incident_type_id' => $type->id,
            'priority' => 'P3',
            'channel' => 'app',
            'location_text' => 'Some location',
        ])
        ->assertRedirect(route('incidents.queue'));
});

it('renders create form with required props', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    IncidentType::factory()->count(3)->create();

    $this->actingAs($dispatcher)
        ->get(route('incidents.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('incidents/Create')
            ->has('incidentTypes')
            ->has('channels')
            ->has('priorities')
            ->has('priorityConfig')
        );
});

it('stores coordinates when latitude and longitude provided', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $this->actingAs($dispatcher)
        ->post(route('incidents.store'), [
            'incident_type_id' => $type->id,
            'priority' => 'P2',
            'channel' => 'phone',
            'location_text' => 'Butuan City',
            'latitude' => 8.9475,
            'longitude' => 125.5406,
        ])
        ->assertRedirect(route('incidents.queue'));

    $incident = Incident::first();
    expect($incident->coordinates)->not->toBeNull();
});
