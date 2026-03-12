<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Keyword to Incident Type Mapping
    |--------------------------------------------------------------------------
    |
    | Maps Filipino and English keywords from inbound SMS messages to
    | incident type codes for automatic classification.
    |
    */

    'keyword_map' => [
        'sunog' => 'FIR-001',
        'fire' => 'FIR-001',
        'baha' => 'NAT-002',
        'flood' => 'NAT-002',
        'aksidente' => 'TRA-001',
        'accident' => 'TRA-001',
        'ambulansya' => 'MED-001',
        'ambulance' => 'MED-001',
        'lindol' => 'NAT-001',
        'earthquake' => 'NAT-001',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Type Code
    |--------------------------------------------------------------------------
    |
    | When no keyword matches, incidents are classified as this type.
    |
    */

    'default_type_code' => 'PUB-001',

];
