<?php

namespace App\Contracts;

interface DirectionsServiceInterface
{
    /**
     * Calculate road-network route between two coordinate pairs.
     *
     * The steps array contains turn-by-turn maneuvers for in-app navigation.
     * Each step: instruction (string), type (string), modifier (?string),
     * distance_meters (float), location ([lng, lat]).
     *
     * @return array{
     *     distance_meters: float,
     *     duration_seconds: float,
     *     geometry: string,
     *     coordinates: array<int, array{0: float, 1: float}>,
     *     steps: array<int, array{
     *         instruction: string,
     *         type: string,
     *         modifier: ?string,
     *         distance_meters: float,
     *         location: array{0: float, 1: float},
     *     }>,
     * }
     */
    public function route(float $originLat, float $originLng, float $destLat, float $destLng): array;
}
