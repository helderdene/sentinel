<?php

namespace App\Services;

use App\Contracts\DirectionsServiceInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class MapboxDirectionsService implements DirectionsServiceInterface
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $apiKey,
    ) {}

    /**
     * Call the Mapbox Directions API (driving-traffic) and return a route
     * between the two coordinate pairs.
     *
     * @return array{distance_meters: float, duration_seconds: float, geometry: string, coordinates: array<int, array{0: float, 1: float}>}
     *
     * @throws RuntimeException on HTTP failure or missing route data.
     */
    public function route(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        $coords = sprintf('%F,%F;%F,%F', $originLng, $originLat, $destLng, $destLat);
        $url = rtrim($this->endpoint, '/').'/'.$coords;

        try {
            $response = Http::timeout(6)
                ->retry(2, 200, throw: false)
                ->get($url, [
                    'access_token' => $this->apiKey,
                    'geometries' => 'geojson',
                    'overview' => 'full',
                    'steps' => 'true',
                    'annotations' => 'duration,distance',
                ]);
        } catch (RequestException $e) {
            throw new RuntimeException('Mapbox Directions request failed: '.$e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new RuntimeException('Mapbox Directions request failed: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new RuntimeException("Mapbox Directions HTTP {$response->status()}: {$response->body()}");
        }

        $data = $response->json();
        $route = $data['routes'][0] ?? null;

        if ($route === null) {
            throw new RuntimeException('Mapbox Directions returned no routes.');
        }

        $coordinates = $route['geometry']['coordinates'] ?? [];
        $steps = [];

        foreach (($route['legs'] ?? []) as $leg) {
            foreach (($leg['steps'] ?? []) as $step) {
                $maneuver = $step['maneuver'] ?? [];
                $location = $maneuver['location'] ?? [0, 0];

                $steps[] = [
                    'instruction' => (string) ($maneuver['instruction'] ?? ''),
                    'type' => (string) ($maneuver['type'] ?? ''),
                    'modifier' => isset($maneuver['modifier']) ? (string) $maneuver['modifier'] : null,
                    'distance_meters' => (float) ($step['distance'] ?? 0),
                    'location' => [(float) ($location[0] ?? 0), (float) ($location[1] ?? 0)],
                ];
            }
        }

        return [
            'distance_meters' => (float) ($route['distance'] ?? 0),
            'duration_seconds' => (float) ($route['duration'] ?? 0),
            'geometry' => json_encode($route['geometry'] ?? new \stdClass) ?: '',
            'coordinates' => array_map(
                fn (array $pair): array => [(float) $pair[0], (float) $pair[1]],
                $coordinates,
            ),
            'steps' => $steps,
        ];
    }
}
