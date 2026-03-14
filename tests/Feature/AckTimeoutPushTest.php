<?php

use App\Events\AssignmentPushed;
use App\Jobs\CheckAckTimeout;
use App\Listeners\SendAssignmentPushNotification;
use App\Models\Incident;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\AckTimeoutNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

it('sends AckTimeoutNotification when assignment is unacknowledged', function () {
    Notification::fake();

    $dispatcher = User::factory()->dispatcher()->create();
    $user = User::factory()->responder()->create();
    $unit = Unit::factory()->create(['id' => 'AMB-10']);
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

it('does not send notification when assignment is acknowledged', function () {
    Notification::fake();

    $dispatcher = User::factory()->dispatcher()->create();
    $user = User::factory()->responder()->create();
    $unit = Unit::factory()->create(['id' => 'AMB-11']);
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

it('dispatches CheckAckTimeout job with 90-second delay from listener', function () {
    Notification::fake();
    Bus::fake([CheckAckTimeout::class]);

    $user = User::factory()->responder()->create();
    $incident = Incident::factory()->create();

    $event = new AssignmentPushed($incident, 'AMB-12', $user->id);
    $listener = new SendAssignmentPushNotification;
    $listener->handle($event);

    Bus::assertDispatched(CheckAckTimeout::class, function ($job) use ($incident, $user) {
        return $job->incidentId === $incident->id
            && $job->unitId === 'AMB-12'
            && $job->userId === $user->id;
    });
});
