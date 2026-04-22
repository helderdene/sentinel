<?php

use App\Enums\IncidentChannel;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\PersonnelCategory;
use App\Enums\RecognitionSeverity;
use App\Enums\UserRole;
use App\Events\RecognitionAlertReceived;
use App\Models\Camera;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Personnel;
use App\Models\RecognitionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

pest()->group('fras');

/**
 * Issue a POST /broadcasting/auth attempt as a user of the given role against
 * the given channel name. Mirrors BroadcastAuthorizationTest helper.
 */
function frasAlertsAuthAttempt(UserRole $role, string $channelName): TestResponse
{
    $user = User::factory()->create(['role' => $role]);

    return test()
        ->actingAs($user)
        ->post('/broadcasting/auth', [
            'channel_name' => $channelName,
            'socket_id' => '1234.5678',
        ]);
}

it('broadcasts on private fras.alerts channel with full denorm payload', function () {
    $camera = Camera::factory()->create(['camera_id_display' => 'CAM-42']);
    $personnel = Personnel::factory()->create([
        'name' => 'Juan dela Cruz',
        'category' => PersonnelCategory::Block,
    ]);
    $event = RecognitionEvent::factory()
        ->for($camera)
        ->for($personnel)
        ->create([
            'severity' => RecognitionSeverity::Critical,
            'similarity' => 0.85,
        ]);

    $broadcast = new RecognitionAlertReceived($event, null);

    $channels = $broadcast->broadcastOn();
    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe('private-fras.alerts');

    $payload = $broadcast->broadcastWith();
    expect($payload)->toHaveKeys([
        'event_id',
        'camera_id',
        'camera_id_display',
        'camera_location',
        'severity',
        'personnel_id',
        'personnel_name',
        'personnel_category',
        'confidence',
        'captured_at',
        'incident_id',
    ]);

    expect($payload['event_id'])->toBe($event->id);
    expect($payload['camera_id'])->toBe($camera->id);
    expect($payload['camera_id_display'])->toBe('CAM-42');
    expect($payload['camera_location'])->toBeArray();
    expect($payload['camera_location'])->toHaveCount(2); // [lng, lat]
    expect($payload['severity'])->toBe('critical');
    expect($payload['personnel_id'])->toBe($personnel->id);
    expect($payload['personnel_name'])->toBe('Juan dela Cruz');
    expect($payload['personnel_category'])->toBe('block');
    expect($payload['confidence'])->toEqual($event->similarity);
    expect($payload['captured_at'])->toBeString();
    expect($payload['incident_id'])->toBeNull();
});

it('broadcasts with incident_id populated when incident passed', function () {
    $camera = Camera::factory()->create();
    $personnel = Personnel::factory()->create(['category' => PersonnelCategory::Block]);
    $event = RecognitionEvent::factory()
        ->for($camera)
        ->for($personnel)
        ->create([
            'severity' => RecognitionSeverity::Critical,
            'similarity' => 0.90,
        ]);

    $incidentType = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'incident_type_id' => $incidentType->id,
        'channel' => IncidentChannel::IoT,
        'priority' => IncidentPriority::P2,
        'status' => IncidentStatus::Pending,
    ]);

    $broadcast = new RecognitionAlertReceived($event, $incident);
    $payload = $broadcast->broadcastWith();

    expect($payload['incident_id'])->toBe($incident->id);
});

describe('fras.alerts channel auth', function () {
    foreach ([UserRole::Operator, UserRole::Dispatcher, UserRole::Supervisor, UserRole::Admin] as $allowedRole) {
        it("authorizes {$allowedRole->value} to subscribe to private-fras.alerts", function () use ($allowedRole) {
            $response = frasAlertsAuthAttempt($allowedRole, 'private-fras.alerts');
            expect($response->getStatusCode())->toBeIn([200, 201]);
        });
    }

    it('denies responder subscription to private-fras.alerts', function () {
        $response = frasAlertsAuthAttempt(UserRole::Responder, 'private-fras.alerts');
        expect($response->getStatusCode())->toBe(403);
    });
});
