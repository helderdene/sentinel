<?php

namespace Database\Seeders;

use App\Enums\IncidentChannel;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Seeder;

class IncidentSeeder extends Seeder
{
    /**
     * Seed incidents with at least one per priority level.
     */
    public function run(): void
    {
        $operator = User::where('email', 'santos.ml@cdrrmo.gov.ph')->first()
            ?? User::first();

        $incidents = [
            [
                'type_code' => 'MED-001',
                'priority' => IncidentPriority::P1,
                'status' => IncidentStatus::Pending,
                'channel' => IncidentChannel::Phone,
                'location_text' => 'Butuan Doctors Hospital, J.C. Aquino Ave, Butuan City',
                'lat' => 8.9505,
                'lng' => 125.5318,
                'caller_name' => 'Maria Cruz',
                'caller_contact' => '09171234567',
                'raw_message' => 'Male, 55 years old, collapsed at the lobby, not breathing.',
                'notes' => 'Cardiac arrest reported by hospital security. CPR in progress by bystander.',
            ],
            [
                'type_code' => 'FIR-004',
                'priority' => IncidentPriority::P2,
                'status' => IncidentStatus::Pending,
                'channel' => IncidentChannel::Radio,
                'location_text' => 'Brgy. Libertad, near Agusan del Norte Provincial Capitol',
                'lat' => 8.9472,
                'lng' => 125.5440,
                'caller_name' => 'BFP Desk Officer',
                'caller_contact' => '09209876543',
                'raw_message' => 'Brush fire spreading toward residential area in Libertad.',
                'notes' => 'Wind pushing fire eastward. Two houses within 50 meters.',
            ],
            [
                'type_code' => 'VEH-005',
                'priority' => IncidentPriority::P3,
                'status' => IncidentStatus::Pending,
                'channel' => IncidentChannel::Sms,
                'location_text' => 'Montilla Blvd cor. A.D. Curato St, Butuan City',
                'lat' => 8.9558,
                'lng' => 125.5297,
                'caller_name' => 'Pedro Santos',
                'caller_contact' => '09351112222',
                'raw_message' => 'Two motorcycles bumped at the intersection. Minor scratches, no injuries.',
                'notes' => 'Both riders conscious and standing. Traffic partially blocked.',
            ],
            [
                'type_code' => 'PUB-003',
                'priority' => IncidentPriority::P4,
                'status' => IncidentStatus::Pending,
                'channel' => IncidentChannel::App,
                'location_text' => 'Robinsons Place Butuan, J.C. Aquino Ave',
                'lat' => 8.9444,
                'lng' => 125.5355,
                'caller_name' => 'Anonymous',
                'caller_contact' => null,
                'raw_message' => 'Loud karaoke from nearby residence, past 10 PM.',
                'notes' => 'Residential noise complaint. Ongoing for the past two hours.',
            ],
        ];

        foreach ($incidents as $data) {
            $type = IncidentType::where('code', $data['type_code'])->first();

            if (! $type) {
                continue;
            }

            Incident::create([
                'incident_type_id' => $type->id,
                'priority' => $data['priority'],
                'status' => $data['status'],
                'channel' => $data['channel'],
                'location_text' => $data['location_text'],
                'coordinates' => Point::makeGeodetic($data['lat'], $data['lng']),
                'caller_name' => $data['caller_name'],
                'caller_contact' => $data['caller_contact'],
                'raw_message' => $data['raw_message'],
                'notes' => $data['notes'],
                'tracking_token' => Incident::generateTrackingToken(),
                'created_by' => $operator?->id,
            ]);
        }
    }
}
