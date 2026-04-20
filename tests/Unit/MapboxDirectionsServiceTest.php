<?php

use App\Services\MapboxDirectionsService;
use Illuminate\Support\Facades\Http;

it('parses Mapbox Directions API response into canonical shape', function () {
    Http::fake([
        'api.mapbox.com/*' => Http::response([
            'code' => 'Ok',
            'routes' => [
                [
                    'distance' => 1234.5,
                    'duration' => 310.0,
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [125.5406, 8.9475],
                            [125.5400, 8.9490],
                            [125.5300, 8.9560],
                        ],
                    ],
                    'legs' => [
                        [
                            'steps' => [
                                [
                                    'distance' => 500.0,
                                    'maneuver' => [
                                        'instruction' => 'Head north on Magallanes Street',
                                        'type' => 'depart',
                                        'location' => [125.5406, 8.9475],
                                    ],
                                ],
                                [
                                    'distance' => 734.5,
                                    'maneuver' => [
                                        'instruction' => 'Turn right onto Taguibo-Masao Road',
                                        'type' => 'turn',
                                        'modifier' => 'right',
                                        'location' => [125.5400, 8.9490],
                                    ],
                                    'voiceInstructions' => [
                                        [
                                            'distanceAlongGeometry' => 400.0,
                                            'announcement' => 'In 400 meters, turn right onto Taguibo-Masao Road',
                                        ],
                                        [
                                            'distanceAlongGeometry' => 50.0,
                                            'announcement' => 'Turn right',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $service = new MapboxDirectionsService(
        'https://api.mapbox.com/directions/v5/mapbox/driving-traffic',
        'test-key',
    );

    $result = $service->route(8.9475, 125.5406, 8.9560, 125.5300);

    expect($result['distance_meters'])->toBe(1234.5)
        ->and($result['duration_seconds'])->toBe(310.0)
        ->and($result['coordinates'])->toHaveCount(3)
        ->and($result['coordinates'][0])->toBe([125.5406, 8.9475])
        ->and($result['steps'])->toHaveCount(2)
        ->and($result['steps'][0]['instruction'])->toBe('Head north on Magallanes Street')
        ->and($result['steps'][1]['modifier'])->toBe('right')
        ->and($result['steps'][0]['location'])->toBe([125.5406, 8.9475])
        ->and($result['steps'][1]['voice_instructions'])->toHaveCount(2)
        ->and($result['steps'][1]['voice_instructions'][0]['distance_along_geometry'])->toBe(400.0)
        ->and($result['steps'][1]['voice_instructions'][0]['announcement'])->toBe('In 400 meters, turn right onto Taguibo-Masao Road');
});

it('sends voice_instructions=true and voice_units=metric', function () {
    Http::fake([
        'api.mapbox.com/*' => Http::response([
            'code' => 'Ok',
            'routes' => [[
                'distance' => 100,
                'duration' => 10,
                'geometry' => ['type' => 'LineString', 'coordinates' => [[0, 0], [1, 1]]],
            ]],
        ]),
    ]);

    (new MapboxDirectionsService(
        'https://api.mapbox.com/directions/v5/mapbox/driving-traffic',
        'test-key',
    ))->route(8.9475, 125.5406, 8.9560, 125.5300);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'voice_instructions=true')
        && str_contains($request->url(), 'voice_units=metric'));
});

it('throws when Mapbox returns no routes', function () {
    Http::fake([
        'api.mapbox.com/*' => Http::response(['code' => 'NoRoute', 'routes' => []]),
    ]);

    $service = new MapboxDirectionsService(
        'https://api.mapbox.com/directions/v5/mapbox/driving-traffic',
        'test-key',
    );

    $service->route(8.9475, 125.5406, 8.9560, 125.5300);
})->throws(RuntimeException::class, 'no routes');

it('throws on HTTP failure', function () {
    Http::fake([
        'api.mapbox.com/*' => Http::response('unauthorized', 401),
    ]);

    $service = new MapboxDirectionsService(
        'https://api.mapbox.com/directions/v5/mapbox/driving-traffic',
        'bad-key',
    );

    $service->route(8.9475, 125.5406, 8.9560, 125.5300);
})->throws(RuntimeException::class);

it('sends coordinates in lng,lat;lng,lat order with access_token and geojson geometry', function () {
    Http::fake([
        'api.mapbox.com/*' => Http::response([
            'code' => 'Ok',
            'routes' => [[
                'distance' => 100,
                'duration' => 10,
                'geometry' => ['type' => 'LineString', 'coordinates' => [[125.5, 8.9], [125.6, 9.0]]],
            ]],
        ]),
    ]);

    $service = new MapboxDirectionsService(
        'https://api.mapbox.com/directions/v5/mapbox/driving-traffic',
        'test-key',
    );

    $service->route(8.9475, 125.5406, 8.9560, 125.5300);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '125.540600,8.947500;125.530000,8.956000')
            && str_contains($request->url(), 'access_token=test-key')
            && str_contains($request->url(), 'geometries=geojson');
    });
});
