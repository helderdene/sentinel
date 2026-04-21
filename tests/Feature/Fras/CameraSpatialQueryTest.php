<?php

use App\Models\Camera;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\DB;

pest()->group('fras');

it('finds cameras within 500m of a point via ST_DWithin', function () {
    // Butuan City plaza reference point
    $plazaLat = 8.9475;
    $plazaLng = 125.5406;

    // Camera ~200m north of plaza (well within 500m radius)
    $nearCamera = Camera::factory()->create([
        'name' => 'Near Camera',
        'location' => Point::makeGeodetic($plazaLat + 0.0018, $plazaLng),
    ]);

    // Camera ~5km north of plaza (outside 500m radius)
    $farCamera = Camera::factory()->create([
        'name' => 'Far Camera',
        'location' => Point::makeGeodetic($plazaLat + 0.045, $plazaLng),
    ]);

    $rows = DB::select('
        SELECT id, name
        FROM cameras
        WHERE ST_DWithin(
            location,
            ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
            500
        )
    ', [$plazaLng, $plazaLat]); // ST_MakePoint is lng-first

    $returnedIds = array_map(fn ($row) => $row->id, $rows);

    expect($returnedIds)->toContain($nearCamera->id);
    expect($returnedIds)->not->toContain($farCamera->id);
});

it('returns no cameras when none are within radius', function () {
    // Seed one camera near Butuan plaza
    Camera::factory()->create([
        'location' => Point::makeGeodetic(8.9475, 125.5406),
    ]);

    // Query from a point ~100km away in Davao region
    $rows = DB::select('
        SELECT id FROM cameras
        WHERE ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, 500)
    ', [125.6, 7.0]);

    expect($rows)->toBeEmpty();
});
