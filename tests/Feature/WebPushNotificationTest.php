<?php

use App\Enums\IncidentPriority;
use App\Events\AssignmentPushed;
use App\Events\IncidentCreated;
use App\Jobs\CheckAckTimeout;
use App\Listeners\SendAssignmentPushNotification;
use App\Listeners\SendP1PushNotification;
use App\Models\Incident;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\AckTimeoutNotification;
use App\Notifications\AssignmentPushedNotification;
use App\Notifications\P1IncidentNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

it('stores a push subscription for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('push-subscriptions.store'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
            'public_key' => 'test-public-key',
            'auth_token' => 'test-auth-token',
            'content_encoding' => 'aesgcm',
        ])
        ->assertCreated()
        ->assertJson(['message' => 'Subscription saved.']);

    $this->assertDatabaseHas('push_subscriptions', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
    ]);
});

it('deletes a push subscription for authenticated user', function () {
    $user = User::factory()->create();
    $endpoint = 'https://fcm.googleapis.com/fcm/send/test-endpoint';

    $user->updatePushSubscription($endpoint, 'key', 'token', 'aesgcm');

    $this->actingAs($user)
        ->deleteJson(route('push-subscriptions.destroy'), [
            'endpoint' => $endpoint,
        ])
        ->assertNoContent();

    $this->assertDatabaseMissing('push_subscriptions', [
        'endpoint' => $endpoint,
    ]);
});

it('rejects push subscription store without authentication', function () {
    $this->postJson(route('push-subscriptions.store'), [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
        'public_key' => 'key',
        'auth_token' => 'token',
        'content_encoding' => 'aesgcm',
    ])
        ->assertUnauthorized();
});

it('validates endpoint is required for store', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('push-subscriptions.store'), [
            'public_key' => 'key',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['endpoint']);
});

it('sends assignment push notification on AssignmentPushed event', function () {
    Notification::fake();
    Bus::fake([CheckAckTimeout::class]);

    $user = User::factory()->responder()->create();
    $incident = Incident::factory()->create();

    $event = new AssignmentPushed($incident, 'AMB-01', $user->id);
    $listener = new SendAssignmentPushNotification;
    $listener->handle($event);

    Notification::assertSentTo($user, AssignmentPushedNotification::class);
});

it('dispatches CheckAckTimeout job with delay on assignment push', function () {
    Notification::fake();
    Bus::fake([CheckAckTimeout::class]);

    $user = User::factory()->responder()->create();
    $incident = Incident::factory()->create();

    $event = new AssignmentPushed($incident, 'AMB-01', $user->id);
    $listener = new SendAssignmentPushNotification;
    $listener->handle($event);

    Bus::assertDispatched(CheckAckTimeout::class, function ($job) use ($incident, $user) {
        return $job->incidentId === $incident->id
            && $job->unitId === 'AMB-01'
            && $job->userId === $user->id;
    });
});

it('sends P1 push notification to dispatchers, operators, and supervisors', function () {
    Notification::fake();

    $dispatcher = User::factory()->dispatcher()->create();
    $operator = User::factory()->operator()->create();
    $supervisor = User::factory()->supervisor()->create();
    $responder = User::factory()->responder()->create();

    $incident = Incident::factory()->create(['priority' => IncidentPriority::P1]);

    $event = new IncidentCreated($incident);
    $listener = new SendP1PushNotification;
    $listener->handle($event);

    Notification::assertSentTo($dispatcher, P1IncidentNotification::class);
    Notification::assertSentTo($operator, P1IncidentNotification::class);
    Notification::assertSentTo($supervisor, P1IncidentNotification::class);
    Notification::assertNotSentTo($responder, P1IncidentNotification::class);
});

it('does not send push notification for non-P1 incidents', function () {
    Notification::fake();

    User::factory()->dispatcher()->create();
    $incident = Incident::factory()->create(['priority' => IncidentPriority::P3]);

    $event = new IncidentCreated($incident);
    $listener = new SendP1PushNotification;
    $listener->handle($event);

    Notification::assertNothingSent();
});

it('sends ack timeout notification when assignment is unacknowledged', function () {
    Notification::fake();

    $dispatcher = User::factory()->dispatcher()->create();
    $user = User::factory()->responder()->create();
    $unit = Unit::factory()->create(['id' => 'AMB-01']);
    $incident = Incident::factory()->create();

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'acknowledged_at' => null,
        'assigned_by' => $dispatcher->id,
    ]);

    $job = new CheckAckTimeout($incident->id, $unit->id, $user->id);
    $job->handle();

    Notification::assertSentTo($user, AckTimeoutNotification::class);
});

it('does not send ack timeout notification when assignment is acknowledged', function () {
    Notification::fake();

    $dispatcher = User::factory()->dispatcher()->create();
    $user = User::factory()->responder()->create();
    $unit = Unit::factory()->create(['id' => 'AMB-02']);
    $incident = Incident::factory()->create();

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'acknowledged_at' => now(),
        'assigned_by' => $dispatcher->id,
    ]);

    $job = new CheckAckTimeout($incident->id, $unit->id, $user->id);
    $job->handle();

    Notification::assertNothingSent();
});
