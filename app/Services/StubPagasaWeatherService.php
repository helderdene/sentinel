<?php

namespace App\Services;

use App\Contracts\WeatherServiceInterface;
use Illuminate\Support\Facades\Log;

class StubPagasaWeatherService implements WeatherServiceInterface
{
    /**
     * Get current weather advisories for the operational area.
     *
     * Returns static PAGASA-style advisories with real Butuan City barangay names
     * and the 3-level color-coded system (yellow, orange, red).
     *
     * @return array{advisories: array<int, array{type: string, level: string, title: string, description: string, affected_barangays: string[], issued_at: string, expires_at: string}>, current: array{rainfall_mm_hr: float, wind_speed_kph: float, wind_direction: string, temperature_c: float}}
     */
    public function getCurrentAdvisories(): array
    {
        Log::info('StubPagasaWeatherService::getCurrentAdvisories');

        $now = now();

        return [
            'advisories' => [
                [
                    'type' => 'rainfall',
                    'level' => 'orange',
                    'title' => 'Rainfall Warning Level 2 -- Butuan City',
                    'description' => 'Moderate to heavy rainfall expected in the next 3 hours. Possible flooding in low-lying barangays along the Agusan River.',
                    'affected_barangays' => ['Libertad', 'Baan Km 3', 'Baan Riverside', 'Limaha'],
                    'issued_at' => $now->subHour()->toIso8601String(),
                    'expires_at' => $now->addHours(3)->toIso8601String(),
                ],
                [
                    'type' => 'wind',
                    'level' => 'yellow',
                    'title' => 'Wind Advisory -- Agusan del Norte',
                    'description' => 'Strong winds of 45-60 kph expected from the northeast. Secure loose outdoor objects.',
                    'affected_barangays' => ['Doongan', 'Langihan', 'Villa Kananga'],
                    'issued_at' => $now->subMinutes(30)->toIso8601String(),
                    'expires_at' => $now->addHours(6)->toIso8601String(),
                ],
                [
                    'type' => 'flood',
                    'level' => 'red',
                    'title' => 'Flood Warning Level 3 -- Agusan River Basin',
                    'description' => 'Critical water level reached at Agusan River. Mandatory evacuation for riverside communities in Butuan City.',
                    'affected_barangays' => ['Obrero', 'Limaha', 'Baan Riverside'],
                    'issued_at' => $now->subMinutes(15)->toIso8601String(),
                    'expires_at' => $now->addHours(12)->toIso8601String(),
                ],
            ],
            'current' => [
                'rainfall_mm_hr' => 12.5,
                'wind_speed_kph' => 35.0,
                'wind_direction' => 'NE',
                'temperature_c' => 28.4,
            ],
        ];
    }

    /**
     * Get current weather conditions for a specific location.
     *
     * Returns static tropical weather data typical for Butuan City.
     *
     * @return array{rainfall_mm_hr: float, wind_speed_kph: float, wind_direction: string, temperature_c: float}
     */
    public function getCurrentConditions(float $latitude, float $longitude): array
    {
        Log::info('StubPagasaWeatherService::getCurrentConditions', compact('latitude', 'longitude'));

        return [
            'rainfall_mm_hr' => 8.2,
            'wind_speed_kph' => 22.0,
            'wind_direction' => 'NE',
            'temperature_c' => 28.7,
        ];
    }
}
