<?php

namespace App\Services;

use App\Contracts\DirectionsServiceInterface;
use Illuminate\Support\Facades\Log;

class StubMapboxDirectionsService implements DirectionsServiceInterface
{
    /**
     * Calculate route between two coordinate pairs using Haversine approximation.
     *
     * Returns deterministic ETA based on straight-line distance at 30km/h
     * urban speed factor, matching Butuan City area travel estimates.
     *
     * @return array{distance_meters: float, duration_seconds: float, geometry: string, coordinates: array<int, array{0: float, 1: float}>}
     */
    public function route(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        Log::info('StubMapboxDirectionsService::route', compact(
            'originLat', 'originLng', 'destLat', 'destLng',
        ));

        $distanceKm = $this->haversine($originLat, $originLng, $destLat, $destLng);
        $distanceMeters = $distanceKm * 1000;
        $durationSeconds = ($distanceKm / 30) * 3600;

        return [
            'distance_meters' => round($distanceMeters, 1),
            'duration_seconds' => round($durationSeconds, 1),
            'geometry' => '',
            'coordinates' => [
                [$originLng, $originLat],
                [$destLng, $destLat],
            ],
            'steps' => [],
        ];
    }

    /**
     * Calculate the Haversine distance between two points on Earth.
     *
     * @return float Distance in kilometers
     */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
