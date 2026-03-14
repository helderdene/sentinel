<?php

use App\Enums\IncidentStatus;
use App\Enums\UnitStatus;
use App\Events\AssignmentPushed;
use App\Events\IncidentCreated;
use App\Events\IncidentStatusChanged;
use App\Events\MessageSent;
use App\Events\UnitLocationUpdated;
use App\Events\UnitStatusChanged;
use App\Models\Incident;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

it('IncidentCreated broadcasts on private-dispatch.incidents channel', function () {
    $incident = Incident::factory()->for(User::factory(), 'createdBy')->create();
    $incident->load('incidentType', 'barangay');

    $event = new IncidentCreated($incident);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-dispatch.incidents');
});

it('IncidentCreated broadcastWith returns correct payload keys', function () {
    $incident = Incident::factory()->for(User::factory(), 'createdBy')->create();
    $incident->load('incidentType', 'barangay');

    $event = new IncidentCreated($incident);
    $payload = $event->broadcastWith();

    expect($payload)->toHaveKeys([
        'id', 'incident_no', 'priority', 'status',
        'incident_type', 'location_text', 'barangay', 'channel', 'created_at',
    ]);
    expect($payload['id'])->toBe($incident->id);
    expect($payload['priority'])->toBe($incident->priority->value);
    expect($payload['status'])->toBe($incident->status->value);
});

it('IncidentStatusChanged broadcasts on private-dispatch.incidents', function () {
    $incident = Incident::factory()->for(User::factory(), 'createdBy')->create([
        'status' => IncidentStatus::Dispatched,
    ]);

    $event = new IncidentStatusChanged($incident, IncidentStatus::Pending);
    $channels = $event->broadcastOn();

    expect($channels[0]->name)->toBe('private-dispatch.incidents');
});

it('IncidentStatusChanged broadcastWith returns old_status and new_status', function () {
    $incident = Incident::factory()->for(User::factory(), 'createdBy')->create([
        'status' => IncidentStatus::Dispatched,
    ]);

    $event = new IncidentStatusChanged($incident, IncidentStatus::Pending);
    $payload = $event->broadcastWith();

    expect($payload['old_status'])->toBe('PENDING');
    expect($payload['new_status'])->toBe('DISPATCHED');
});

it('UnitLocationUpdated broadcasts on private-dispatch.units', function () {
    $unit = Unit::factory()->create();

    $event = new UnitLocationUpdated($unit);
    $channels = $event->broadcastOn();

    expect($channels[0]->name)->toBe('private-dispatch.units');
});

it('UnitLocationUpdated broadcastWith returns latitude and longitude', function () {
    $unit = Unit::factory()->create();

    $event = new UnitLocationUpdated($unit);
    $payload = $event->broadcastWith();

    expect($payload)->toHaveKeys(['id', 'callsign', 'latitude', 'longitude', 'updated_at']);
    expect($payload['latitude'])->toBeFloat();
    expect($payload['longitude'])->toBeFloat();
});

it('UnitStatusChanged broadcasts on private-dispatch.units', function () {
    $unit = Unit::factory()->create(['status' => UnitStatus::Dispatched]);

    $event = new UnitStatusChanged($unit, UnitStatus::Available);
    $channels = $event->broadcastOn();

    expect($channels[0]->name)->toBe('private-dispatch.units');
});

it('AssignmentPushed broadcasts on private-user.{userId} channel', function () {
    $incident = Incident::factory()->for(User::factory(), 'createdBy')->create();
    $userId = 42;

    $event = new AssignmentPushed($incident, 'AMB-01', $userId);
    $channels = $event->broadcastOn();

    expect($channels[0]->name)->toBe('private-user.42');
});

it('MessageSent broadcasts on incident and dispatch channels', function () {
    $event = new MessageSent('INC-2026-00001', 1, 'Admin', 'dispatcher', null, 'Proceed to location', false, 1);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(2);
    expect($channels[0]->name)->toBe('private-incident.INC-2026-00001.messages');
    expect($channels[1]->name)->toBe('private-dispatch.incidents');
});

it('all events implement ShouldBroadcast and ShouldDispatchAfterCommit', function (string $eventClass) {
    $interfaces = class_implements($eventClass);

    expect($interfaces)->toHaveKey(ShouldBroadcast::class);
    expect($interfaces)->toHaveKey(ShouldDispatchAfterCommit::class);
})->with([
    IncidentCreated::class,
    IncidentStatusChanged::class,
    UnitLocationUpdated::class,
    UnitStatusChanged::class,
    AssignmentPushed::class,
    MessageSent::class,
]);
