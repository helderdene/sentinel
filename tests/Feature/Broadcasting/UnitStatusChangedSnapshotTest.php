<?php

use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Events\UnitStatusChanged;
use App\Models\Unit;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Pin clock to a fixed instant so `created_at` / `now()` are identical across runs.
    Carbon::setTestNow(Carbon::parse('2026-04-21T00:00:00Z'));
});

it('UnitStatusChanged payload matches golden fixture', function () {
    // Pin every UnitFactory field that uses fake() (type, agency, crew_capacity,
    // coordinates, shift) to prevent fixture drift.
    $unit = Unit::factory()->create([
        'id' => 'AMB-01',
        'callsign' => 'AMB 01',
        'type' => UnitType::Ambulance,
        'agency' => 'CDRRMO',
        'crew_capacity' => 4,
        'status' => UnitStatus::Dispatched,
        'coordinates' => Point::makeGeodetic(8.9475, 125.5406),
        'shift' => 'day',
    ]);

    $payload = (new UnitStatusChanged($unit, UnitStatus::Available))->broadcastWith();
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $fixturePath = __DIR__.'/__snapshots__/UnitStatusChanged.json';

    if (! file_exists($fixturePath)) {
        file_put_contents($fixturePath, $json);
        $this->markTestIncomplete('Golden fixture created; re-run to verify.');
    }

    expect($json)->toBe(file_get_contents($fixturePath));
});
