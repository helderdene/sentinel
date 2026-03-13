<?php

use App\Contracts\WeatherServiceInterface;
use App\Services\StubPagasaWeatherService;
use Illuminate\Support\Facades\Log;

it('resolves WeatherServiceInterface from container', function () {
    $service = app(WeatherServiceInterface::class);

    expect($service)->toBeInstanceOf(WeatherServiceInterface::class)
        ->and($service)->toBeInstanceOf(StubPagasaWeatherService::class);
});

it('getCurrentAdvisories returns advisories with required keys', function () {
    $service = app(WeatherServiceInterface::class);
    $result = $service->getCurrentAdvisories();

    expect($result)->toHaveKey('advisories')
        ->and($result)->toHaveKey('current')
        ->and($result['advisories'])->toBeArray()
        ->and($result['advisories'])->not->toBeEmpty();

    $advisory = $result['advisories'][0];

    expect($advisory)->toHaveKeys(['type', 'level', 'title', 'description', 'affected_barangays', 'issued_at', 'expires_at'])
        ->and($advisory['affected_barangays'])->toBeArray()
        ->and($advisory['affected_barangays'])->not->toBeEmpty();
});

it('getCurrentConditions returns required weather keys', function () {
    $service = app(WeatherServiceInterface::class);
    $conditions = $service->getCurrentConditions(8.9475, 125.5406);

    expect($conditions)->toHaveKeys(['rainfall_mm_hr', 'wind_speed_kph', 'wind_direction', 'temperature_c'])
        ->and($conditions['rainfall_mm_hr'])->toBeFloat()
        ->and($conditions['wind_speed_kph'])->toBeFloat()
        ->and($conditions['wind_direction'])->toBeString()
        ->and($conditions['temperature_c'])->toBeFloat();
});

it('advisory levels are valid PAGASA levels', function () {
    $service = app(WeatherServiceInterface::class);
    $result = $service->getCurrentAdvisories();
    $validLevels = ['yellow', 'orange', 'red'];

    foreach ($result['advisories'] as $advisory) {
        expect($validLevels)->toContain($advisory['level']);
    }
});

it('advisory data contains Butuan City barangay names', function () {
    $service = app(WeatherServiceInterface::class);
    $result = $service->getCurrentAdvisories();

    $butanBarangays = ['Libertad', 'Baan Km 3', 'Baan Riverside', 'Limaha', 'Doongan', 'Langihan', 'Obrero', 'Villa Kananga'];
    $allBarangays = collect($result['advisories'])
        ->pluck('affected_barangays')
        ->flatten()
        ->unique()
        ->values()
        ->all();

    // At least 3 of the known Butuan barangays should appear
    $matches = array_intersect($butanBarangays, $allBarangays);
    expect(count($matches))->toBeGreaterThanOrEqual(3);
});

it('weather stub logs calls via Log::info', function () {
    Log::spy();

    $service = app(WeatherServiceInterface::class);
    $service->getCurrentAdvisories();

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message) => $message === 'StubPagasaWeatherService::getCurrentAdvisories')
        ->once();
});

it('weather stub logs getCurrentConditions via Log::info', function () {
    Log::spy();

    $service = app(WeatherServiceInterface::class);
    $service->getCurrentConditions(8.9475, 125.5406);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message) => $message === 'StubPagasaWeatherService::getCurrentConditions')
        ->once();
});
