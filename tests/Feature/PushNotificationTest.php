<?php

use App\Enums\IncidentPriority;
use App\Events\AssignmentPushed;
use App\Events\IncidentCreated;
use App\Jobs\CheckAckTimeout;
use App\Listeners\SendAssignmentPushNotification;
use App\Listeners\SendP1PushNotification;
use App\Models\Incident;
use App\Models\User;
use App\Notifications\AssignmentPushedNotification;
use App\Notifications\P1IncidentNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

it('triggers AssignmentPushedNotification on AssignmentPushed event', function () {
    Notification::fake();
    Bus::fake([CheckAckTimeout::class]);

    $user = User::factory()->responder()->create();
    $incident = Incident::factory()->create();

    $event = new AssignmentPushed($incident, 'AMB-01', $user->id);
    $listener = new SendAssignmentPushNotification;
    $listener->handle($event);

    Notification::assertSentTo($user, AssignmentPushedNotification::class);
});

it('sends P1 push notification to dispatchers on IncidentCreated with P1 priority', function () {
    Notification::fake();

    $dispatcher = User::factory()->dispatcher()->create();
    $incident = Incident::factory()->create(['priority' => IncidentPriority::P1]);

    $event = new IncidentCreated($incident);
    $listener = new SendP1PushNotification;
    $listener->handle($event);

    Notification::assertSentTo($dispatcher, P1IncidentNotification::class);
});

it('does not send P1 push notification for P3 priority incidents', function () {
    Notification::fake();

    User::factory()->dispatcher()->create();
    $incident = Incident::factory()->create(['priority' => IncidentPriority::P3]);

    $event = new IncidentCreated($incident);
    $listener = new SendP1PushNotification;
    $listener->handle($event);

    Notification::assertNothingSent();
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

it('does not send P1 push notification for P2 priority incidents', function () {
    Notification::fake();

    User::factory()->dispatcher()->create();
    $incident = Incident::factory()->create(['priority' => IncidentPriority::P2]);

    $event = new IncidentCreated($incident);
    $listener = new SendP1PushNotification;
    $listener->handle($event);

    Notification::assertNothingSent();
});
