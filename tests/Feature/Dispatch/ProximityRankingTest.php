<?php

use App\Contracts\ProximityServiceInterface;
use App\Enums\IncidentStatus;
use App\Enums\UnitStatus;
use App\Events\IncidentCreated;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Unit;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;

beforeEach(function () {
    Event::fake([IncidentCreated::class]);
});

it('ranks nearby units sorted by distance ascending', function () {
    $service = app(ProximityServiceInterface::class);

    // Create units at different distances from reference point (Butuan City center)
    $referenceLatitude = 8.9475;
    $referenceLongitude = 125.5406;

    // Near unit (~1km away)
    Unit::factory()->create([
        'id' => 'AMB-01',
        'callsign' => 'AMB 01',
        'status' => UnitStatus::Available,
        'coordinates' => Point::makeGeodetic(8.9485, 125.5416),
    ]);

    // Far unit (~5km away)
    Unit::factory()->create([
        'id' => 'AMB-02',
        'callsign' => 'AMB 02',
        'status' => UnitStatus::Available,
        'coordinates' => Point::makeGeodetic(8.9875, 125.5806),
    ]);

    // Dispatched unit (should not appear)
    Unit::factory()->create([
        'id' => 'AMB-03',
        'callsign' => 'AMB 03',
        'status' => UnitStatus::Dispatched,
        'coordinates' => Point::makeGeodetic(8.9480, 125.5410),
    ]);

    // Unit without coordinates (should not appear)
    Unit::factory()->create([
        'id' => 'AMB-04',
        'callsign' => 'AMB 04',
        'status' => UnitStatus::Available,
        'coordinates' => null,
    ]);

    $results = $service->rankNearbyUnits($referenceLatitude, $referenceLongitude);

    // Should only return available units with coordinates
    expect($results)->toHaveCount(2);

    // First result should be the nearer unit
    expect($results[0]->id)->toBe('AMB-01');
    expect($results[1]->id)->toBe('AMB-02');

    // Should have distance_meters field
    expect($results[0]->distance_meters)->toBeLessThan($results[1]->distance_meters);
});

it('returns nearby units endpoint with distance and ETA for incident', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Triaged,
        'incident_type_id' => $type->id,
        'coordinates' => Point::makeGeodetic(8.9475, 125.5406),
    ]);

    Unit::factory()->create([
        'id' => 'AMB-10',
        'callsign' => 'AMB 10',
        'status' => UnitStatus::Available,
        'coordinates' => Point::makeGeodetic(8.9485, 125.5416),
    ]);

    $response = $this->actingAs($dispatcher)
        ->getJson(route('dispatch.nearby-units', $incident))
        ->assertSuccessful();

    $data = $response->json();
    expect($data)->toHaveCount(1);
    expect($data[0])->toHaveKeys(['id', 'callsign', 'distance_km', 'eta_minutes']);
});
