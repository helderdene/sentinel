<?php

use App\Enums\IncidentChannel;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;

it('returns correct per-channel pending counts in queue endpoint', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    Incident::factory()->count(3)->create([
        'status' => IncidentStatus::Pending,
        'channel' => IncidentChannel::Phone,
    ]);
    Incident::factory()->count(2)->create([
        'status' => IncidentStatus::Pending,
        'channel' => IncidentChannel::Sms,
    ]);
    Incident::factory()->create([
        'status' => IncidentStatus::Pending,
        'channel' => IncidentChannel::IoT,
    ]);

    $this->actingAs($dispatcher)
        ->get(route('incidents.queue'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('channelCounts')
            ->where('channelCounts.phone', 3)
            ->where('channelCounts.sms', 2)
            ->where('channelCounts.iot', 1)
        );
});

it('only counts PENDING incidents in channel counts', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    Incident::factory()->count(2)->create([
        'status' => IncidentStatus::Pending,
        'channel' => IncidentChannel::Phone,
    ]);
    Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'channel' => IncidentChannel::Phone,
    ]);
    Incident::factory()->create([
        'status' => IncidentStatus::Resolved,
        'channel' => IncidentChannel::Phone,
    ]);

    $this->actingAs($dispatcher)
        ->get(route('incidents.queue'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('channelCounts.phone', 2)
        );
});

it('returns empty channel counts when no pending incidents exist', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get(route('incidents.queue'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('channelCounts')
            ->has('incidents', 0)
        );
});

it('returns counts for all channels with mixed incidents', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    Incident::factory()->create([
        'status' => IncidentStatus::Pending,
        'channel' => IncidentChannel::App,
    ]);
    Incident::factory()->create([
        'status' => IncidentStatus::Pending,
        'channel' => IncidentChannel::Radio,
    ]);

    $this->actingAs($dispatcher)
        ->get(route('incidents.queue'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('channelCounts.app', 1)
            ->where('channelCounts.radio', 1)
        );
});
