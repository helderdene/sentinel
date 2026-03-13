<?php

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Unit;
use App\Models\User;

it('allows dispatcher to access incident message channel', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create(['incident_type_id' => $type->id]);

    $this->actingAs($dispatcher)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-incident.{$incident->id}.messages",
        ])
        ->assertSuccessful();
});

it('allows operator to access incident message channel', function () {
    $operator = User::factory()->operator()->create();
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create(['incident_type_id' => $type->id]);

    $this->actingAs($operator)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-incident.{$incident->id}.messages",
        ])
        ->assertSuccessful();
});

it('allows supervisor to access incident message channel', function () {
    $supervisor = User::factory()->supervisor()->create();
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create(['incident_type_id' => $type->id]);

    $this->actingAs($supervisor)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-incident.{$incident->id}.messages",
        ])
        ->assertSuccessful();
});

it('allows admin to access incident message channel', function () {
    $admin = User::factory()->admin()->create();
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create(['incident_type_id' => $type->id]);

    $this->actingAs($admin)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-incident.{$incident->id}.messages",
        ])
        ->assertSuccessful();
});

it('allows responder with assigned unit to access incident message channel', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'incident_type_id' => $type->id,
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-incident.{$incident->id}.messages",
        ])
        ->assertSuccessful();
});

it('denies responder whose unit is not assigned to incident', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($responder)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-incident.{$incident->id}.messages",
        ])
        ->assertForbidden();
});

it('denies unauthenticated user from accessing incident message channel', function () {
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create(['incident_type_id' => $type->id]);

    $this->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-incident.{$incident->id}.messages",
    ])
        ->assertForbidden();
});
