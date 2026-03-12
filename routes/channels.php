<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

$dispatchRoles = [UserRole::Operator, UserRole::Dispatcher, UserRole::Supervisor, UserRole::Admin];

Broadcast::channel('dispatch.incidents', function (User $user) use ($dispatchRoles): bool {
    return in_array($user->role, $dispatchRoles);
});

Broadcast::channel('dispatch.units', function (User $user) use ($dispatchRoles): bool {
    return in_array($user->role, $dispatchRoles);
});

Broadcast::channel('user.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});

Broadcast::channel('dispatch', function (User $user) use ($dispatchRoles): array|false {
    if (! in_array($user->role, $dispatchRoles)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->role->value,
    ];
});
