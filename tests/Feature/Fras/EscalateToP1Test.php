<?php

use App\Enums\IncidentChannel;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\UserRole;
use App\Events\IncidentStatusChanged;
use App\Models\Incident;
use App\Models\IncidentTimeline;
use App\Models\IncidentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

pest()->group('fras');

beforeEach(function () {
    Event::fake([IncidentStatusChanged::class]);
});

/**
 * Create a FRAS-originated Incident + its creation timeline entry for use by
 * the escalate tests.
 */
function frasIncident(string $priority = 'P2'): Incident
{
    $type = IncidentType::factory()->create(['code' => 'person_of_interest']);
    $incident = Incident::factory()->create([
        'incident_type_id' => $type->id,
        'channel' => IncidentChannel::IoT,
        'priority' => IncidentPriority::from($priority),
        'status' => IncidentStatus::Pending,
    ]);

    IncidentTimeline::create([
        'incident_id' => $incident->id,
        'event_type' => 'incident_created',
        'event_data' => [
            'source' => 'fras_recognition',
            'recognition_event_id' => fake()->uuid(),
        ],
    ]);

    return $incident;
}

it('escalates FRAS-originated incident to P1 with fras_escalate_button trigger', function () {
    $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
    $incident = frasIncident('P2');

    $this->actingAs($supervisor)
        ->post(route('intake.override-priority', $incident), [
            'priority' => 'P1',
            'trigger' => 'fras_escalate_button',
        ])
        ->assertRedirect();

    expect($incident->fresh()->priority)->toBe(IncidentPriority::P1);

    $override = $incident->timeline()
        ->where('event_type', 'priority_override')
        ->first();
    expect($override)->not->toBeNull();
    expect($override->event_data['trigger'] ?? null)->toBe('fras_escalate_button');
});

it('defaults event_data.trigger to manual_override when trigger field absent', function () {
    $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
    $incident = frasIncident('P2');

    $this->actingAs($supervisor)
        ->post(route('intake.override-priority', $incident), [
            'priority' => 'P1',
        ]);

    $override = $incident->timeline()
        ->where('event_type', 'priority_override')
        ->first();
    expect($override)->not->toBeNull();
    expect($override->event_data['trigger'] ?? null)->toBe('manual_override');
});

it('rejects invalid trigger values with 422', function () {
    $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
    $incident = frasIncident('P2');

    $this->actingAs($supervisor)
        ->postJson(route('intake.override-priority', $incident), [
            'priority' => 'P1',
            'trigger' => 'hacker_value',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['trigger']);
});

it('denies dispatcher on override-priority route', function () {
    $dispatcher = User::factory()->create(['role' => UserRole::Dispatcher]);
    $incident = frasIncident('P2');

    $this->actingAs($dispatcher)
        ->post(route('intake.override-priority', $incident), [
            'priority' => 'P1',
            'trigger' => 'fras_escalate_button',
        ])
        ->assertForbidden();
});
