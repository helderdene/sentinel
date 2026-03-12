<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Priority Suggestion Keywords
    |--------------------------------------------------------------------------
    |
    | Keywords used by PrioritySuggestionService to adjust priority confidence
    | and potentially escalate/de-escalate the suggested priority level.
    | Each keyword maps to a confidence adjustment integer.
    |
    */

    'escalation_keywords' => [
        // English
        'trapped' => 10,
        'unconscious' => 15,
        'multiple' => 10,
        'children' => 10,
        'critical' => 15,
        'dying' => 20,
        'severe' => 10,
        'mass' => 15,
        'collapse' => 10,
        'explosion' => 15,
        'unresponsive' => 15,

        // Filipino
        'nakulong' => 10,
        'walang' => 8,
        'malay' => 8,
        'marami' => 10,
        'bata' => 10,
        'malala' => 10,
        'nasusunog' => 10,
        'bumaha' => 10,
    ],

    'deescalation_keywords' => [
        // English
        'minor' => -15,
        'small' => -10,
        'contained' => -10,
        'stable' => -10,
        'false' => -20,
        'drill' => -25,
        'test' => -25,
        'cancel' => -30,

        // Filipino
        'maliit' => -10,
        'kontrolado' => -10,
        'kaunti' => -10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Suggestion Thresholds
    |--------------------------------------------------------------------------
    |
    | Cumulative keyword adjustment thresholds for escalation/de-escalation.
    |
    */

    'escalation_threshold' => 25,
    'deescalation_threshold' => -25,
    'base_confidence' => 70,
    'min_confidence' => 30,
    'max_confidence' => 99,

];
