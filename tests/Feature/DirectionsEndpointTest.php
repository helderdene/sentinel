<?php

use App\Contracts\DirectionsServiceInterface;
use App\Models\User;
use App\Services\MapboxDirectionsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('returns a route for an authenticated responder', function () {
    $responder = User::factory()->responder()->create();

    $response = $this->actingAs($responder)
        ->getJson(route('api.directions', [
            'from_lat' => 8.9475,
            'from_lng' => 125.5406,
            'to_lat' => 8.9560,
            'to_lng' => 125.5300,
        ]))
        ->assertOk();

    $response->assertJsonStructure(['coordinates', 'distance_km', 'duration_min']);
});

it('validates required coordinate params', function () {
    $responder = User::factory()->responder()->create();

    $this->actingAs($responder)
        ->getJson(route('api.directions'))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['from_lat', 'from_lng', 'to_lat', 'to_lng']);
});

it('rejects unauthenticated requests', function () {
    $this->getJson(route('api.directions', [
        'from_lat' => 8.9475,
        'from_lng' => 125.5406,
        'to_lat' => 8.9560,
        'to_lng' => 125.5300,
    ]))->assertStatus(401);
});

it('caches identical coordinate requests', function () {
    Cache::flush();
    $responder = User::factory()->responder()->create();
    $params = [
        'from_lat' => 8.9475,
        'from_lng' => 125.5406,
        'to_lat' => 8.9560,
        'to_lng' => 125.5300,
    ];

    $first = $this->actingAs($responder)
        ->getJson(route('api.directions', $params))
        ->assertOk()
        ->json();

    $second = $this->actingAs($responder)
        ->getJson(route('api.directions', $params))
        ->assertOk()
        ->json();

    expect($second)->toEqual($first);
});

it('returns 502 when upstream fails and no cache hit', function () {
    Cache::flush();
    config()->set('integrations.mapbox.api_key', 'test-key');
    Http::fake([
        'api.mapbox.com/*' => Http::response('boom', 500),
    ]);
    $this->app->forgetInstance(DirectionsServiceInterface::class);
    $this->app->bind(DirectionsServiceInterface::class, fn () => new MapboxDirectionsService(
        'https://api.mapbox.com/directions/v5/mapbox/driving-traffic',
        'test-key',
    ));

    $responder = User::factory()->responder()->create();

    $this->actingAs($responder)
        ->getJson(route('api.directions', [
            'from_lat' => 8.9475,
            'from_lng' => 125.5406,
            'to_lat' => 8.9999,
            'to_lng' => 125.5999,
        ]))
        ->assertStatus(502);
});
