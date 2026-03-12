<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Dispatcher = 'dispatcher';
    case Responder = 'responder';
    case Supervisor = 'supervisor';
}
