<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Pending = 'PENDING';
    case Triaged = 'TRIAGED';
    case Dispatched = 'DISPATCHED';
    case Acknowledged = 'ACKNOWLEDGED';
    case EnRoute = 'EN_ROUTE';
    case OnScene = 'ON_SCENE';
    case Resolving = 'RESOLVING';
    case Resolved = 'RESOLVED';
}
