<?php

namespace App\Contracts;

interface DirectionsServiceInterface
{
    /**
     * Calculate road-network route between two coordinate pairs.
     *
     * @return array{distance_meters: float, duration_seconds: float, geometry: string}
     */
    public function route(float $originLat, float $originLng, float $destLat, float $destLng): array;
}
