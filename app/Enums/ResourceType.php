<?php

namespace App\Enums;

enum ResourceType: string
{
    case AdditionalAmbulance = 'ADDITIONAL_AMBULANCE';
    case FireUnit = 'FIRE_UNIT';
    case PoliceBackup = 'POLICE_BACKUP';
    case RescueBoat = 'RESCUE_BOAT';
    case MedicalOfficer = 'MEDICAL_OFFICER';
    case Medevac = 'MEDEVAC';

    /**
     * Get the human-readable label for this resource type.
     */
    public function label(): string
    {
        return match ($this) {
            self::AdditionalAmbulance => 'Additional Ambulance',
            self::FireUnit => 'Fire Unit',
            self::PoliceBackup => 'Police Backup',
            self::RescueBoat => 'Rescue Boat',
            self::MedicalOfficer => 'Medical Officer',
            self::Medevac => 'Medevac',
        };
    }
}
