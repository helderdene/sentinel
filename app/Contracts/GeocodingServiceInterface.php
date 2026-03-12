<?php

namespace App\Contracts;

interface GeocodingServiceInterface
{
    /**
     * Forward geocode an address text to coordinates.
     *
     * @return array{lat: float, lng: float, display_name: string}[]
     */
    public function forward(string $query, string $country = 'PH'): array;
}
