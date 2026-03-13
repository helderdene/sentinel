<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mapbox Integration
    |--------------------------------------------------------------------------
    |
    | Mapbox provides geocoding (forward/reverse) and directions (routing/ETA)
    | services. The same API key is shared across both endpoints.
    |
    */

    'mapbox' => [
        'api_key' => env('MAPBOX_API_KEY', ''),
        'geocoding' => [
            'endpoint' => env('MAPBOX_GEOCODING_URL', 'https://api.mapbox.com/geocoding/v5/mapbox.places'),
            'simulate_errors' => false,
        ],
        'directions' => [
            'endpoint' => env('MAPBOX_DIRECTIONS_URL', 'https://api.mapbox.com/directions/v5/mapbox/driving-traffic'),
            'simulate_errors' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Semaphore SMS Integration
    |--------------------------------------------------------------------------
    |
    | Semaphore is a Philippine SMS gateway for outbound messages and
    | inbound webhook parsing.
    |
    */

    'semaphore' => [
        'api_key' => env('SEMAPHORE_API_KEY', ''),
        'sender_name' => env('SEMAPHORE_SENDER', 'CDRRMO'),
        'endpoint' => env('SEMAPHORE_API_URL', 'https://api.semaphore.co/api/v4/messages'),
        'simulate_errors' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | PAGASA Weather Integration
    |--------------------------------------------------------------------------
    |
    | PAGASA provides rainfall, wind, and flood advisory data for
    | Philippine municipalities via their weather API.
    |
    */

    'pagasa' => [
        'api_url' => env('PAGASA_API_URL', ''),
        'api_key' => env('PAGASA_API_KEY', ''),
        'simulate_errors' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | NDRRMC Integration
    |--------------------------------------------------------------------------
    |
    | National Disaster Risk Reduction and Management Council receives
    | Situation Reports (SitRep) via XML submission on P1 incident closure.
    |
    */

    'ndrrmc' => [
        'api_url' => env('NDRRMC_API_URL', ''),
        'api_key' => env('NDRRMC_API_KEY', ''),
        'simulate_errors' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | BFP Fire Sync Integration
    |--------------------------------------------------------------------------
    |
    | Bureau of Fire Protection bidirectional fire incident sync.
    | Inbound: BFP fire incidents mirrored into IRMS.
    | Outbound: IRMS fire incidents pushed to BFP.
    |
    */

    'bfp' => [
        'webhook_url' => env('BFP_WEBHOOK_URL', ''),
        'hmac_secret' => env('BFP_HMAC_SECRET', ''),
        'simulate_errors' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | PNP e-Blotter Integration
    |--------------------------------------------------------------------------
    |
    | Philippine National Police e-Blotter for criminal incident
    | auto-recording using the 5W1H framework.
    |
    */

    'pnp' => [
        'api_url' => env('PNP_API_URL', ''),
        'api_key' => env('PNP_API_KEY', ''),
        'simulate_errors' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Hospital EHR / HL7 FHIR R4 Integration
    |--------------------------------------------------------------------------
    |
    | Hospital Electronic Health Record integration using HL7 FHIR R4
    | for patient pre-notification on transport outcomes.
    |
    */

    'hospital_ehr' => [
        'fhir_base_url' => env('HOSPITAL_FHIR_BASE_URL', ''),
        'simulate_errors' => false,
    ],

];
