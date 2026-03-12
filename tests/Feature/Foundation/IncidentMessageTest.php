<?php

use App\Models\Incident;
use App\Models\IncidentMessage;
use App\Models\User;
use Carbon\CarbonImmutable;

it('belongs to an incident', function () {
    $incident = Incident::factory()->create();
    $message = IncidentMessage::factory()->create(['incident_id' => $incident->id]);

    expect($message->incident)->toBeInstanceOf(Incident::class);
    expect($message->incident->id)->toBe($incident->id);
});

it('morphs to sender', function () {
    $user = User::factory()->dispatcher()->create();
    $message = IncidentMessage::factory()->create([
        'sender_type' => User::class,
        'sender_id' => $user->id,
    ]);

    expect($message->sender)->toBeInstanceOf(User::class);
    expect($message->sender->id)->toBe($user->id);
});

it('casts read_at as datetime', function () {
    $message = IncidentMessage::factory()->create(['read_at' => now()]);

    $fresh = IncidentMessage::find($message->id);
    expect($fresh->read_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('defaults to unread (null read_at)', function () {
    $message = IncidentMessage::factory()->create();

    expect($message->read_at)->toBeNull();
});

it('has message_type defaulting to text', function () {
    $message = IncidentMessage::factory()->create();

    expect($message->message_type)->toBe('text');
});
