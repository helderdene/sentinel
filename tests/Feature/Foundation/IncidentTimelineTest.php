<?php

use App\Models\Incident;
use App\Models\IncidentTimeline;
use App\Models\User;

it('belongs to an incident', function () {
    $incident = Incident::factory()->create();
    $timeline = IncidentTimeline::factory()->create(['incident_id' => $incident->id]);

    expect($timeline->incident)->toBeInstanceOf(Incident::class);
    expect($timeline->incident->id)->toBe($incident->id);
});

it('stores event_type and event_data JSONB', function () {
    $eventData = ['old_status' => 'PENDING', 'new_status' => 'DISPATCHED'];
    $timeline = IncidentTimeline::factory()->create([
        'event_type' => 'status_change',
        'event_data' => $eventData,
    ]);

    $fresh = IncidentTimeline::find($timeline->id);
    expect($fresh->event_type)->toBe('status_change');
    expect($fresh->event_data)->toEqual($eventData);
});

it('morphs to actor', function () {
    $user = User::factory()->dispatcher()->create();
    $timeline = IncidentTimeline::factory()->create([
        'actor_type' => User::class,
        'actor_id' => $user->id,
    ]);

    expect($timeline->actor)->toBeInstanceOf(User::class);
    expect($timeline->actor->id)->toBe($user->id);
});

it('appends multiple entries to an incident timeline', function () {
    $incident = Incident::factory()->create();

    IncidentTimeline::factory()->create([
        'incident_id' => $incident->id,
        'event_type' => 'created',
    ]);
    IncidentTimeline::factory()->create([
        'incident_id' => $incident->id,
        'event_type' => 'dispatched',
    ]);
    IncidentTimeline::factory()->create([
        'incident_id' => $incident->id,
        'event_type' => 'acknowledged',
    ]);

    expect($incident->refresh()->timeline)->toHaveCount(3);
});
