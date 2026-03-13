<?php

namespace App\Enums;

enum IncidentOutcome: string
{
    case TreatedOnScene = 'TREATED_ON_SCENE';
    case TransportedToHospital = 'TRANSPORTED_TO_HOSPITAL';
    case RefusedTreatment = 'REFUSED_TREATMENT';
    case DeclaredDOA = 'DECLARED_DOA';
    case FalseAlarm = 'FALSE_ALARM';

    /**
     * Get the human-readable label for this outcome.
     */
    public function label(): string
    {
        return match ($this) {
            self::TreatedOnScene => 'Treated on Scene',
            self::TransportedToHospital => 'Transported to Hospital',
            self::RefusedTreatment => 'Refused Treatment',
            self::DeclaredDOA => 'Declared DOA',
            self::FalseAlarm => 'False Alarm',
        };
    }

    /**
     * Determine if this outcome is a medical outcome requiring vitals.
     */
    public function isMedical(): bool
    {
        return in_array($this, [
            self::TreatedOnScene,
            self::TransportedToHospital,
        ], true);
    }
}
