<?php

use App\Contracts\DirectionsServiceInterface;
use App\Contracts\GeocodingServiceInterface;
use App\Contracts\ProximityServiceInterface;
use App\Contracts\SmsParserServiceInterface;
use App\Contracts\SmsServiceInterface;
use App\Services\StubMapboxDirectionsService;
use App\Services\StubMapboxGeocodingService;
use App\Services\StubSemaphoreSmsService;
use Illuminate\Support\Facades\Log;

it('resolves GeocodingServiceInterface from container', function () {
    $service = app(GeocodingServiceInterface::class);

    expect($service)->toBeInstanceOf(GeocodingServiceInterface::class)
        ->and($service)->toBeInstanceOf(StubMapboxGeocodingService::class);
});

it('resolves SmsServiceInterface from container', function () {
    $service = app(SmsServiceInterface::class);

    expect($service)->toBeInstanceOf(SmsServiceInterface::class)
        ->and($service)->toBeInstanceOf(StubSemaphoreSmsService::class);
});

it('resolves ProximityServiceInterface from container', function () {
    $service = app(ProximityServiceInterface::class);

    expect($service)->toBeInstanceOf(ProximityServiceInterface::class);
});

it('resolves SmsParserServiceInterface from container', function () {
    $service = app(SmsParserServiceInterface::class);

    expect($service)->toBeInstanceOf(SmsParserServiceInterface::class);
});

it('resolves DirectionsServiceInterface from container', function () {
    $service = app(DirectionsServiceInterface::class);

    expect($service)->toBeInstanceOf(DirectionsServiceInterface::class)
        ->and($service)->toBeInstanceOf(StubMapboxDirectionsService::class);
});

it('stub geocoding service logs calls', function () {
    Log::spy();

    $service = app(GeocodingServiceInterface::class);
    $service->forward('Butuan City Hall');

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message) => $message === 'StubMapboxGeocodingService::forward')
        ->once();
});

it('stub SMS service logs calls', function () {
    Log::spy();

    $service = app(SmsServiceInterface::class);
    $service->send('09171234567', 'Test message');

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message) => $message === 'StubSemaphoreSmsService::send')
        ->once();
});

it('stub directions service logs calls', function () {
    Log::spy();

    $service = app(DirectionsServiceInterface::class);
    $service->route(8.9475, 125.5406, 8.9560, 125.5300);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message) => $message === 'StubMapboxDirectionsService::route')
        ->once();
});
