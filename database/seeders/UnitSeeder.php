<?php

namespace Database\Seeders;

use App\Models\Unit;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Seed the default response units.
     */
    public function run(): void
    {
        $units = [
            ['id' => 'AMB-01', 'callsign' => 'Ambulance 1', 'type' => 'ambulance', 'agency' => 'CDRRMO', 'crew_capacity' => 3, 'lat' => 8.9490, 'lng' => 125.5400],
            ['id' => 'AMB-02', 'callsign' => 'Ambulance 2', 'type' => 'ambulance', 'agency' => 'CDRRMO', 'crew_capacity' => 3, 'lat' => 8.9470, 'lng' => 125.5380],
            ['id' => 'AMB-03', 'callsign' => 'Ambulance 3', 'type' => 'ambulance', 'agency' => 'CDRRMO', 'crew_capacity' => 3, 'lat' => 8.9510, 'lng' => 125.5420],
            ['id' => 'RESCUE-01', 'callsign' => 'Rescue Unit 1', 'type' => 'rescue', 'agency' => 'CDRRMO', 'crew_capacity' => 4, 'lat' => 8.9485, 'lng' => 125.5410],
            ['id' => 'RESCUE-02', 'callsign' => 'Rescue Unit 2', 'type' => 'rescue', 'agency' => 'CDRRMO', 'crew_capacity' => 4, 'lat' => 8.9460, 'lng' => 125.5390],
            ['id' => 'FIRE-01', 'callsign' => 'Fire Engine 1', 'type' => 'fire', 'agency' => 'BFP', 'crew_capacity' => 6, 'lat' => 8.9500, 'lng' => 125.5350],
            ['id' => 'FIRE-02', 'callsign' => 'Fire Engine 2', 'type' => 'fire', 'agency' => 'BFP', 'crew_capacity' => 6, 'lat' => 8.9520, 'lng' => 125.5370],
            ['id' => 'POLICE-01', 'callsign' => 'Patrol Unit 1', 'type' => 'police', 'agency' => 'PNP', 'crew_capacity' => 2, 'lat' => 8.9475, 'lng' => 125.5406],
            ['id' => 'POLICE-02', 'callsign' => 'Patrol Unit 2', 'type' => 'police', 'agency' => 'PNP', 'crew_capacity' => 2, 'lat' => 8.9455, 'lng' => 125.5430],
            ['id' => 'BOAT-01', 'callsign' => 'Rescue Boat 1', 'type' => 'boat', 'agency' => 'CDRRMO', 'crew_capacity' => 4, 'lat' => 8.9530, 'lng' => 125.5300],
        ];

        foreach ($units as $unitData) {
            Unit::updateOrCreate(
                ['id' => $unitData['id']],
                [
                    'callsign' => $unitData['callsign'],
                    'type' => $unitData['type'],
                    'agency' => $unitData['agency'],
                    'crew_capacity' => $unitData['crew_capacity'],
                    'status' => 'AVAILABLE',
                    'coordinates' => Point::makeGeodetic($unitData['lat'], $unitData['lng']),
                ]
            );
        }
    }
}
