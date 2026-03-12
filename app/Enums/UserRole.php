<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Dispatcher = 'dispatcher';
    case Operator = 'operator';
    case Responder = 'responder';
    case Supervisor = 'supervisor';
}
