<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\IncidentType;
use Illuminate\Database\Seeder;

class AgencySeeder extends Seeder
{
    /**
     * Seed agencies and their incident type associations.
     */
    public function run(): void
    {
        $agencies = [
            [
                'name' => 'BFP Caraga',
                'code' => 'BFP',
                'contact_phone' => '(085) 342-5678',
                'radio_channel' => 'VHF Ch 12',
                'categories' => ['Fire'],
            ],
            [
                'name' => 'PNP Butuan',
                'code' => 'PNP',
                'contact_phone' => '(085) 225-2222',
                'radio_channel' => 'VHF Ch 8',
                'categories' => ['Crime / Security', 'Public Disturbance'],
            ],
            [
                'name' => 'DSWD Caraga',
                'code' => 'DSWD',
                'contact_phone' => '(085) 342-9876',
                'categories' => ['Natural Disaster'],
            ],
            [
                'name' => 'DOH Caraga',
                'code' => 'DOH',
                'contact_phone' => '(085) 342-1111',
                'categories' => ['Medical'],
            ],
            [
                'name' => 'LGU Cabadbaran',
                'code' => 'LGU_CABADBARAN',
                'contact_phone' => '(085) 343-5555',
                'categories' => ['Natural Disaster'],
            ],
        ];

        foreach ($agencies as $agencyData) {
            $categories = $agencyData['categories'];
            unset($agencyData['categories']);

            $agency = Agency::updateOrCreate(
                ['code' => $agencyData['code']],
                $agencyData,
            );

            $incidentTypeIds = IncidentType::whereIn('category', $categories)
                ->pluck('id')
                ->toArray();

            $agency->incidentTypes()->sync($incidentTypeIds);
        }
    }
}
