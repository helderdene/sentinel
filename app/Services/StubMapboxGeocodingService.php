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
     *
     * @return array{lat: float, lng: float, display_name: string}[]
     */
    public function forward(string $query, string $country = 'PH'): array
    {
        Log::info('StubMapboxGeocodingService::forward', compact('query', 'country'));

        $hash = crc32($query);
        $results = [];

        for ($i = 0; $i < 3; $i++) {
            $offset = (($hash + $i * 1000) % 1000) / 10000;

            $results[] = [
                'lat' => self::BUTUAN_LAT + $offset,
                'lng' => self::BUTUAN_LNG + $offset,
                'display_name' => $query.', Butuan City, Agusan del Norte #'.($i + 1),
            ];
        }

        return $results;
    }
}
