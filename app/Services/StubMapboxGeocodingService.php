<?php

namespace App\Services;

use App\Contracts\GeocodingServiceInterface;
use Illuminate\Support\Facades\Log;

class StubMapboxGeocodingService implements GeocodingServiceInterface
{
    private const BUTUAN_LAT = 8.9475;

    private const BUTUAN_LNG = 125.5406;

    /**
     * Forward geocode an address text to coordinates.
     *
     * Returns deterministic Butuan City area results based on query hash.
     * Coordinates stay within ~0.03 degrees of Butuan center (~3km radius).
     *
     * @return array{lat: float, lng: float, display_name: string}[]
     */
    public function forward(string $query, string $country = 'PH'): array
    {
        Log::info('StubMapboxGeocodingService::forward', compact('query', 'country'));

        $hash = abs(crc32($query));
        $results = [];

        for ($i = 0; $i < 3; $i++) {
            $latOffset = ((($hash + $i * 7919) % 600) - 300) / 10000;
            $lngOffset = ((($hash + $i * 6271) % 600) - 300) / 10000;

            $results[] = [
                'lat' => round(self::BUTUAN_LAT + $latOffset, 6),
                'lng' => round(self::BUTUAN_LNG + $lngOffset, 6),
                'display_name' => $query.', Butuan City, Agusan del Norte #'.($i + 1),
            ];
        }

        return $results;
    }
}
