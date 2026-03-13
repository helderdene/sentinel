<?php

namespace App\Services;

use App\Contracts\ProximityServiceInterface;
use App\Enums\UnitStatus;
use Illuminate\Support\Facades\DB;

class ProximityRankingService implements ProximityServiceInterface
{
    /**
     * Rank nearby available units by distance from the given coordinates using PostGIS.
     *
     * @return array<int, object>
     */
    public function rankNearbyUnits(float $latitude, float $longitude, float $radiusMeters = 50000.0): array
    {
        return DB::select('
            SELECT
                id,
                callsign,
                type,
                agency,
                crew_capacity,
                status,
                ST_Y(coordinates::geometry) as latitude,
                ST_X(coordinates::geometry) as longitude,
                ST_Distance(
                    coordinates,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                ) as distance_meters
            FROM units
            WHERE status = ?
              AND coordinates IS NOT NULL
              AND ST_DWithin(
                    coordinates,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                    ?
                  )
            ORDER BY distance_meters ASC
        ', [$longitude, $latitude, UnitStatus::Available->value, $longitude, $latitude, $radiusMeters]);
    }
}
