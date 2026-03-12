<?php

namespace App\Enums;

enum UnitType: string
{
    case Ambulance = 'ambulance';
    case Fire = 'fire';
    case Rescue = 'rescue';
    case Police = 'police';
    case Boat = 'boat';
}
