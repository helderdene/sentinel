<?php

namespace App\Contracts;

interface WeatherServiceInterface
{
    /**
     * Get current weather advisories for the operational area.
     *
     * @return array{advisories: array<int, array{type: string, level: string, title: string, description: string, affected_barangays: string[], issued_at: string, expires_at: string}>, current: array{rainfall_mm_hr: float, wind_speed_kph: float, wind_direction: string, temperature_c: float}}
     */
    public function getCurrentAdvisories(): array;

    /**
     * Get current weather conditions for a specific location.
     *
     * @return array{rainfall_mm_hr: float, wind_speed_kph: float, wind_direction: string, temperature_c: float}
     */
    public function getCurrentConditions(float $latitude, float $longitude): array;
}
