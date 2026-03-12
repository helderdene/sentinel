<?php

namespace App\Enums;

enum UnitStatus: string
{
    case Available = 'AVAILABLE';
    case Dispatched = 'DISPATCHED';
    case EnRoute = 'EN_ROUTE';
    case OnScene = 'ON_SCENE';
    case Offline = 'OFFLINE';
}
