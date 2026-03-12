<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Pending = 'PENDING';
    case Dispatched = 'DISPATCHED';
    case Acknowledged = 'ACKNOWLEDGED';
    case EnRoute = 'EN_ROUTE';
    case OnScene = 'ON_SCENE';
    case Resolving = 'RESOLVING';
    case Resolved = 'RESOLVED';
}
