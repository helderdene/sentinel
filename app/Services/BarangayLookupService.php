<?php

namespace App\Services;

use App\Models\Barangay;
use Illuminate\Support\Facades\DB;

class BarangayLookupService
{
    /**
     * Find the barangay that contains the given coordinates using PostGIS ST_Contains.
     */
    public function findByCoordinates(float $latitude, float $longitude): ?Barangay
    {
        $result = DB::select('
            SELECT id, name FROM barangays
            WHERE ST_Contains(
                boundary::geometry,
                ST_SetSRID(ST_MakePoint(?, ?), 4326)::geometry
            )
            LIMIT 1
        ', [$longitude, $latitude]);

        if (empty($result)) {
            return null;
        }

        return Barangay::find($result[0]->id);
    }
}
