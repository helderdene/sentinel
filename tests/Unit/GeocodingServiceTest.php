<?php

use App\Contracts\GeocodingServiceInterface;
use App\Services\StubMapboxGeocodingService;

it('implements geocoding service interface', function () {
    $service = new StubMapboxGeocodingService;

    expect($service)->toBeInstanceOf(GeocodingServiceInterface::class);
});

it('returns array of geocoding results for a query', function () {
    $service = new StubMapboxGeocodingService;
    $results = $service->forward('Butuan City Hall');

    expect($results)->toBeArray()
        ->and($results)->not->toBeEmpty()
        ->and(count($results))->toBeLessThanOrEqual(3);
});

it('returns results with required shape keys', function () {
    $service = new StubMapboxGeocodingService;
    $results = $service->forward('Butuan City Hall');

    foreach ($results as $result) {
        expect($result)->toHaveKeys(['lat', 'lng', 'display_name'])
            ->and($result['lat'])->toBeFloat()
            ->and($result['lng'])->toBeFloat()
            ->and($result['display_name'])->toBeString();
    }
});

it('returns deterministic results for the same query', function () {
    $service = new StubMapboxGeocodingService;

    $first = $service->forward('Some Address');
    $second = $service->forward('Some Address');

    expect($first)->toEqual($second);
});

it('returns results within Butuan City area coordinates', function () {
    $service = new StubMapboxGeocodingService;
    $results = $service->forward('Any location query');

    foreach ($results as $result) {
        expect($result['lat'])->toBeGreaterThan(8.8)
            ->and($result['lat'])->toBeLessThan(9.1)
            ->and($result['lng'])->toBeGreaterThan(125.4)
            ->and($result['lng'])->toBeLessThan(125.7);
    }
});

it('returns different results for different queries', function () {
    $service = new StubMapboxGeocodingService;

    $first = $service->forward('Address One');
    $second = $service->forward('Address Two');

    expect($first)->not->toEqual($second);
});
