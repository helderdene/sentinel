<?php

use App\Events\IncidentCreated;
use App\Events\UnitLocationUpdated;
use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    Event::fake([
        IncidentCreated::class,
        UnitLocationUpdated::class,
    ]);
});

it('updates responder unit location', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);

    $this->actingAs($responder)
        ->postJson(route('responder.update-location'), [
            'latitude' => 8.9475,
            'longitude' => 125.5406,
        ])
        ->assertSuccessful();

    Event::assertDispatched(UnitLocationUpdated::class);
});

it('validates coordinate ranges', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);

    $this->actingAs($responder)
        ->postJson(route('responder.update-location'), [
            'latitude' => 100,
            'longitude' => 200,
        ])
        ->assertUnprocessable();
});

it('returns 422 if responder has no unit', function () {
    $responder = User::factory()->responder()->create(['unit_id' => null]);

    $this->actingAs($responder)
        ->postJson(route('responder.update-location'), [
            'latitude' => 8.9475,
            'longitude' => 125.5406,
        ])
        ->assertUnprocessable();
});
