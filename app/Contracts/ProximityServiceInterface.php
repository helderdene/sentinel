<?php

namespace App\Contracts;

interface ProximityServiceInterface
{
    /**
     * Rank nearby available units by distance from the given coordinates.
     *
     * @return array<int, object>
     */
    public function rankNearbyUnits(float $latitude, float $longitude, float $radiusMeters = 50000.0): array;
}
