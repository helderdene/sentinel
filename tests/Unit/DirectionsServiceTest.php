<?php

use App\Contracts\DirectionsServiceInterface;
use App\Services\StubMapboxDirectionsService;
use Illuminate\Support\Facades\Log;

it('implements DirectionsServiceInterface', function () {
    $service = new StubMapboxDirectionsService;

    expect($service)->toBeInstanceOf(DirectionsServiceInterface::class);
});

it('returns array with required shape keys', function () {
    $service = new StubMapboxDirectionsService;
    $result = $service->route(8.9475, 125.5406, 8.9560, 125.5300);

    expect($result)->toHaveKeys(['distance_meters', 'duration_seconds', 'geometry']);
});

it('returns positive distance and duration for non-identical coordinates', function () {
    $service = new StubMapboxDirectionsService;
    $result = $service->route(8.9475, 125.5406, 8.9560, 125.5300);

    expect($result['distance_meters'])->toBeGreaterThan(0)
        ->and($result['duration_seconds'])->toBeGreaterThan(0);
});

it('returns zero distance for identical origin and destination', function () {
    $service = new StubMapboxDirectionsService;
    $result = $service->route(8.9475, 125.5406, 8.9475, 125.5406);

    expect($result['distance_meters'])->toBe(0.0)
        ->and($result['duration_seconds'])->toBe(0.0);
});

it('returns deterministic results for same input', function () {
    $service = new StubMapboxDirectionsService;

    $first = $service->route(8.9475, 125.5406, 8.9560, 125.5300);
    $second = $service->route(8.9475, 125.5406, 8.9560, 125.5300);

    expect($first)->toEqual($second);
});

it('returns distance and duration as floats', function () {
    $service = new StubMapboxDirectionsService;
    $result = $service->route(8.9475, 125.5406, 8.9560, 125.5300);

    expect($result['distance_meters'])->toBeFloat()
        ->and($result['duration_seconds'])->toBeFloat();
});

it('returns geometry as string', function () {
    $service = new StubMapboxDirectionsService;
    $result = $service->route(8.9475, 125.5406, 8.9560, 125.5300);

    expect($result['geometry'])->toBeString();
});

it('logs route call via Log::info', function () {
    Log::spy();

    $service = new StubMapboxDirectionsService;
    $service->route(8.9475, 125.5406, 8.9560, 125.5300);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) {
            return $message === 'StubMapboxDirectionsService::route'
                && $context['originLat'] === 8.9475
                && $context['originLng'] === 125.5406
                && $context['destLat'] === 8.9560
                && $context['destLng'] === 125.5300;
        })
        ->once();
});

it('returns realistic distances for Butuan City area coordinates', function () {
    $service = new StubMapboxDirectionsService;

    // ~1.5km apart within Butuan City
    $result = $service->route(8.9475, 125.5406, 8.9560, 125.5300);

    // Expect distance in reasonable range (500m to 5km)
    expect($result['distance_meters'])->toBeGreaterThan(500)
        ->and($result['distance_meters'])->toBeLessThan(5000);
});
