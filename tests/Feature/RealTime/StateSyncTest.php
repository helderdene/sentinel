<?php

use App\Enums\IncidentStatus;
use App\Enums\UnitStatus;
use App\Models\Incident;
use App\Models\Unit;
use App\Models\User;

it('returns incidents ordered by priority then FIFO', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $p3 = Incident::factory()->for($dispatcher, 'createdBy')->create([
        'priority' => 'P3',
        'status' => IncidentStatus::Pending,
        'created_at' => now()->subMinutes(5),
    ]);
    $p1 = Incident::factory()->for($dispatcher, 'createdBy')->create([
        'priority' => 'P1',
        'status' => IncidentStatus::Pending,
        'created_at' => now()->subMinutes(3),
    ]);
    $p1_older = Incident::factory()->for($dispatcher, 'createdBy')->create([
        'priority' => 'P1',
        'status' => IncidentStatus::Pending,
        'created_at' => now()->subMinutes(10),
    ]);

    $response = $this->actingAs($dispatcher)
        ->getJson(route('state-sync'))
        ->assertSuccessful();

    $ids = collect($response->json('incidents'))->pluck('id')->values()->all();
    expect($ids[0])->toBe($p1_older->id);
    expect($ids[1])->toBe($p1->id);
    expect($ids[2])->toBe($p3->id);
});

it('returns channel counts', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    Incident::factory()->for($dispatcher, 'createdBy')->create([
        'status' => IncidentStatus::Pending,
        'channel' => 'phone',
    ]);
    Incident::factory()->for($dispatcher, 'createdBy')->create([
        'status' => IncidentStatus::Pending,
        'channel' => 'phone',
    ]);
    Incident::factory()->for($dispatcher, 'createdBy')->create([
        'status' => IncidentStatus::Pending,
        'channel' => 'radio',
    ]);

    $response = $this->actingAs($dispatcher)
        ->getJson(route('state-sync'))
        ->assertSuccessful();

    $channelCounts = $response->json('channelCounts');
    expect($channelCounts)->toHaveKey('phone');
    expect($channelCounts['phone'])->toBe(2);
    expect($channelCounts['radio'])->toBe(1);
});

it('returns non-offline units', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $available = Unit::factory()->create(['status' => UnitStatus::Available]);
    $dispatched = Unit::factory()->create(['status' => UnitStatus::Dispatched]);
    $offline = Unit::factory()->create(['status' => UnitStatus::Offline]);

    $response = $this->actingAs($dispatcher)
        ->getJson(route('state-sync'))
        ->assertSuccessful();

    $unitIds = collect($response->json('units'))->pluck('id')->all();
    expect($unitIds)->toContain($available->id);
    expect($unitIds)->toContain($dispatched->id);
    expect($unitIds)->not->toContain($offline->id);
});

it('excludes resolved incidents', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    Incident::factory()->for($dispatcher, 'createdBy')->create([
        'status' => IncidentStatus::Pending,
    ]);
    Incident::factory()->for($dispatcher, 'createdBy')->create([
        'status' => IncidentStatus::Resolved,
        'resolved_at' => now(),
    ]);

    $response = $this->actingAs($dispatcher)
        ->getJson(route('state-sync'))
        ->assertSuccessful();

    expect($response->json('incidents'))->toHaveCount(1);
});

it('requires authentication', function () {
    $this->getJson(route('state-sync'))
        ->assertUnauthorized();
});

it('responders cannot access state-sync', function () {
    $responder = User::factory()->responder()->create();

    $this->actingAs($responder)
        ->getJson(route('state-sync'))
        ->assertForbidden();
});
